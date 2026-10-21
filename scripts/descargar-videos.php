<?php

/**
 * =====================================================================
 *  Vídeos por vehículo (YouTube)
 * =====================================================================
 *
 *   php scripts/descargar-videos.php                   todos los coches
 *   php scripts/descargar-videos.php --solo=vw-golf     uno solo
 *   php scripts/descargar-videos.php --por-coche=2      hasta 2 por coche
 *   php scripts/descargar-videos.php --dry              no escribe nada
 *
 * Para cada ficha busca material en vídeo del modelo, valida que el
 * título corresponda realmente al coche, comprueba que no se repita en
 * todo el sitio y guarda:
 *
 *   · la miniatura en public/assets/img/cars/<slug>/video-NN.jpg
 *   · los metadatos en database/seeds/videos-manifest.json
 *   · el registro en la tabla coches_videos (vía SeedGaleria)
 *
 * El reproductor se carga en diferido desde la ficha: la página sólo
 * descarga el iframe cuando se pulsa el vídeo.
 * =====================================================================
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Console\SeedGaleria;
use App\Core\Database;
use App\Core\Logger;

const VIDEOS_MANIFEST = 'database/seeds/videos-manifest.json';

$opciones = cli_args($argv, ['solo::', 'por-coche::', 'dry', 'limpiar', 'force', 'help']);

if (isset($opciones['help'])) {
    echo <<<TXT
MotorPress · Vídeos de los vehículos

  php scripts/descargar-videos.php                busca vídeo para todo
  php scripts/descargar-videos.php --solo=vw-golf sólo ese vehículo
  php scripts/descargar-videos.php --por-coche=2  hasta 2 vídeos por coche
  php scripts/descargar-videos.php --limpiar      borra manifiesto, miniaturas y BD
  php scripts/descargar-videos.php --dry          no escribe nada

TXT;
    exit(0);
}

$solo     = isset($opciones['solo']) ? (string) $opciones['solo'] : null;
$porCoche = max(1, (int) ($opciones['por-coche'] ?? 1));
$dry      = isset($opciones['dry']);
$force    = isset($opciones['force']);

$manifestFile = BASE_PATH . '/' . VIDEOS_MANIFEST;

// --limpiar: se parte de cero (miniaturas locales, manifiesto y tabla)
// Sólo se reinicia lo que descargó este script: los vídeos dados de alta
// desde el panel se conservan mientras estén fuera del manifiesto.
if (isset($opciones['limpiar'])) {
    $borrados = 0;
    $filas = 0;

    $previo = is_file($manifestFile)
        ? ((json_decode((string) file_get_contents($manifestFile), true)) ?: [])
        : [];

    $codigos = [];
    foreach ($previo as $listaPrevia) {
        if (!is_array($listaPrevia)) {
            continue;
        }
        foreach ($listaPrevia as $video) {
            if (is_array($video) && !empty($video['codigo'])) {
                $codigos[(string) $video['codigo']] = true;
            }
        }
    }
    $codigos = array_keys($codigos);

    if ($codigos === []) {
        // Sin manifiesto no hay nada con qué distinguir: reinicio total
        foreach (glob(BASE_PATH . '/public/assets/img/cars/*', GLOB_ONLYDIR) ?: [] as $directorio) {
            foreach (glob($directorio . '/video-*.jpg') ?: [] as $fichero) {
                if (!$dry && @unlink($fichero)) {
                    $borrados++;
                }
            }
        }

        if (!$dry) {
            try {
                $filas = Database::get()->execute('DELETE FROM coches_videos');
                // Miniaturas que un seeder anterior hubiera registrado como foto
                $filas += Database::get()->execute(
                    "DELETE FROM imagenes WHERE ruta LIKE 'assets/img/cars/%/video-%'"
                );
            } catch (\Throwable $e) {
                Logger::warning('Limpieza de vídeos: ' . $e->getMessage());
            }
            @unlink($manifestFile);
        }
    } else {
        $params = [];
        $marcadores = [];

        foreach ($codigos as $i => $codigo) {
            $params[':c' . $i] = $codigo;
            $marcadores[] = ':c' . $i;
        }

        $listaSql = implode(', ', $marcadores);

        if (!$dry) {
            try {
                $miniaturas = Database::get()->select(
                    "SELECT miniatura FROM coches_videos
                      WHERE plataforma = 'youtube' AND codigo IN ({$listaSql})",
                    $params
                );

                foreach ($miniaturas as $fila) {
                    $ruta = (string) ($fila['miniatura'] ?? '');
                    if (str_starts_with($ruta, 'assets/img/cars/') && @unlink(BASE_PATH . '/public/' . $ruta)) {
                        $borrados++;
                    }
                }

                $filas = Database::get()->execute(
                    "DELETE FROM coches_videos WHERE plataforma = 'youtube' AND codigo IN ({$listaSql})",
                    $params
                );
            } catch (\Throwable $e) {
                Logger::warning('Limpieza de vídeos: ' . $e->getMessage());
            }
            @unlink($manifestFile);
        }
    }

    printf("--limpiar: %d miniaturas, %d vídeos de la BD%s\n\n", $borrados, $filas, $dry ? ' (dry)' : '');
}

$manifest = is_file($manifestFile)
    ? ((json_decode((string) file_get_contents($manifestFile), true)) ?: [])
    : [];

/** @var array<string,string> $mapa */
$mapa = require BASE_PATH . '/database/seeds/imagenes.php';
$consultas = $mapa['cars'] ?? [];

