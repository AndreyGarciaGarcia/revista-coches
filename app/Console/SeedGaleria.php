<?php

declare(strict_types=1);

namespace App\Console;

use App\Core\Database;
use App\Core\Logger;

/**
 * Biblioteca de galerías y vídeos por vehículo.
 *
 * Las fotografías se descargan con scripts/descargar-galeria.php y los
 * vídeos con scripts/descargar-videos.php; esta clase es la encargada de
 * llevar ese material a la base de datos:
 *
 *   · registrar()  → alta en `imagenes` (autor, licencia, dimensiones)
 *   · vincular()   → enlace en `coches_imagenes` con categoría y pie
 *   · videos()     → alta en `coches_videos`
 *
 * Todo es idempotente: se puede ejecutar en cualquier orden y repetir
 * sin duplicar registros.
 */
final class SeedGaleria
{
    private const MANIFIESTO = 'database/seeds/imagenes-manifest.json';
    private const VIDEOS     = 'database/seeds/videos-manifest.json';

    private const ROTULOS = [
        'exterior' => 'vista exterior',
        'interior' => 'interior',
        'motor'    => 'motor',
        'detalle'  => 'detalle',
    ];

    private readonly Database $db;

    /** @var callable(string):void|null */
    private $out;

    /**
     * @param callable(string):void|null $out
     */
    public function __construct(?callable $out = null)
    {
        $this->db = Database::get();
        $this->out = $out;
    }

    /**
     * Resuelve la clave de imagen de un vehículo («bmw-serie-3») a partir
     * de la fotografía principal ya enlazada en la ficha: esa relación
     * existe desde el propio seeder, de modo que no hace falta guardar la
     * clave en el esquema.
     *
     * @return array{id:int,slug:string,marca:string,modelo:string,etiqueta:string}|null
     */
    public function vehiculoPorClave(string $clave): ?array
    {
        $fila = $this->db->first(
            'SELECT c.id, c.slug, m.nombre AS marca, c.modelo
               FROM imagenes i
               JOIN coches_imagenes ci ON ci.imagen_id = i.id
               JOIN coches c ON c.id = ci.coche_id AND c.borrado_en IS NULL
               JOIN marcas m ON m.id = c.marca_id
              WHERE i.ruta = :r
              ORDER BY ci.es_principal DESC
              LIMIT 1',
            ['r' => 'assets/img/cars/' . $clave . '.jpg']
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
        ];
    }

    // =================================================================
    //  FOTOGRAFÍAS
    // =================================================================

