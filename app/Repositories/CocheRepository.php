<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Logger;

/**
 * Acceso a datos del catálogo de vehículos.
 *
 * Los listados filtrados pasan por el procedimiento almacenado
 * `sp_buscar_coches`, que construye la consulta con identificadores
 * fijos y envía los valores como variables de sesión: no existe ningún
 * punto en el que un dato del usuario se concatene en SQL.
 */
final class CocheRepository
{
    public const ORDENES = [
        'relevancia', 'precio_asc', 'precio_desc', 'potencia', 'novedades', 'vistas',
    ];

    private Database $db;

    public function __construct()
    {
        $this->db = Database::get();
    }

    // -----------------------------------------------------------------
    //  Buscador con filtros
    // -----------------------------------------------------------------

    /**
     * @param array{
     *     texto?:string, marcas?:string, categorias?:string,
     *     combustibles?:string, precio_min?:?int, precio_max?:?int,
     *     potencia_min?:?int, orden?:string
     * } $f
     * @return array{items:array<int,array<string,mixed>>,total:int,paginas:int,pagina:int}
     */
    public function buscar(array $f, int $pagina = 1, int $porPagina = 12): array
    {
        $pagina = max(1, $pagina);
        $orden = in_array($f['orden'] ?? '', self::ORDENES, true) ? $f['orden'] : 'relevancia';
        $offset = ($pagina - 1) * $porPagina;

        $total = 0;
        $filas = [];

        try {
            $stmt = $this->db->pdo()->prepare(
                'CALL sp_buscar_coches(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, @total_busqueda)'
            );

            $parametros = [
                1 => [$this->csv($f['texto'] ?? ''), \PDO::PARAM_STR],
                2 => [$this->csv($f['marcas'] ?? ''), \PDO::PARAM_STR],
                3 => [$this->csv($f['categorias'] ?? ''), \PDO::PARAM_STR],
                4 => [$this->csv($f['combustibles'] ?? ''), \PDO::PARAM_STR],
                5 => [$this->int($f['precio_min'] ?? null), \PDO::PARAM_INT],
                6 => [$this->int($f['precio_max'] ?? null), \PDO::PARAM_INT],
                7 => [$this->int($f['potencia_min'] ?? null), \PDO::PARAM_INT],
                8 => [$orden, \PDO::PARAM_STR],
                9 => [$porPagina, \PDO::PARAM_INT],
                10 => [$offset, \PDO::PARAM_INT],
            ];

            foreach ($parametros as $posicion => [$valor, $tipo]) {
                // Los parámetros admiten NULL: se vinculan como salida para
                // que MySQL no confunda un NULL con un entero 0.
                $vinculo = $valor === null ? ($tipo | \PDO::PARAM_INPUT_OUTPUT) : $tipo;
                $stmt->bindValue($posicion, $valor, $vinculo);
            }

            $stmt->execute();
            $filas = $stmt->fetchAll();
            $stmt->closeCursor();

            $total = (int) $this->db->scalar('SELECT @total_busqueda');
        } catch (\Throwable $e) {
            // Si la base de datos no puede ejecutar el procedimiento, la
            // lista no debe quedarse en blanco: se registra y se resuelve
            // con la vista del catálogo (mismos criterios de publicación).
            Logger::error('sp_buscar_coches no disponible: ' . $e->getMessage());
            return $this->buscarSimple($f, $pagina, $porPagina);
        }

        return [
            'items'   => $this->adjuntarImagenes($filas),
            'total'   => $total,
            'paginas' => max(1, (int) ceil($total / $porPagina)),
            'pagina'  => $pagina,
        ];
    }