// Ningún vídeo puede servir a dos fichas distintas
$usados = [];
foreach ($manifest as $slug => $lista) {
    if (!is_array($lista)) {
        continue;
    }
    foreach ($lista as $video) {
        $codigo = (string) ($video['codigo'] ?? '');
        if ($codigo !== '') {
            $usados[$codigo] = (string) $slug;
        }
    }
}

echo "Buscando vídeos de los vehículos…\n\n";

$encontrados = 0;
$omitidos    = 0;
$fallidos    = 0;

foreach ($consultas as $slug => $consulta) {
    if ($solo !== null && $slug !== $solo) {
        continue;
    }

    $existentes = $manifest[$slug] ?? [];
    if (!$force && count($existentes) >= $porCoche) {
        $omitidos++;
        printf("   = %-22s (%d vídeos)\n", $slug, count($existentes));
        continue;
    }

    $contexto = contextoCoche($slug, (string) $consulta);
    $tituloCoche = $contexto['etiqueta'];

    $ids = candidatos($contexto['busquedas']);

    if ($ids === []) {
        $fallidos++;
        printf("   ✗ %-22s sin resultados en YouTube\n", $slug);
        continue;
    }

    $lista = array_values($existentes);
    $n = count($lista);
    $usadoEnCoche = [];
    foreach ($lista as $v) {
        $usadoEnCoche[(string) ($v['codigo'] ?? '')] = true;
    }

    foreach ($ids as $id => $duracion) {
        if ($n >= $porCoche) {
            break;
        }
        if (isset($usados[$id]) || isset($usadoEnCoche[$id])) {
            continue;
        }

        $meta = datosVideo($id, $duracion);
        if ($meta === null) {
            continue;
        }

        if (!tituloCoincide($meta['titulo'], $contexto['tokens'])) {
            continue;
        }

        $directorio = BASE_PATH . '/public/assets/img/cars/' . $slug;
        $nombreMini = nombreMiniaturaLibre($directorio);
        $rutaRel = 'assets/img/cars/' . $slug . '/' . $nombreMini;

        if ($dry) {
            printf("   ~ %-22s %s · %s\n", $slug, $id, $meta['titulo']);
            $encontrados++;
            $usados[$id] = $slug;
            $usadoEnCoche[$id] = true;
            $n++;
            continue;
        }

        if (!is_dir($directorio)) {
            mkdir($directorio, 0775, true);
        }

        $miniatura = descargarMiniatura($id);
        if ($miniatura === false || file_put_contents($directorio . '/' . $nombreMini, $miniatura) === false) {
            $fallidos++;
            continue;
        }

        $lista[] = [
            'plataforma' => 'youtube',
            'codigo'     => $id,
            'titulo'     => $meta['titulo'],
            'canal'      => $meta['autor'],
            'duracion'   => $meta['duracion'],
            'miniatura'  => $rutaRel,
        ];

        $manifest[$slug] = $lista;
        $usados[$id] = $slug;
        $usadoEnCoche[$id] = true;
        $encontrados++;
        $n++;

        printf(
            "   ✓ %-22s %-13s %-38s %s\n",
            $slug,
            $meta['duracion'] ?? '--:--',
            mb_substr($meta['titulo'], 0, 38),
            $meta['autor']
        );
    }

    if ($n === 0) {
        $fallidos++;
        printf("   ✗ %-22s ningún vídeo válido de %d candidatos\n", $slug, count($ids));
    }
}