    /**
     * Da de alta en `imagenes` cada archivo bajo public/assets/img/cars/<slug>/.
     *
     * @return int imágenes registradas en esta ejecución
     */
    public function registrar(): int
    {
        $base = BASE_PATH . '/public/assets/img/cars';
        $directorios = glob($base . '/*', GLOB_ONLYDIR) ?: [];

        if ($directorios === []) {
            return 0;
        }

        $manifiesto = $this->manifiesto();
        $registradas = 0;

        foreach ($directorios as $directorio) {
            $slug = basename($directorio);
            $vehiculo = $this->vehiculoPorClave($slug);
            $etiqueta = $vehiculo['etiqueta'] ?? $this->etiquetaSinFicha($slug);

            foreach (glob($directorio . '/*.{jpg,jpeg}', GLOB_BRACE) ?: [] as $fichero) {
                $nombre = basename($fichero);

                // Las miniaturas de vídeo no son fotografía de galería:
                // se guardan aparte y no se atribuyen como imagen.
                if (str_starts_with($nombre, 'video-')) {
                    continue;
                }

                $ruta = 'assets/img/cars/' . $slug . '/' . $nombre;
                $claveFoto = 'cars/' . $slug . '/' . pathinfo($nombre, PATHINFO_FILENAME);
                $meta = $manifiesto[$claveFoto] ?? [];

                $categoria = (string) ($meta['categoria'] ?? 'exterior');
                $rotulo = self::ROTULOS[$categoria] ?? 'vista';

                $hash = sha1_file($fichero);
                if ($hash === false) {
                    continue;
                }

                [$w, $h] = @getimagesize($fichero) ?: [0, 0];

                $alt = mb_substr($etiqueta . ' — ' . $rotulo, 0, 200);

                $insertada = $this->db->execute(
                    'INSERT IGNORE INTO imagenes
                        (hash, ruta, titulo, alt, autor, licencia, fuente_url, ancho, alto, peso_kb, tipo)
                     VALUES
                        (:hash, :ruta, :titulo, :alt, :autor, :licencia, :fuente, :ancho, :alto, :peso, :tipo)',
                    [
                        'hash'      => $hash,
                        'ruta'      => $ruta,
                        'titulo'    => mb_substr((string) ($meta['titulo'] ?? $nombre), 0, 180),
                        'alt'       => $alt,
                        'autor'     => mb_substr((string) ($meta['autor'] ?? 'Wikimedia Commons'), 0, 120),
                        'licencia'  => mb_substr((string) ($meta['licencia'] ?? 'CC BY-SA'), 0, 40),
                        'fuente'    => mb_substr((string) ($meta['fuente'] ?? 'https://commons.wikimedia.org/'), 0, 300),
                        'ancho'     => $w,
                        'alto'      => $h,
                        'peso'      => (int) round((int) filesize($fichero) / 1024),
                        'tipo'      => 'jpg',
                    ]
                );

                if ($insertada > 0) {
                    $registradas++;
                }
            }
        }

        if ($registradas > 0) {
            $this->log("{$registradas} fotografías nuevas en la biblioteca");
        }

        return $registradas;
    }

    /**
     * Vincula cada fotografía de galería con su vehículo, con su categoría
     * y el pie de foto (autoría y licencia) que exige la atribución.
     *
     * @return int vínculos creados en esta ejecución
     */
    public function vincular(): int
    {
        $manifiesto = $this->manifiesto();
        $vinculadas = 0;

        /** @var array<int,int> $ultimo orden ya usado por coche */
        $ultimo = [];

        ksort($manifiesto);

        foreach ($manifiesto as $clave => $meta) {
            if (($meta['seccion'] ?? '') !== 'cars') {
                continue;
            }

            // Sólo las fotos anidadas: la principal ya está vinculada
            if (!preg_match('#^cars/([^/]+)/([^/]+)$#', (string) $clave, $m)) {
                continue;
            }

            $slug = $m[1];
            $ruta = (string) ($meta['ruta'] ?? 'assets/img/cars/' . $slug . '/' . $m[2] . '.jpg');

            $vehiculo = $this->vehiculoPorClave($slug);

            if ($vehiculo === null) {
                continue;
            }

            $imagen = $this->db->first('SELECT id FROM imagenes WHERE ruta = :r LIMIT 1', ['r' => $ruta]);
            if ($imagen === null) {
                continue;
            }

            $cocheId = $vehiculo['id'];
            $imagenId = (int) $imagen['id'];

            $yaExiste = $this->db->first(
                'SELECT orden FROM coches_imagenes WHERE coche_id = :c AND imagen_id = :i LIMIT 1',
                ['c' => $cocheId, 'i' => $imagenId]
            );

            if ($yaExiste !== null) {
                $ultimo[$cocheId] = max($ultimo[$cocheId] ?? 0, (int) $yaExiste['orden']);
                continue;
            }

            if (!isset($ultimo[$cocheId])) {
                $max = $this->db->first(
                    'SELECT COALESCE(MAX(orden), 0) AS m FROM coches_imagenes WHERE coche_id = :c',
                    ['c' => $cocheId]
                );
                $ultimo[$cocheId] = (int) ($max['m'] ?? 0);
            }

            $orden = $ultimo[$cocheId] + 1;
            if ($orden > 250) {
                continue;
            }
            $ultimo[$cocheId] = $orden;

            $autor = (string) ($meta['autor'] ?? 'Wikimedia Commons');
            $licencia = (string) ($meta['licencia'] ?? 'CC BY-SA');
            $pie = mb_substr('Fotografía: ' . $autor . ' · ' . $licencia, 0, 255);

            $this->db->execute(
                'INSERT INTO coches_imagenes (coche_id, imagen_id, orden, es_principal, pie, categoria)
                 VALUES (:c, :i, :o, 0, :pie, :cat)',
                [
                    'c'    => $cocheId,
                    'i'    => $imagenId,
                    'o'    => $orden,
                    'pie'  => $pie,
                    'cat'  => (string) ($meta['categoria'] ?? 'exterior'),
                ]
            );

            $vinculadas++;
        }

        if ($vinculadas > 0) {
            $this->log("{$vinculadas} fotografías vinculadas a fichas");
        }

        return $vinculadas;
    }

