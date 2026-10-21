<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Galería fotográfica y material en vídeo de cada ficha de vehículo.
 *
 * Es la contraparte de administración de `CocheRepository::galeria()`
 * y `::videos()`: mismos criterios de orden, pero sin límite de filas
 * y sin el filtro de publicación (un borrador también se puede maquetar).
 *
 * Las operaciones devuelven siempre si han tenido éxito y, cuando
 * procede, la ruta del fichero que hay que borrar del disco: el repositorio
 * toca la base de datos, el controlador toca el sistema de archivos.
 */
final class GaleriaRepository
{
    private const CATEGORIAS = ['exterior', 'interior', 'motor', 'detalle'];

    private Database $db;

    public function __construct()
    {
        $this->db = Database::get();
    }

    // -----------------------------------------------------------------
    //  Vehículo y carpeta de trabajo
    // -----------------------------------------------------------------

    /**
     * Ficha mínima + carpeta donde viven sus imágenes.
     *
     * La carpeta no es el slug del coche sino la clave de imagen que ya
     * usa la biblioteca («bmw-serie-3»): se deduce de la fotografía
     * principal, con el slug como red de seguridad para fichas nuevas.
     *
     * @return array{id:int,slug:string,marca:string,modelo:string,etiqueta:string,carpeta:string}|null
     */
    public function vehiculo(int $cocheId): ?array
    {
        $fila = $this->db->first(
            'SELECT c.id, c.slug, m.nombre AS marca, c.modelo
               FROM coches c
               JOIN marcas m ON m.id = c.marca_id
              WHERE c.id = :id AND c.borrado_en IS NULL',
            ['id' => $cocheId]
        );

        if ($fila === null) {
            return null;
        }

        $marca = trim((string) $fila['marca']);
        $modelo = trim((string) $fila['modelo']);

        return [
            'id'       => (int) $fila['id'],
            'slug'     => (string) $fila['slug'],
            'marca'    => $marca,
            'modelo'   => $modelo,
            'etiqueta' => trim($marca . ' ' . $modelo),
            'carpeta'  => $this->carpeta($cocheId, (string) $fila['slug']),
        ];
    }

    /** Directorio relativo («cars/bmw-serie-3») donde se guardan los ficheros. */
    private function carpeta(int $cocheId, string $slug): string
    {
        $rutas = $this->db->select(
            'SELECT i.ruta
               FROM coches_imagenes ci
               JOIN imagenes i ON i.id = ci.imagen_id
              WHERE ci.coche_id = :c
              ORDER BY ci.es_principal DESC, ci.orden ASC
              LIMIT 3',
            ['c' => $cocheId]
        );

        foreach ($rutas as $fila) {
            $ruta = (string) $fila['ruta'];
            $prefijo = 'assets/img/cars/';

            if (!str_starts_with($ruta, $prefijo)) {
                continue;
            }

            $partes = explode('/', substr($ruta, strlen($prefijo)));
            $clave = $partes[0];

            // Un fichero plano (cars/vw-golf.jpg) vive en la carpeta que
            // lleva su nombre sin extensión (cars/vw-golf/)
            if (count($partes) === 1) {
                $clave = (string) preg_replace('/\.(jpe?g|png|webp|avif|gif|svg)$/i', '', $clave);
            }

            if (preg_match('/^[A-Za-z0-9_-][A-Za-z0-9._-]*$/', $clave) === 1
                && !str_contains($clave, '..')) {
                return 'cars/' . $clave;
            }
        }

        return 'cars/' . preg_replace('/[^A-Za-z0-9._-]/', '-', $slug);
    }

    // -----------------------------------------------------------------
    //  Fotografías
    // -----------------------------------------------------------------

    /** @return array<int,array<string,mixed>> lista completa, igual que la ficha pública */
    public function fotos(int $cocheId): array
    {
        return $this->db->select(
            'SELECT i.id, i.ruta, i.alt, i.titulo, i.autor, i.licencia, i.ancho, i.alto,
                    i.peso_kb, i.hash, ci.orden, ci.categoria, ci.es_principal
               FROM coches_imagenes ci
               JOIN imagenes i ON i.id = ci.imagen_id
              WHERE ci.coche_id = :c
              ORDER BY ci.es_principal DESC, ci.orden ASC, i.id ASC',
            ['c' => $cocheId]
        );
    }