if (!$dry) {
    ksort($manifest);
    file_put_contents(
        $manifestFile,
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    );
}

printf("\nListo: %d vídeos nuevos, %d coches ya completos, %d sin vídeo.\n", $encontrados, $omitidos, $fallidos);

if ($dry) {
    exit(0);
}

$registrados = (new SeedGaleria(static function (string $m): void {
    echo '   ' . $m . "\n";
}))->videos();

printf("Base de datos: %d vídeos registrados.\n", $registrados);


// ====================================================================
//  Funciones
// ====================================================================

/**
 * @return array{etiqueta:string,tokens:array<int,string>,busquedas:array<int,string>}
 */
function contextoCoche(string $clave, string $consulta): array
{
    $marca = '';
    $modelo = '';

    try {
        $vehiculo = (new SeedGaleria())->vehiculoPorClave($clave);
        if ($vehiculo !== null) {
            $marca = $vehiculo['marca'];
            $modelo = $vehiculo['modelo'];
        }
    } catch (\Throwable) {
        // Sin base de datos se usa la consulta del manifiesto
    }

    if ($marca === '' && $modelo === '') {
        $partes = preg_split('/\s+/', trim($consulta)) ?: [];
        $marca = (string) array_shift($partes);
        $modelo = trim(implode(' ', $partes));
    }

    $tokens = array_values(array_unique(array_filter(
        array_map('mb_strtolower', preg_split('/\s+/', trim($marca . ' ' . $modelo)) ?: []),
        static fn (string $t): bool => $t !== '' && !preg_match('/^\d+$/', $t) && mb_strlen($t) >= 2
    )));

    if ($tokens === []) {
        $tokens = preg_split('/\s+/', str_replace('-', ' ', $slug)) ?: [];
    }

    $base = trim($marca . ' ' . $modelo);

    return [
        'etiqueta'   => trim($base),
        'tokens'     => $tokens,
        'busquedas'  => [
            $base . ' review',
            $base . ' test drive',
            $base,
        ],
    ];
}

/**
 * Identificadores de vídeo con su duración, en el orden de los resultados.
 *
 * La duración se lee de la propia página de resultados (donde sí aparece
 * junto a cada vídeo), evitando así una petición adicional por vídeo.
 *
 * @param array<int,string> $busquedas
 * @return array<string,string|null> id => duración («12:34») o null
 */
function candidatos(array $busquedas): array
{
    $encontrados = [];

    foreach ($busquedas as $consulta) {
        if (count($encontrados) >= 8) {
            break;
        }

        $html = http('https://www.youtube.com/results?search_query=' . rawurlencode(trim($consulta)), 25);
        if ($html === false) {
            continue;
        }

        foreach (duraciones($html) as $id => $duracion) {
            if (!array_key_exists($id, $encontrados)) {
                $encontrados[$id] = $duracion;
            }
        }

        pausa();
    }

    return array_slice($encontrados, 0, 8, true);
}