    // =================================================================
    //  VÍDEOS
    // =================================================================

    /**
     * Da de alta el material en vídeo descargado por scripts/descargar-videos.php.
     *
     * @return int vídeos registrados en esta ejecución
     */
    public function videos(): int
    {
        $fichero = BASE_PATH . '/' . self::VIDEOS;

        if (!is_file($fichero)) {
            return 0;
        }

        $datos = json_decode((string) file_get_contents($fichero), true);
        if (!is_array($datos)) {
            return 0;
        }

        $insertados = 0;

        foreach ($datos as $clave => $lista) {
            if (!is_array($lista)) {
                continue;
            }

            $vehiculo = $this->vehiculoPorClave((string) $clave);

            if ($vehiculo === null) {
                continue;
            }

            foreach (array_values($lista) as $orden => $video) {
                $resultado = $this->db->execute(
                    'INSERT IGNORE INTO coches_videos
                        (coche_id, plataforma, codigo, titulo, canal, duracion, miniatura, orden)
                     VALUES (:c, :plataforma, :codigo, :titulo, :canal, :duracion, :miniatura, :orden)',
                    [
                        'c'         => $vehiculo['id'],
                        'plataforma'=> (string) ($video['plataforma'] ?? 'youtube'),
                        'codigo'    => (string) ($video['codigo'] ?? ''),
                        'titulo'    => mb_substr((string) ($video['titulo'] ?? 'Vídeo'), 0, 200),
                        'canal'     => isset($video['canal']) ? mb_substr((string) $video['canal'], 0, 120) : null,
                        'duracion'  => isset($video['duracion']) ? mb_substr((string) $video['duracion'], 0, 9) : null,
                        'miniatura' => isset($video['miniatura'])
                            ? mb_substr((string) $video['miniatura'], 0, 255)
                            : null,
                        'orden'     => $orden,
                    ]
                );

                if ($resultado > 0) {
                    $insertados++;
                }
            }
        }

        if ($insertados > 0) {
            $this->log("{$insertados} vídeos nuevos");
        }

        return $insertados;
    }

    // =================================================================
    //  AUXILIARES
    // =================================================================

    /** @return array<string,array<string,mixed>> */
    private function manifiesto(): array
    {
        $fichero = BASE_PATH . '/' . self::MANIFIESTO;

        if (!is_file($fichero)) {
            return [];
        }

        return json_decode((string) file_get_contents($fichero), true) ?: [];
    }

    /**
     * Nombre de respaldo cuando el vehículo todavía no está en la base de
     * datos (instalación sin sembrar): la consulta del manifiesto, que ya
     * describe el modelo.
     */
    private function etiquetaSinFicha(string $clave): string
    {
        try {
            /** @var array<string,array<string,string>> $mapa */
            $mapa = require BASE_PATH . '/database/seeds/imagenes.php';
        } catch (\Throwable $e) {
            Logger::warning('No se pudo leer el mapa de imágenes: ' . $e->getMessage());
            $mapa = [];
        }

        return (string) ($mapa['cars'][$clave] ?? str_replace('-', ' ', $clave));
    }

    private function log(string $mensaje): void
    {
        if ($this->out) {
            ($this->out)($mensaje);
        }
    }
}