    /**
     * Da de alta un fichero en la biblioteca o devuelve el que ya existe
     * (misma huella SHA‑1 o misma ruta): nunca se guardan dos copias.
     *
     * @return int identificador de `imagenes`
     */
    public function registrarImagen(
        string $ruta,
        string $alt,
        string $hash,
        int $ancho,
        int $alto,
        int $pesoKb
    ): int {
        $existe = $this->db->scalar(
            'SELECT id FROM imagenes WHERE hash = :h OR ruta = :r LIMIT 1',
            ['h' => $hash, 'r' => $ruta]
        );

        if ($existe !== null) {
            return (int) $existe;
        }

        return $this->db->insert('imagenes', [
            'hash'       => $hash,
            'ruta'       => $ruta,
            'titulo'     => mb_substr($alt, 0, 180),
            'alt'        => mb_substr($alt, 0, 200),
            'autor'      => 'Redacción MotorPress',
            'licencia'   => 'Todos los derechos reservados',
            'fuente_url' => null,
            'ancho'      => $ancho,
            'alto'       => $alto,
            'peso_kb'    => $pesoKb,
            'tipo'       => 'jpg',
        ]);
    }

    /**
     * Devuelve una fotografía ya registrada por su huella, si existe.
     *
     * @return array{id:int,ruta:string,alt:string}|null
     */
    public function imagenPorHash(string $hash): ?array
    {
        $fila = $this->db->first(
            'SELECT id, ruta, alt FROM imagenes WHERE hash = :h LIMIT 1',
            ['h' => $hash]
        );

        if ($fila === null) {
            return null;
        }

        return [
            'id'   => (int) $fila['id'],
            'ruta' => (string) $fila['ruta'],
            'alt'  => (string) $fila['alt'],
        ];
    }

    /**
     * Enlaza una fotografía con el vehículo: si era su primera, pasa a
     * ser la principal; si ya está enlazada no hace nada.
     */
    public function agregarFoto(int $cocheId, int $imagenId, string $categoria): bool
    {
        $yaEnlazada = $this->db->scalar(
            'SELECT imagen_id FROM coches_imagenes WHERE coche_id = :c AND imagen_id = :i',
            ['c' => $cocheId, 'i' => $imagenId]
        );

        if ($yaEnlazada !== null) {
            return false;
        }

        $principal = (int) $this->db->scalar(
            'SELECT COUNT(*) FROM coches_imagenes WHERE coche_id = :c AND es_principal = 1',
            ['c' => $cocheId]
        );

        $orden = 1 + (int) $this->db->scalar(
            'SELECT COALESCE(MAX(orden), 0) FROM coches_imagenes WHERE coche_id = :c',
            ['c' => $cocheId]
        );

        $this->db->insert('coches_imagenes', [
            'coche_id'     => $cocheId,
            'imagen_id'    => $imagenId,
            'orden'        => min(255, $orden),
            'categoria'    => $this->categoriaValida($categoria),
            'es_principal' => $principal === 0 ? 1 : 0,
            'pie'          => null,
        ]);

        return true;
    }

    public function categoria(int $cocheId, int $imagenId, string $categoria): bool
    {
        return $this->db->execute(
            'UPDATE coches_imagenes SET categoria = :cat
              WHERE coche_id = :c AND imagen_id = :i',
            ['cat' => $this->categoriaValida($categoria), 'c' => $cocheId, 'i' => $imagenId]
        ) > 0;
    }

    /**
     * Deja la numeración sin huecos ni empates: la principal en 0 y el
     * resto en 1…n según el orden en que se ven en la ficha.
     */
    public function normalizarOrden(int $cocheId): void
    {
        $filas = $this->fotos($cocheId);
        $resto = array_values(array_filter($filas, static fn (array $f): bool => empty($f['es_principal'])));

        $this->renumerar($cocheId, $resto, 1);
    }

