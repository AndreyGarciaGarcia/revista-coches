<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Str;

/**
 * Acceso a datos editoriales: artículos, categorías, etiquetas y
 * comentarios. Las consultas de listado se apoyan en las vistas
 * v_noticias_publicadas y v_noticias_portada.
 */
final class NoticiaRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::get();
    }

    // -----------------------------------------------------------------
    //  Listados
    // -----------------------------------------------------------------

    /** Destacadas + fijadas para la portada. @return array<int,array<string,mixed>> */
    public function portada(int $limite = 6): array
    {
        return $this->db->select(
            'SELECT * FROM v_noticias_portada
              ORDER BY fijada DESC, destacada DESC, publicado_at DESC
              LIMIT ' . (int) $limite
        );
    }

    /**
     * Listado paginado con filtros.
     *
     * @return array{items:array<int,array<string,mixed>>,total:int,paginas:int,pagina:int}
     */
    public function listado(
        int $pagina = 1,
        int $porPagina = 9,
        ?string $categoria = null,
        ?string $texto = null,
        ?string $etiqueta = null,
        string $orden = 'recientes'
    ): array {
        $donde = ['1 = 1'];
        $parametros = [];

        if ($categoria !== null && $categoria !== '') {
            $donde[] = 'categoria_slug = :cat';
            $parametros['cat'] = $categoria;
        }

        if ($texto !== null && trim($texto) !== '') {
            $donde[] = '(titulo LIKE :q1 OR resumen LIKE :q2)';
            $parametros['q1'] = '%' . trim($texto) . '%';
            $parametros['q2'] = '%' . trim($texto) . '%';
        }

        $union = '';
        if ($etiqueta !== null && $etiqueta !== '') {
            $union = ' JOIN noticias_etiquetas ne ON ne.noticia_id = v.id
                      JOIN etiquetas e ON e.id = ne.etiqueta_id AND e.slug = :tag';
            $parametros['tag'] = $etiqueta;
        }

        $ordenSql = match ($orden) {
            'vistas'    => 'v.vistas DESC',
            'comentarios' => 'v.num_comentarios DESC',
            default     => 'v.publicado_at DESC',
        };

        $where = implode(' AND ', $donde);
        $base = 'FROM v_noticias_publicadas v' . $union . ' WHERE ' . $where;

        $total = (int) $this->db->scalar('SELECT COUNT(DISTINCT v.id) ' . $base, $parametros);
        $pagina = max(1, $pagina);

        $items = $this->db->select(
            'SELECT DISTINCT v.* ' . $base . " ORDER BY {$ordenSql} LIMIT "
            . (int) $porPagina . ' OFFSET ' . (int) (($pagina - 1) * $porPagina),
            $parametros
        );

        return [
            'items'   => $items,
            'total'   => $total,
            'paginas' => max(1, (int) ceil($total / $porPagina)),
            'pagina'  => $pagina,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function masLeidas(int $limite = 5): array
    {
        return $this->db->select(
            'SELECT * FROM v_noticias_publicadas ORDER BY vistas DESC, publicado_at DESC LIMIT ' . (int) $limite
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function recientes(int $limite = 5): array
    {
        return $this->db->select(
            'SELECT * FROM v_noticias_publicadas ORDER BY publicado_at DESC LIMIT ' . (int) $limite
        );
    }

    // -----------------------------------------------------------------
    //  Ficha
    // -----------------------------------------------------------------

    public function porSlug(string $slug): ?array
    {
        return $this->db->first(
            'SELECT n.id, n.uuid, n.titulo, n.slug, n.resumen, n.cuerpo, n.pie_foto,
                    n.destacada, n.fijada, n.permitir_comentarios, n.vistas,
                    n.tiempo_lectura, n.valoracion, n.publicado_at, n.actualizado_en,
                    c.id AS categoria_id, c.nombre AS categoria, c.slug AS categoria_slug,
                    c.color AS categoria_color,
                    u.id AS autor_id, u.nombre AS autor, u.bio AS autor_bio, u.avatar AS autor_avatar,
                    i.ruta AS imagen, i.alt AS imagen_alt, i.autor AS imagen_autor,
                    i.licencia AS imagen_licencia, i.fuente_url AS imagen_fuente,
                    (SELECT COUNT(*) FROM comentarios co
                      WHERE co.noticia_id = n.id AND co.estado = "aprobado") AS num_comentarios
               FROM noticias n
               LEFT JOIN categorias c ON c.id = n.categoria_id
               LEFT JOIN usuarios u ON u.id = n.autor_id
               LEFT JOIN imagenes i ON i.id = n.imagen_id
              WHERE n.slug = :slug AND n.estado = "publicado" AND n.borrado_en IS NULL
              LIMIT 1',
            ['slug' => $slug]
        );
    }

    /** Artículo publicado por su id (consumido por la API). */
    public function porIdPublicado(int $id): ?array
    {
        return $this->db->first(
            'SELECT n.id, n.uuid, n.titulo, n.slug, n.resumen, n.cuerpo, n.vistas,
                    n.tiempo_lectura, n.valoracion, n.publicado_at,
                    c.nombre AS categoria, c.slug AS categoria_slug,
                    u.nombre AS autor,
                    i.ruta AS imagen
               FROM noticias n
               LEFT JOIN categorias c ON c.id = n.categoria_id
               LEFT JOIN usuarios u ON u.id = n.autor_id
               LEFT JOIN imagenes i ON i.id = n.imagen_id
              WHERE n.id = :id AND n.estado = "publicado" AND n.borrado_en IS NULL',
            ['id' => $id]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function relacionadas(array $noticia, int $limite = 3): array
    {
        $mismasCat = $this->db->select(
            'SELECT * FROM v_noticias_publicadas
              WHERE id <> :id AND categoria_id = :cat
              ORDER BY publicado_at DESC LIMIT ' . (int) $limite,
            ['id' => (int) $noticia['id'], 'cat' => (int) $noticia['categoria_id']]
        );

        if (count($mismasCat) >= $limite) {
            return $mismasCat;
        }

        $faltan = $limite - count($mismasCat);
        $usados = array_column($mismasCat, 'id');

        $extras = $this->db->select(
            'SELECT * FROM v_noticias_publicadas
              WHERE id <> :id AND categoria_id <> :cat2' .
            ($usados !== [] ? ' AND id NOT IN (' . implode(',', array_map('intval', $usados)) . ')' : '') .
            ' ORDER BY vistas DESC LIMIT ' . (int) $faltan,
            ['id' => (int) $noticia['id'], 'cat2' => (int) $noticia['categoria_id']]
        );

        return array_merge($mismasCat, $extras);
    }

    // -----------------------------------------------------------------
    //  Taxonomía
    // -----------------------------------------------------------------

    /** @return array<int,array<string,mixed>> */
    public function categorias(): array
    {
        return $this->db->select(
            'SELECT c.*, COUNT(n.id) AS num_noticias
               FROM categorias c
               LEFT JOIN noticias n
                      ON n.categoria_id = c.id AND n.estado = "publicado" AND n.borrado_en IS NULL
              WHERE c.activa = 1
              GROUP BY c.id
              ORDER BY c.orden ASC, c.nombre ASC'
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function etiquetas(?int $noticiaId = null, int $limite = 30): array
    {
        if ($noticiaId !== null) {
            return $this->db->select(
                'SELECT e.* FROM etiquetas e
                   JOIN noticias_etiquetas ne ON ne.etiqueta_id = e.id
                  WHERE ne.noticia_id = :n ORDER BY e.nombre ASC',
                ['n' => $noticiaId]
            );
        }

        return $this->db->select(
            'SELECT e.*, COUNT(ne.noticia_id) AS num
               FROM etiquetas e
               JOIN noticias_etiquetas ne ON ne.etiqueta_id = e.id
               JOIN noticias n ON n.id = ne.noticia_id AND n.estado = "publicado" AND n.borrado_en IS NULL
              GROUP BY e.id
              ORDER BY num DESC, e.nombre ASC
              LIMIT ' . (int) $limite
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function etiquetasPopulares(int $limite = 18): array
    {
        return $this->etiquetas(null, $limite);
    }

    // -----------------------------------------------------------------
    //  Comentarios
    // -----------------------------------------------------------------

    /** @return array<int,array<string,mixed>> */
    public function comentarios(int $noticiaId, string $estado = 'aprobado'): array
    {
        return $this->db->select(
            'SELECT c.id, c.parent_id, c.autor, c.contenido, c.creado_en, c.estado,
                    u.avatar, u.id AS usuario_id
               FROM comentarios c
               LEFT JOIN usuarios u ON u.id = c.usuario_id
              WHERE c.noticia_id = :n AND c.estado = :e
              ORDER BY c.creado_en ASC',
            ['n' => $noticiaId, 'e' => $estado]
        );
    }

    /** Registra un comentario. Devuelve el id, 0 si ya existía o -1 si falló. */
    public function agregarComentario(array $datos): int
    {
        $existe = $this->db->first(
            'SELECT id FROM comentarios
              WHERE noticia_id = :n AND autor = :a AND contenido = :c
              LIMIT 1',
            [
                'n' => (int) $datos['noticia_id'],
                'a' => (string) $datos['autor'],
                'c' => (string) $datos['contenido'],
            ]
        );

        if ($existe !== null) {
            return 0;
        }

        try {
            return $this->db->insert('comentarios', [
                'noticia_id' => (int) $datos['noticia_id'],
                'usuario_id' => $datos['usuario_id'] ?? null,
                'parent_id'  => $datos['parent_id'] ?? null,
                'autor'      => mb_substr((string) $datos['autor'], 0, 80),
                'email'      => $datos['email'] ?? null,
                'contenido'  => (string) $datos['contenido'],
                'ip'         => $datos['ip'] ?? null,
                'user_agent' => $datos['user_agent'] ?? null,
                'estado'     => $datos['estado'] ?? 'pendiente',
                'moderado_en' => ($datos['estado'] ?? 'pendiente') === 'aprobado' ? date('Y-m-d H:i:s') : null,
            ]);
        } catch (\Throwable $e) {
            Logger::error('No se pudo guardar el comentario: ' . $e->getMessage());

            // -1 = error de guardado (distinto de «ya existía»)
            return -1;
        }
    }

    // -----------------------------------------------------------------
    //  Contadores
    // -----------------------------------------------------------------

    public function totalPublicadas(): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM v_noticias_publicadas');
    }

    public function registrarVista(int $noticiaId, string $ip): void
    {
        try {
            $this->db->run('CALL sp_incrementar_vista("noticias", ?, ?)', [$noticiaId, $ip]);
        } catch (\Throwable $e) {
            Logger::warning('No se pudo registrar la vista: ' . $e->getMessage());
        }
    }

    /** Slugs de los últimos artículos (sitemap y feed). */
    /** @return array<int,string> */
    public function slugs(int $limite = 100): array
    {
        return array_column(
            $this->db->select('SELECT slug FROM v_noticias_publicadas ORDER BY publicado_at DESC LIMIT ' . (int) $limite),
            'slug'
        );
    }

    public function slugUnico(string $titulo, ?int $excluirId = null): string
    {
        $base = Str::slug($titulo);
        $slug = $base;
        $i = 2;

        while (true) {
            $sql = 'SELECT id FROM noticias WHERE slug = :s' . ($excluirId !== null ? ' AND id <> :i' : '');
            $params = ['s' => $slug];
            if ($excluirId !== null) {
                $params['i'] = $excluirId;
            }

            if ($this->db->first($sql, $params) === null) {
                return $slug;
            }

            $slug = $base . '-' . $i++;
        }
    }

    // =================================================================
    //  ADMINISTRACIÓN
    // =================================================================

    /**
     * Listado del panel (incluye borradores y archivadas).
     *
     * @return array{items:array<int,array<string,mixed>>,total:int,paginas:int,pagina:int}
     */
    public function listadoAdmin(int $pagina = 1, int $porPagina = 20, string $estado = "", string $q = ""): array
    {
        $donde = "n.borrado_en IS NULL";
        $parametros = [];

        if ($estado !== "" && in_array($estado, ["borrador", "revisado", "publicado", "archivado"], true)) {
            $donde .= " AND n.estado = :e";
            $parametros["e"] = $estado;
        }

        if (trim($q) !== "") {
            $donde .= " AND (n.titulo LIKE :q1 OR n.resumen LIKE :q2)";
            $like = "%" . trim($q) . "%";
            $parametros["q1"] = $like;
            $parametros["q2"] = $like;
        }

        $total = (int) $this->db->scalar("SELECT COUNT(*) FROM noticias n WHERE {$donde}", $parametros);
        $pagina = max(1, $pagina);

        $items = $this->db->select(
            "SELECT n.id, n.titulo, n.slug, n.estado, n.destacada, n.fijada, n.vistas,
                    n.publicado_at, n.creado_en, n.actualizado_en, n.tiempo_lectura,
                    c.nombre AS categoria, u.nombre AS autor,
                    (SELECT COUNT(*) FROM comentarios co WHERE co.noticia_id = n.id) AS num_comentarios
               FROM noticias n
               LEFT JOIN categorias c ON c.id = n.categoria_id
               LEFT JOIN usuarios u ON u.id = n.autor_id
              WHERE {$donde}
              ORDER BY n.creado_en DESC
              LIMIT " . (int) $porPagina . " OFFSET " . (int) (($pagina - 1) * $porPagina),
            $parametros
        );

        return [
            "items"   => $items,
            "total"   => $total,
            "paginas" => max(1, (int) ceil($total / $porPagina)),
            "pagina"  => $pagina,
        ];
    }

    /** Ficha de administración: también borradores y archivadas. */
    public function porIdAdmin(int $id): ?array
    {
        return $this->db->first(
            "SELECT n.*, c.slug AS categoria_slug, i.ruta AS imagen
               FROM noticias n
               LEFT JOIN categorias c ON c.id = n.categoria_id
               LEFT JOIN imagenes i ON i.id = n.imagen_id
              WHERE n.id = :id AND n.borrado_en IS NULL
              LIMIT 1",
            ["id" => $id]
        );
    }

    /** @param array<string,mixed> $d */
    public function crear(array $d): int
    {
        return $this->db->insert("noticias", [
            "uuid"                 => "",
            "titulo"               => (string) $d["titulo"],
            "slug"                 => "",
            "resumen"              => $d["resumen"] ?? null,
            "cuerpo"               => (string) $d["cuerpo"],
            "categoria_id"         => $d["categoria_id"] ?? null,
            "autor_id"             => $d["autor_id"] ?? null,
            "imagen_id"            => $d["imagen_id"] ?? null,
            "pie_foto"             => $d["pie_foto"] ?? null,
            "estado"               => $d["estado"] ?? "borrador",
            "destacada"            => !empty($d["destacada"]) ? 1 : 0,
            "fijada"               => !empty($d["fijada"]) ? 1 : 0,
            "permitir_comentarios" => isset($d["permitir_comentarios"]) ? (int) $d["permitir_comentarios"] : 1,
            "publicado_at"         => ($d["estado"] ?? "borrador") === "publicado" ? date("Y-m-d H:i:s") : null,
        ]);
    }

    /** @param array<string,mixed> $d */
    public function actualizar(int $id, array $d): bool
    {
        $campos = array_intersect_key($d, array_flip([
            "titulo", "slug", "resumen", "cuerpo", "categoria_id", "imagen_id",
            "pie_foto", "estado", "destacada", "fijada", "permitir_comentarios", "publicado_at",
        ]));

        if ($campos === []) {
            return false;
        }

        $campos["actualizado_en"] = date("Y-m-d H:i:s");

        return $this->db->update("noticias", $campos, ["id" => $id]) > 0;
    }

    /** Borrado lógico: el contenido se conserva para auditoría. */
    public function borrar(int $id): bool
    {
        return $this->db->execute(
            "UPDATE noticias SET borrado_en = NOW(), estado = \"archivado\" WHERE id = :id AND borrado_en IS NULL",
            ["id" => $id]
        ) > 0;
    }

    /** Publica mediante el procedimiento (garantiza cuerpo suficiente). */
    public function publicar(int $id): bool
    {
        return $this->llamar("sp_publicar_noticia", $id);
    }

    public function archivar(int $id): bool
    {
        return $this->llamar("sp_archivar_noticia", $id);
    }

    /** Llama a un procedimiento con un parámetro IN y uno OUT. */
    private function llamar(string $procedimiento, int $id): bool
    {
        try {
            $stmt = $this->db->pdo()->prepare("CALL {$procedimiento}(?, @mp_resultado)");
            $stmt->bindValue(1, $id, \PDO::PARAM_INT);
            $stmt->execute();
            $stmt->closeCursor();

            return (int) $this->db->scalar("SELECT @mp_resultado") === 1;
        } catch (\Throwable $e) {
            Logger::error("No se pudo ejecutar {$procedimiento}: " . $e->getMessage());
            return false;
        }
    }

    /** @return array<string,int> */
    public function contarPorEstado(): array
    {
        $out = [];
        foreach ($this->db->select("SELECT estado, COUNT(*) AS n FROM noticias WHERE borrado_en IS NULL GROUP BY estado") as $fila) {
            $out[(string) $fila["estado"]] = (int) $fila["n"];
        }
        return $out;
    }
}