    /**
     * Completa las filas del buscador con la fotografía principal y el
     * número de fotos.
     *
     * `sp_buscar_coches` devuelve los datos del vehículo pero no la imagen
     * (que vive en `coches_imagenes`/`imagenes`); sin este paso las tarjetas
     * del catálogo y el campo «imagen» de la API quedarían vacíos.
     *
     * @param array<int,array<string,mixed>> $filas
     * @return array<int,array<string,mixed>>
     */
    private function adjuntarImagenes(array $filas): array
    {
        if ($filas === []) {
            return $filas;
        }

        $ids = array_values(array_unique(array_map(
            static fn (array $fila): int => (int) ($fila['id'] ?? 0),
            $filas
        )));

        $ids = array_filter($ids, static fn (int $id): bool => $id > 0);

        if ($ids === []) {
            return $filas;
        }

        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        $rutas = [];
        $conteos = [];

        try {
            $relacion = $this->db->select(
                "SELECT ci.coche_id, i.ruta
                   FROM coches_imagenes ci
                   JOIN imagenes i ON i.id = ci.imagen_id
                  WHERE ci.coche_id IN ({$marcadores})
                  ORDER BY ci.es_principal DESC, ci.orden ASC",
                $ids
            );
        } catch (\Throwable $e) {
            Logger::warning('No se pudieron cargar las fotos del catálogo: ' . $e->getMessage());
            $relacion = [];
        }

        foreach ($relacion as $par) {
            $cocheId = (int) $par['coche_id'];
            $conteos[$cocheId] = ($conteos[$cocheId] ?? 0) + 1;

            // La primera fila de cada coche es la principal (ORDER BY arriba)
            $rutas[$cocheId] ??= (string) $par['ruta'];
        }

        foreach ($filas as $indice => $fila) {
            $cocheId = (int) ($fila['id'] ?? 0);
            $filas[$indice]['imagen_principal'] = $rutas[$cocheId] ?? null;
            $filas[$indice]['num_imagenes'] = $conteos[$cocheId] ?? 0;
        }

        return $filas;
    }

    /**
     * Respaldo del buscador con consultas preparadas normales.
     * Se mantiene idéntico en resultados al procedimiento.
     *
     * @param array<string,mixed> $f
     * @return array{items:array<int,array<string,mixed>>,total:int,paginas:int,pagina:int}
     */
    private function buscarSimple(array $f, int $pagina, int $porPagina): array
    {
        $donde = ['c.estado = "publicado"', 'c.borrado_en IS NULL'];
        $parametros = [];

        if (($texto = trim((string) ($f['texto'] ?? ''))) !== '') {
            $donde[] = 'CONCAT_WS(" ", m.nombre, c.modelo, COALESCE(c.version, "")) LIKE :texto';
            $parametros['texto'] = '%' . $texto . '%';
        }

        foreach ([
            'marcas'       => 'c.marca_id',
            'categorias'   => 'c.categoria',
            'combustibles' => 'c.combustible',
        ] as $clave => $columna) {
            if (($valor = trim((string) ($f[$clave] ?? ''))) !== '') {
                $partes = explode(',', $valor);
                $marcas = [];
                foreach ($partes as $i => $parte) {
                    $marcas[':f' . $i] = $parte;
                }
                $donde[] = $columna . ' IN (' . implode(', ', array_keys($marcas)) . ')';
                $parametros += $marcas;
            }
        }

        if (!empty($f['precio_min'])) {
            $donde[] = 'COALESCE(c.precio_nuevo, c.precio_ocasion) >= :pmin';
            $parametros['pmin'] = (int) $f['precio_min'];
        }
        if (!empty($f['precio_max'])) {
            $donde[] = 'COALESCE(c.precio_nuevo, c.precio_ocasion) <= :pmax';
            $parametros['pmax'] = (int) $f['precio_max'];
        }
        if (!empty($f['potencia_min'])) {
            $donde[] = 'COALESCE(c.potencia_cv, 0) >= :kmin';
            $parametros['kmin'] = (int) $f['potencia_min'];
        }

        $orden = match ($f['orden'] ?? '') {
            'precio_asc'  => 'c.precio_nuevo IS NULL, c.precio_nuevo ASC',
            'precio_desc' => 'c.precio_nuevo DESC',
            'potencia'    => 'c.potencia_cv DESC',
            'novedades'   => 'c.anio_desde DESC',
            'vistas'      => 'c.vistas DESC',
            default       => 'c.destacado DESC, c.valoracion DESC, c.vistas DESC',
        };

        $where = implode(' AND ', $donde);

        $total = (int) $this->db->scalar(
            "SELECT COUNT(*) FROM coches c JOIN marcas m ON m.id = c.marca_id WHERE {$where}",
            $parametros
        );

        $items = $this->db->select(
            "SELECT c.*, m.nombre AS marca, m.slug AS marca_slug, m.logo AS marca_logo
               FROM coches c JOIN marcas m ON m.id = c.marca_id
              WHERE {$where}
              ORDER BY {$orden}
              LIMIT " . (int) $porPagina . ' OFFSET ' . (int) (($pagina - 1) * $porPagina),
            $parametros
        );

        return [
            'items'   => $items,
            'total'   => $total,
            'paginas' => max(1, (int) ceil($total / $porPagina)),
            'pagina'  => $pagina,
        ];
    }

    // -----------------------------------------------------------------
    //  Fichas
    // -----------------------------------------------------------------