/**
 * Recorre la página de resultados y devuelve, para cada vídeo, el
 * identificador y la duración declarada en el bloque del propio vídeo.
 *
 * @return array<string,string|null>
 */
function duraciones(string $html): array
{
    $mapa = [];
    $desde = 0;

    while (($pos = strpos($html, '"videoId":"', $desde)) !== false) {
        $desde = $pos + 11;

        $id = substr($html, $pos + 11, 11);
        if (strlen($id) !== 11 || isset($mapa[$id])) {
            continue;
        }

        $mapa[$id] = null;

        // Cada bloque de resultado llega hasta el siguiente «videoId»
        $siguiente = strpos($html, '"videoId":"', $desde);
        $limite = $siguiente !== false ? $siguiente : $pos + 4000;
        $bloque = substr($html, $pos, min($limite, $pos + 4000) - $pos);

        $inicio = strpos($bloque, '"lengthText":');
        if ($inicio === false) {
            continue;
        }

        // Dentro de lengthText, «simpleText» es la duración («4:32»);
        // el nodo accessibility que lo precede usa «label».
        $zona = substr($bloque, $inicio, 320);
        if (preg_match('/"simpleText":"([^"]{1,9})"/', $zona, $m) === 1) {
            $mapa[$id] = $m[1];
        }
    }

    return $mapa;
}

/**
 * Metadatos públicos del vídeo (oEmbed) y duración ya conocida.
 *
 * @return array{titulo:string,autor:string,duracion:?string}|null
 */
function datosVideo(string $id, ?string $duracion = null): ?array
{
    $json = http(
        'https://www.youtube.com/oembed?' . http_build_query([
            'url'    => 'https://www.youtube.com/watch?v=' . $id,
            'format' => 'json',
        ]),
        20
    );

    if ($json === false) {
        return null;
    }

    $datos = json_decode($json, true);
    if (!is_array($datos) || empty($datos['title'])) {
        return null;
    }

    return [
        'titulo'   => (string) $datos['title'],
        'autor'    => (string) ($datos['author_name'] ?? 'YouTube'),
        'duracion' => $duracion,
    ];
}

/** ¿El título del vídeo habla del modelo? */
function tituloCoincide(string $titulo, array $tokens): bool
{
    $bajo = mb_strtolower($titulo);

    foreach ($tokens as $token) {
        if (str_contains($bajo, $token)) {
            return true;
        }
    }

    return false;
}

function descargarMiniatura(string $id): string|false
{
    foreach (['maxresdefault.jpg', 'hqdefault.jpg'] as $archivo) {
        $bytes = http('https://i.ytimg.com/vi/' . $id . '/' . $archivo, 20);
        if ($bytes !== false && strlen($bytes) > 5000) {
            return $bytes;
        }
    }

    return false;
}

/**
 * Próximo «video-NN.jpg» libre de una carpeta.
 *
 * El nombre no depende del índice de la lista que se está descargando
 * para no pisar miniaturas que hayan subido los administradores.
 */
function nombreMiniaturaLibre(string $directorio): string
{
    $max = 0;

    foreach (glob($directorio . '/video-*.jpg') ?: [] as $fichero) {
        if (preg_match('/^video-(\d+)\.jpg$/', basename($fichero), $m) === 1) {
            $max = max($max, (int) $m[1]);
        }
    }

    return sprintf('video-%02d.jpg', $max + 1);
}

function http(string $url, int $timeout = 20): string|false
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 4,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
            . '(KHTML, like Gecko) Chrome/124.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => ['Accept: text/html,application/json;q=0.9,*/*;q=0.8'],
    ]);

    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    pausa();

    if ($body === false || $code >= 400) {
        return false;
    }

    return (string) $body;
}

/** Cortesía hacia el servidor. */
function pausa(): void
{
    usleep(200000);
}