    /**
     * Intercambia la posición de una fotografía con su vecina dentro del
     * orden visible (la principal ocupa siempre el primer hueco).
     */
    public function reordenarFotos(int $cocheId, int $imagenId, int $delta): bool
    {
        $filas = $this->fotos($cocheId);

        /** @var array<int,array<string,mixed>> $resto */
        $resto = array_values(array_filter($filas, static fn (array $f): bool => empty($f['es_principal'])));
        $pos = -1;

        foreach ($resto as $i => $fila) {
            if ((int) $fila['id'] === $imagenId) {
                $pos = $i;
                break;
            }
        }

        if ($pos < 0) {
            return false;
        }

        $destino = $pos + ($delta < 0 ? -1 : 1);

        if ($destino < 0 || $destino >= count($resto)) {
            return false;
        }

        [$resto[$pos], $resto[$destino]] = [$resto[$destino], $resto[$pos]];

        return $this->renumerar($cocheId, $resto, 1);
    }

    /**
     * Quita la fotografía de la galería y, si ya nadie la usa, la borra
     * también de la biblioteca (el controlador borrará el fichero).
     *
     * @return array{ok:bool,error:?string,ruta:?string}
     */
    public function borrarFoto(int $cocheId, int $imagenId): array
    {
        $fila = $this->db->first(
            'SELECT i.id, i.ruta, ci.es_principal
               FROM coches_imagenes ci
               JOIN imagenes i ON i.id = ci.imagen_id
              WHERE ci.coche_id = :c AND ci.imagen_id = :i',
            ['c' => $cocheId, 'i' => $imagenId]
        );

        if ($fila === null) {
            return ['ok' => false, 'error' => 'La fotografía no está en la galería de este vehículo.', 'ruta' => null];
        }

        if (!empty($fila['es_principal'])) {
            return [
                'ok'    => false,
                'error' => 'No se puede eliminar la foto principal: antes haz principal otra fotografía.',
                'ruta'  => null,
            ];
        }

        // ¿La siguen usando otros contenidos? Se pregunta antes de tocar nada
        $otros = (int) $this->db->scalar(
            'SELECT (SELECT COUNT(*) FROM coches_imagenes WHERE imagen_id = :a AND coche_id <> :c)
                  + (SELECT COUNT(*) FROM noticias WHERE imagen_id = :b)',
            ['a' => $imagenId, 'c' => $cocheId, 'b' => $imagenId]
        );

        $this->db->execute(
            'DELETE FROM coches_imagenes WHERE coche_id = :c AND imagen_id = :i',
            ['c' => $cocheId, 'i' => $imagenId]
        );

        if ($otros > 0) {
            // Otro contenido sigue usando la misma fotografía
            return ['ok' => true, 'error' => null, 'ruta' => null];
        }

        $this->db->execute('DELETE FROM imagenes WHERE id = :i', ['i' => $imagenId]);

        return ['ok' => true, 'error' => null, 'ruta' => (string) $fila['ruta']];
    }

    // -----------------------------------------------------------------
    //  Vídeos
    // -----------------------------------------------------------------

    /** @return array<int,array<string,mixed>> sin el filtro de publicación de la vista */
    public function videos(int $cocheId): array
    {
        return $this->db->select(
            'SELECT id, plataforma, codigo, titulo, canal, duracion, miniatura, orden, creado_en
               FROM coches_videos
              WHERE coche_id = :c
              ORDER BY orden ASC, id ASC',
            ['c' => $cocheId]
        );
    }