    /** Ficha completa de un vehículo publicado. */
    public function porSlug(string $slug): ?array
    {
        return $this->db->first(
            'SELECT * FROM v_coches_ficha WHERE slug = :slug LIMIT 1',
            ['slug' => $slug]
        );
    }

    public function porId(int $id): ?array
    {
        return $this->db->first(
            'SELECT * FROM v_coches_ficha WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    /**
     * Galería del vehículo: la fotografía principal y las tomas clasificadas
     * (exterior, interior, motor y detalles) por orden de aparición.
     *
     * @return array<int,array<string,mixed>>
     */
    public function galeria(int $cocheId, int $limite = 14): array
    {
        return $this->db->select(
            'SELECT i.id, i.ruta, i.alt, i.autor, i.licencia, i.fuente_url, i.ancho, i.alto,
                    ci.pie, ci.categoria, ci.es_principal
               FROM coches_imagenes ci
               JOIN imagenes i ON i.id = ci.imagen_id
              WHERE ci.coche_id = :c
              ORDER BY ci.es_principal DESC, ci.orden ASC, i.id ASC
              LIMIT ' . (int) $limite,
            ['c' => $cocheId]
        );
    }

    /**
     * Material en vídeo de la ficha (miniatura local, sin cargar YouTube
     * hasta que el visitante pulsa el reproductor).
     *
     * @return array<int,array<string,mixed>>
     */
    public function videos(int $cocheId): array
    {
        return $this->db->select(
            'SELECT id, plataforma, codigo, titulo, canal, duracion, miniatura, orden
               FROM v_coches_videos
              WHERE coche_id = :c
              ORDER BY orden ASC, id ASC',
            ['c' => $cocheId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function destacados(int $limite = 6): array
    {
        return $this->db->select(
            'SELECT * FROM v_coches_catalogo
              WHERE destacado = 1
              ORDER BY valoracion DESC, vistas DESC
              LIMIT ' . (int) $limite
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function masVistos(int $limite = 6): array
    {
        return $this->db->select(
            'SELECT * FROM v_coches_catalogo ORDER BY vistas DESC, valoracion DESC LIMIT ' . (int) $limite
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function novedades(int $limite = 6): array
    {
        return $this->db->select(
            'SELECT * FROM v_coches_catalogo ORDER BY anio_desde DESC, valoracion DESC LIMIT ' . (int) $limite
        );
    }

    /** @param array<int,int> $ids @return array<int,array<string,mixed>> */
    public function comparar(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }

        $marcas = implode(',', array_fill(0, count($ids), '?'));

        return $this->db->select(
            "SELECT * FROM v_coches_ficha WHERE id IN ({$marcas})
              ORDER BY FIELD(id, {$marcas})",
            [...$ids, ...$ids]
        );
    }

    // -----------------------------------------------------------------
    //  Marcas y facetas
    // -----------------------------------------------------------------

    /** @return array<int,array<string,mixed>> */
    public function marcas(): array
    {
        return $this->db->select(
            'SELECT * FROM v_marcas_conteo WHERE num_modelos > 0 ORDER BY nombre ASC'
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function porMarcaSlug(string $slug, int $limite = 24, int $offset = 0): array
    {
        return $this->db->select(
            'SELECT v.* FROM v_coches_catalogo v
               JOIN marcas m ON m.id = v.marca_id
              WHERE m.slug = :slug
              ORDER BY v.destacado DESC, v.valoracion DESC
              LIMIT ' . (int) $limite . ' OFFSET ' . (int) $offset,
            ['slug' => $slug]
        );
    }

    /** Total de vehículos publicados de una marca. */
    public function totalPorMarca(string $slug): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM v_coches_catalogo v JOIN marcas m ON m.id = v.marca_id WHERE m.slug = :slug',
            ['slug' => $slug]
        );
    }

    public function marcaPorSlug(string $slug): ?array
    {
        return $this->db->first('SELECT * FROM marcas WHERE slug = :slug LIMIT 1', ['slug' => $slug]);
    }

    /**
     * Conteos por faceta para la barra lateral de filtros.
     *
     * @return array{categorias:array<string,int>,combustibles:array<string,int>,marcas:array<int,int>}
     */
    public function facetas(): array
    {
        $base = 'FROM coches c WHERE c.estado = "publicado" AND c.borrado_en IS NULL';

        $categorias = [];
        foreach ($this->db->select("SELECT c.categoria AS k, COUNT(*) AS n {$base} GROUP BY c.categoria") as $fila) {
            $categorias[(string) $fila['k']] = (int) $fila['n'];
        }

        $combustibles = [];
        foreach ($this->db->select("SELECT c.combustible AS k, COUNT(*) AS n {$base} GROUP BY c.combustible") as $fila) {
            $combustibles[(string) $fila['k']] = (int) $fila['n'];
        }

        $marcas = [];
        foreach ($this->db->select("SELECT c.marca_id AS k, COUNT(*) AS n {$base} GROUP BY c.marca_id") as $fila) {
            $marcas[(int) $fila['k']] = (int) $fila['n'];
        }

        return compact('categorias', 'combustibles', 'marcas');
    }

    /** Rango de precios para los controles del formulario. */
    public function rangoPrecios(): array
    {
        return [
            'min' => (int) $this->db->scalar(
                'SELECT MIN(COALESCE(precio_nuevo, precio_ocasion)) FROM v_coches_catalogo'
            ),
            'max' => (int) $this->db->scalar(
                'SELECT MAX(COALESCE(precio_nuevo, precio_ocasion)) FROM v_coches_catalogo'
            ),
        ];
    }

    // -----------------------------------------------------------------
    //  Relaciones
    // -----------------------------------------------------------------

    /** @return array<int,array<string,mixed>> */
    public function relacionados(array $coche, int $limite = 4): array
    {
        return $this->db->select(
            'SELECT * FROM v_coches_catalogo
              WHERE id <> ?
                AND (marca_id = ? OR categoria = ?)
              ORDER BY CASE WHEN marca_id = ? THEN 0 ELSE 1 END, valoracion DESC
              LIMIT ' . (int) $limite,
            [
                (int) $coche['id'],
                (int) $coche['marca_id'],
                (string) $coche['categoria'],
                (int) $coche['marca_id'],
            ]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function noticiasRelacionadas(int $cocheId, int $limite = 4): array
    {
        return $this->db->select(
            'SELECT v.* FROM v_noticias_publicadas v
               JOIN noticias_coches nc ON nc.noticia_id = v.id
              WHERE nc.coche_id = :c
              ORDER BY v.publicado_at DESC
              LIMIT ' . (int) $limite,
            ['c' => $cocheId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function preciosHistoricos(int $cocheId): array
    {
        return $this->db->select(
            'SELECT periodo, precio_nuevo, precio_ocasion, demanda, unidades
               FROM precios_historicos
              WHERE coche_id = :c
              ORDER BY periodo ASC',
            ['c' => $cocheId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function valoraciones(int $cocheId, int $limite = 5): array
    {
        return $this->db->select(
            'SELECT v.puntuacion, v.comentario, v.creado_en, u.nombre
               FROM valoraciones v
               LEFT JOIN usuarios u ON u.id = v.usuario_id
              WHERE v.coche_id = :c
              ORDER BY v.creado_en DESC
              LIMIT ' . (int) $limite,
            ['c' => $cocheId]
        );
    }

    // -----------------------------------------------------------------
    //  Contadores
    // -----------------------------------------------------------------

    public function totalPublicados(): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM v_coches_catalogo');
    }

    public function totalMarcas(): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM v_marcas_conteo WHERE num_modelos > 0');
    }

    /** Suma una vista respetando el límite de una vez cada 30 min por IP. */
    public function registrarVista(int $cocheId, string $ip): void
    {
        try {
            $this->db->run('CALL sp_incrementar_vista("coches", ?, ?)', [$cocheId, $ip]);
        } catch (\Throwable $e) {
            Logger::warning('No se pudo registrar la vista: ' . $e->getMessage());
        }
    }

    // -----------------------------------------------------------------
    //  Utilidades internas
    // -----------------------------------------------------------------

    private function csv(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }

    private function int(mixed $valor): ?int
    {
        if ($valor === null || $valor === '' || !is_numeric($valor)) {
            return null;
        }

        return (int) $valor;
    }

    // =================================================================
    //  ADMINISTRACIÓN
    // =================================================================

    /**
     * Listado del panel (incluye borradores y archivados).
     *
     * @return array{items:array<int,array<string,mixed>>,total:int,paginas:int,pagina:int}
     */
    public function listadoAdmin(int $pagina = 1, int $porPagina = 20, string $estado = '', string $q = ''): array
    {
        $donde = 'c.borrado_en IS NULL';
        $parametros = [];

        if ($estado !== '' && in_array($estado, ['borrador', 'publicado', 'archivado'], true)) {
            $donde .= ' AND c.estado = :e';
            $parametros['e'] = $estado;
        }

        if (trim($q) !== '') {
            $donde .= ' AND (c.modelo LIKE :q1 OR c.version LIKE :q2 OR m.nombre LIKE :q3)';
            $like = '%' . trim($q) . '%';
            $parametros['q1'] = $like;
            $parametros['q2'] = $like;
            $parametros['q3'] = $like;
        }

        $total = (int) $this->db->scalar(
            "SELECT COUNT(*) FROM coches c JOIN marcas m ON m.id = c.marca_id WHERE {$donde}",
            $parametros
        );
        $pagina = max(1, $pagina);

        $items = $this->db->select(
            "SELECT c.id, c.modelo, c.version, c.slug, c.estado, c.destacado, c.categoria,
                    c.combustible, c.potencia_cv, c.precio_nuevo, c.precio_ocasion,
                    c.valoracion, c.vistas, c.creado_en, c.actualizado_en,
                    m.nombre AS marca, m.slug AS marca_slug,
                    (SELECT COUNT(*) FROM coches_imagenes ci WHERE ci.coche_id = c.id) AS num_imagenes
               FROM coches c
               JOIN marcas m ON m.id = c.marca_id
              WHERE {$donde}
              ORDER BY c.creado_en DESC
              LIMIT " . (int) $porPagina . ' OFFSET ' . (int) (($pagina - 1) * $porPagina),
            $parametros
        );

        return [
            'items'   => $items,
            'total'   => $total,
            'paginas' => max(1, (int) ceil($total / $porPagina)),
            'pagina'  => $pagina,
        ];
    }

    /** Ficha de administración (también borradores y archivados). */
    public function porIdAdmin(int $id): ?array
    {
        return $this->db->first(
            'SELECT c.*, m.nombre AS marca, m.slug AS marca_slug
               FROM coches c JOIN marcas m ON m.id = c.marca_id
              WHERE c.id = :id AND c.borrado_en IS NULL
              LIMIT 1',
            ['id' => $id]
        );
    }

    /** Alta de vehículo. Los derivados (slug, uuid, kW) los hace el disparador. */
    public function crear(array $d): int
    {
        return $this->db->insert('coches', $this->limpiar($d) + [
            'uuid'       => '',
            'slug'       => '',
            'creado_en'  => date('Y-m-d H:i:s'),
            'actualizado_en' => date('Y-m-d H:i:s'),
        ]);
    }

    public function actualizar(int $id, array $d): bool
    {
        $campos = $this->limpiar($d);
        if ($campos === []) {
            return false;
        }

        $campos['actualizado_en'] = date('Y-m-d H:i:s');

        return $this->db->update('coches', $campos, ['id' => $id]) > 0;
    }

    /** Borrado lógico: nunca se destruye información del catálogo. */
    public function borrar(int $id): bool
    {
        return $this->db->execute(
            'UPDATE coches SET borrado_en = NOW(), estado = "archivado" WHERE id = :id AND borrado_en IS NULL',
            ['id' => $id]
        ) > 0;
    }

    /** @param array<string,mixed> $d @return array<string,mixed> */
    private function limpiar(array $d): array
    {
        $permitidas = [
            'marca_id', 'modelo', 'version', 'categoria', 'anio_desde', 'anio_hasta',
            'estado', 'destacado', 'combustible', 'transmision', 'marchas', 'traccion',
            'plazas', 'puertas', 'potencia_cv', 'par_nm', 'cilindrada_cc', 'cilindros',
            'aceleracion_0_100', 'vel_maxima_kmh', 'consumo_l100', 'consumo_kwh100',
            'autonomia_km', 'emisiones_gkm', 'bateria_kwh', 'eficiencia',
            'maletero_litros', 'largo_mm', 'ancho_mm', 'alto_mm', 'distancia_ejes_mm',
            'peso_kg', 'precio_nuevo', 'precio_ocasion', 'resumen', 'descripcion',
            'puntos_fuertes', 'puntos_debiles', 'valoracion',
        ];

        $out = array_intersect_key($d, array_flip($permitidas));

        // Las listas viajan como JSON ya codificado
        foreach (['puntos_fuertes', 'puntos_debiles'] as $lista) {
            if (isset($out[$lista]) && is_array($out[$lista])) {
                $out[$lista] = json_encode(array_values($out[$lista]), JSON_UNESCAPED_UNICODE);
            }
        }

        return $out;
    }

    /** @return array<string,int> */
    public function contarPorEstado(): array
    {
        $out = [];
        foreach ($this->db->select(
            'SELECT estado, COUNT(*) AS n FROM coches WHERE borrado_en IS NULL GROUP BY estado'
        ) as $fila) {
            $out[(string) $fila['estado']] = (int) $fila['n'];
        }

        return $out;
    }
}