    /** @return bool false si ese vídeo ya estaba en la ficha (clave única) */
    public function agregarVideo(
        int $cocheId,
        string $codigo,
        string $titulo,
        string $canal,
        ?string $duracion,
        ?string $miniatura
    ): bool {
        $orden = (int) $this->db->scalar(
            'SELECT COALESCE(MAX(orden), -1) + 1 FROM coches_videos WHERE coche_id = :c',
            ['c' => $cocheId]
        );

        return $this->db->execute(
            'INSERT IGNORE INTO coches_videos
                (coche_id, plataforma, codigo, titulo, canal, duracion, miniatura, orden)
             VALUES (:c, :plataforma, :codigo, :titulo, :canal, :duracion, :miniatura, :orden)',
            [
                'c'          => $cocheId,
                'plataforma' => 'youtube',
                'codigo'     => $codigo,
                'titulo'     => mb_substr($titulo, 0, 200),
                'canal'      => $canal !== '' ? mb_substr($canal, 0, 120) : null,
                'duracion'   => $duracion,
                'miniatura'  => $miniatura !== '' ? mb_substr($miniatura, 0, 255) : null,
                'orden'      => max(0, $orden),
            ]
        ) > 0;
    }

    public function reordenarVideos(int $cocheId, int $videoId, int $delta): bool
    {
        $filas = $this->videos($cocheId);
        $pos = -1;

        foreach ($filas as $i => $fila) {
            if ((int) $fila['id'] === $videoId) {
                $pos = $i;
                break;
            }
        }

        if ($pos < 0) {
            return false;
        }

        $destino = $pos + ($delta < 0 ? -1 : 1);

        if ($destino < 0 || $destino >= count($filas)) {
            return false;
        }

        [$filas[$pos], $filas[$destino]] = [$filas[$destino], $filas[$pos]];

        foreach ($filas as $i => $fila) {
            $this->db->execute(
                'UPDATE coches_videos SET orden = :o WHERE id = :id AND coche_id = :c',
                ['o' => $i, 'id' => (int) $fila['id'], 'c' => $cocheId]
            );
        }

        return true;
    }

    /**
     * @return array{ok:bool,error:?string,ruta:?string} `ruta` del fichero local a borrar
     */
    public function borrarVideo(int $cocheId, int $videoId): array
    {
        $fila = $this->db->first(
            'SELECT id, plataforma, codigo, miniatura
               FROM coches_videos
              WHERE coche_id = :c AND id = :id',
            ['c' => $cocheId, 'id' => $videoId]
        );

        if ($fila === null) {
            return ['ok' => false, 'error' => 'El vídeo no está en esta ficha.', 'ruta' => null];
        }

        $this->db->execute(
            'DELETE FROM coches_videos WHERE id = :id AND coche_id = :c',
            ['id' => $videoId, 'c' => $cocheId]
        );

        // El resto vuelve a numerarse para que no queden huecos
        foreach ($this->videos($cocheId) as $i => $resto) {
            $this->db->execute(
                'UPDATE coches_videos SET orden = :o WHERE id = :id AND coche_id = :c',
                ['o' => $i, 'id' => (int) $resto['id'], 'c' => $cocheId]
            );
        }

        // Sólo se borra el fichero local: la miniatura de YouTube no es nuestra
        $ruta = (string) ($fila['miniatura'] ?? '');

        if (str_starts_with($ruta, 'assets/img/cars/')) {
            return ['ok' => true, 'error' => null, 'ruta' => $ruta];
        }

        return ['ok' => true, 'error' => null, 'ruta' => null];
    }

    // -----------------------------------------------------------------
    //  Auxiliares
    // -----------------------------------------------------------------

    private function categoriaValida(string $categoria): string
    {
        return in_array($categoria, self::CATEGORIAS, true) ? $categoria : 'exterior';
    }

    /**
     * Vuelve a numerar 1..n la lista recibida en su orden actual.
     *
     * @param array<int,array<string,mixed>> $filas
     */
    private function renumerar(int $cocheId, array $filas, int $desde): bool
    {
        $ok = true;

        foreach ($filas as $i => $fila) {
            $ok = $this->db->execute(
                'UPDATE coches_imagenes SET orden = :o WHERE coche_id = :c AND imagen_id = :i',
                ['o' => $desde + $i, 'c' => $cocheId, 'i' => (int) $fila['id']]
            ) > 0 && $ok;
        }

        return $ok;
    }
}
