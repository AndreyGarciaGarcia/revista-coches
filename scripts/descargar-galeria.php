<?php

/**
 * =====================================================================
 *  Galería ampliada por vehículo (Commons)
 * =====================================================================
 *
 *   php scripts/descargar-galeria.php                       todo
 *   php scripts/descargar-galeria.php --solo=vw-golf        un coche
 *   php scripts/descargar-galeria.php --por-coche=6         más tomas
 *   php scripts/descargar-galeria.php --dry                 sin escribir
 *   php scripts/descargar-galeria.php --db                  sólo registrar
 *
 * Para cada vehículo del catálogo recupera fotografías ADICIONALES a la
 * que ya usa la ficha (exterior alternativo, interior, motor y detalles):
 *
 *   1. localiza la categoría de Commons del modelo y lee sus ficheros;
 *   2. complementa con búsquedas dirigidas («… interior», «… engine»);
 *   3. descarta repetidas, logotipos, diagramas y miniaturas;
 *   4. clasifica cada toma en exterior / interior / motor / detalle;
 *   5. descarga a public/assets/img/cars/<slug>/NN-<categoria>.jpg;
 *   6. guarda autor y licencia en el manifiesto de créditos;
 *   7. registra y vincula las fotos en la base de datos.
 *
 * Ninguna fotografía se usa dos veces en todo el sitio.
 * =====================================================================
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Console\SeedGaleria;
use App\Core\Database;
use App\Core\Logger;

const MANIFEST = 'database/seeds/imagenes-manifest.json';

$opciones = cli_args($argv, [
    'solo::', 'por-coche::', 'ancho::', 'limpiar', 'dry', 'force', 'db', 'debug', 'help',
]);

if (isset($opciones['help'])) {
    echo <<<TXT
MotorPress · Galería de vehículos desde Wikimedia Commons

  php scripts/descargar-galeria.php                amplía todas las galerías
  php scripts/descargar-galeria.php --solo=vw-golf sólo ese vehículo
  php scripts/descargar-galeria.php --por-coche=6  hasta 6 fotos por coche
  php scripts/descargar-galeria.php --ancho=1280   ancho máximo en px
  php scripts/descargar-galeria.php --limpiar      borra las galerías anidadas
  php scripts/descargar-galeria.php --db           sólo registra en la BD
  php scripts/descargar-galeria.php --dry          no escribe nada
  php scripts/descargar-galeria.php --force        vuelve a descargar
  php scripts/descargar-galeria.php --debug        muestra las candidatas

TXT;
    exit(0);
}

$solo         = isset($opciones['solo']) ? (string) $opciones['solo'] : null;
$porCoche     = max(1, (int) ($opciones['por-coche'] ?? 4));
$ancho        = max(640, (int) ($opciones['ancho'] ?? 1280));
$dry          = isset($opciones['dry']);
$force        = isset($opciones['force']);
$soloDb       = isset($opciones['db']);
$debug        = isset($opciones['debug']);
$limpiar      = isset($opciones['limpiar']);

/** @var array<string,string> $mapa */
$mapa = require BASE_PATH . '/database/seeds/imagenes.php';
$cars = $mapa['cars'] ?? [];

$manifestFile = BASE_PATH . '/' . MANIFEST;
$manifest = is_file($manifestFile)
    ? ((json_decode((string) file_get_contents($manifestFile), true)) ?: [])
    : [];

// --limpiar: reinicia las galerías anidadas (archivos, manifiesto y BD)
if ($limpiar) {
    $borrados = 0;

    foreach (glob(BASE_PATH . '/public/assets/img/cars/*', GLOB_ONLYDIR) ?: [] as $directorio) {
        foreach (glob($directorio . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [] as $fichero) {
            $nombre = basename($fichero);
            // Las miniaturas de vídeo pertenecen a scripts/descargar-videos.php
            // y «subida-*» es material entregado desde el panel: no se tocan.
            if (str_starts_with($nombre, 'video-') || str_starts_with($nombre, 'subida-')) {
                continue;
            }
            if (!$dry && @unlink($fichero)) {
                $borrados++;
            }
        }
    }

    foreach (array_keys($manifest) as $claveManifest) {
        if (preg_match('#^cars/[^/]+/[^/]+$#', (string) $claveManifest) === 1) {
            unset($manifest[$claveManifest]);
        }
    }

    $fueraBD = 0;
    if (!$dry) {
        try {
            $fueraBD = Database::get()->execute(
                "DELETE FROM imagenes
                  WHERE ruta LIKE 'assets/img/cars/%/%'
                    AND ruta NOT LIKE 'assets/img/cars/%/video-%'
                    AND ruta NOT LIKE 'assets/img/cars/%/subida-%'"
            );
        } catch (\Throwable $e) {
            Logger::warning('Limpieza de galería en BD: ' . $e->getMessage());
        }
    }

    printf("--limpiar: %d archivos, %d filas de la BD%s\n\n", $borrados, $fueraBD, $dry ? ' (dry)' : '');
}

// Títulos de Commons ya assignados a otra clave: nunca se repite una foto.
$titulosUsados = [];
foreach ($manifest as $claveManifest => $meta) {
    if (!empty($meta['titulo'])) {
        $titulosUsados[(string) $meta['titulo']][(string) $claveManifest] = true;
    }
}

if (!$soloDb) {
    echo "Ampliando galerías desde Wikimedia Commons…\n\n";

    $descargadas = 0;
    $omitidas    = 0;
    $fallidas    = 0;
    $procesados  = 0;

    foreach ($cars as $slug => $consulta) {
        if ($solo !== null && $slug !== $solo) {
            continue;
        }

        $directorio = BASE_PATH . '/public/assets/img/cars/' . $slug;
        $existentes = glob($directorio . '/*.{jpg,jpeg}', GLOB_BRACE) ?: [];

        if (!$force && count($existentes) >= $porCoche) {
            $omitidas += count($existentes);
            printf("   = %-22s (%d fotos ya en la galería)\n", $slug, count($existentes));
            continue;
        }

        $procesados++;
        $candidatos = candidatos($consulta, $titulosUsados, $ancho, $debug);

        if ($candidatos === []) {
            $fallidas++;
            printf("   ✗ %-22s sin fotografías adicionales\n", $slug);
            continue;
        }

        $elegidas = seleccionar($candidatos, $porCoche);

        if (!is_dir($directorio)) {
            mkdir($directorio, 0775, true);
        }

        // Con --force la galería se reconstruye desde la primera toma
        $n = $force ? 0 : count($existentes);

        foreach ($elegidas as $cand) {
            if ($n >= $porCoche) {
                break;
            }

            $n++;
            $nombre  = sprintf('%02d-%s.jpg', $n + 1, $cand['categoria']);
            $rutaRel = 'assets/img/cars/' . $slug . '/' . $nombre;
            $abs     = BASE_PATH . '/public/' . $rutaRel;
            $clave   = 'cars/' . $slug . '/' . pathinfo($nombre, PATHINFO_FILENAME);

            if (is_file($abs) && !$force) {
                $n--;
                continue;
            }

            if ($dry) {
                printf("   ~ %s → %s (%s)\n", $slug, $nombre, $cand['categoria']);
                $descargadas++;
                continue;
            }

            $bytes = descargar($cand['thumburl'], $ancho);

            if ($bytes === false || strlen($bytes) < 8000) {
                $fallidas++;
                $n--;
                printf("   ✗ %s — error descargando %s\n", $slug, $nombre);
                continue;
            }

            if (file_put_contents($abs, $bytes) === false) {
                $fallidas++;
                $n--;
                continue;
            }

            [$w, $h] = @getimagesize($abs) ?: [0, 0];

            $manifest[$clave] = [
                'seccion'   => 'cars',
                'ruta'      => $rutaRel,
                'categoria' => $cand['categoria'],
                'titulo'    => $cand['titulo'],
                'autor'     => $cand['autor'],
                'licencia'  => $cand['licencia'],
                'fuente'    => $cand['fuente'],
                'ancho'     => $w,
                'alto'      => $h,
                'peso_kb'   => (int) round(strlen($bytes) / 1024),
                'consulta'  => $consulta,
            ];

            $titulosUsados[$cand['titulo']][$clave] = true;
            $descargadas++;
            printf("   ✓ %-22s %-22s %4dx%-4d %s\n", $slug, $nombre, $w, $h, $cand['licencia']);
        }
    }

    // -----------------------------------------------------------------
    //  Ninguna fotografía puede repetirse en todo el sitio
    // -----------------------------------------------------------------
    $hashes = [];
    $duplicadas = [];
    foreach (imagenesDelSitio() as $fichero) {
        $hash = sha1_file($fichero);
        if ($hash === false) {
            continue;
        }
        if (isset($hashes[$hash])) {
            $duplicadas[] = basename($fichero) . ' == ' . basename($hashes[$hash]);
        } else {
            $hashes[$hash] = $fichero;
        }
    }

    if ($duplicadas !== []) {
        fwrite(STDERR, "\n  ✗ IMÁGENES REPETIDAS:\n");
        foreach ($duplicadas as $dup) {
            fwrite(STDERR, "      · {$dup}\n");
        }
    }

    if (!$dry) {
        ksort($manifest);
        file_put_contents(
            $manifestFile,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
        );
        escribirCreditos($manifest);
    }

    printf("\nDescarga: %d nuevas, %d ya existían, %d fallidas.\n", $descargadas, $omitidas, $fallidas);
}

if ($dry) {
    exit(0);
}

// ---------------------------------------------------------------------
//  Base de datos: registrar las fotografías y vincularlas a su coche
// ---------------------------------------------------------------------
$galeria = new SeedGaleria(static function (string $m): void {
    echo '   ' . $m . "\n";
});

$registradas = $galeria->registrar();
$vinculadas  = $galeria->vincular();

printf("Base de datos: %d fotografías registradas, %d vínculos creados.\n", $registradas, $vinculadas);


// ====================================================================
//  Funciones
// ====================================================================

/**
 * Recopila candidatas para un vehículo: categoría de Commons del modelo y
 * búsquedas dirigidas. Devuelve sólo las válidas, ya puntuadas.
 *
 * @return array<int,array{titulo:string,thumburl:string,autor:string,
 *                        licencia:string,fuente:string,categoria:string,
 *                        puntuacion:float,ancho:int,alto:int}>
 */
function candidatos(string $consulta, array $usados, int $ancho, bool $debug = false): array
{
    $brutos = [];
    $corta = consultaCorta($consulta);
    $categoria = null;

    // 1) Categoría de Commons del modelo (motor, interiores, tomas…)
    $categoria = buscarCategoria($corta !== '' ? $corta : $consulta);
    if ($categoria !== null) {
        $titulos = miembrosCategoria($categoria, 60);
        foreach (infoFicheros($titulos, $categoria) as $ficha) {
            $brutos[] = $ficha;
        }

        // Subcategorías: «… interiors», «… engines», «… details»…
        foreach (subcategoriasEspecificas($categoria, 5) as $sub) {
            $subTitulos = miembrosCategoria($sub, 40);
            foreach (infoFicheros($subTitulos, $sub) as $ficha) {
                $brutos[] = $ficha;
            }
        }
    }

    // 2) Búsquedas dirigidas (complemento y garantía de variedad)
    $busquedas = array_values(array_unique([
        $consulta,
        $corta . ' interior',
        $corta . ' engine',
        $corta . ' dashboard',
    ]));

    foreach ($busquedas as $q) {
        if (trim($q) === '') {
            continue;
        }
        foreach (buscarFicheros($q, 14) as $ficha) {
            $brutos[] = $ficha;
        }
    }

    $limpios = [];
    $vistos  = [];

    foreach ($brutos as $ficha) {
        $titulo = $ficha['titulo'];

        if (isset($vistos[$titulo]) || isset($usados[$titulo])) {
            continue;
        }
        $vistos[$titulo] = true;

        $valida = validar($ficha, $ancho);
        if ($valida === null) {
            continue;
        }

        $limpios[] = $valida;
    }

    usort($limpios, static fn (array $a, array $b): int => $b['puntuacion'] <=> $a['puntuacion']);

    if ($debug) {
        $conteos = [];
        foreach ($limpios as $c) {
            $conteos[$c['categoria']] = ($conteos[$c['categoria']] ?? 0) + 1;
        }
        ksort($conteos);
        echo '     categoría: ' . ($categoria ?? '—')
            . ' · candidatas: ' . count($limpios) . ' ' . json_encode($conteos, JSON_UNESCAPED_UNICODE) . "\n";
    }

    return $limpios;
}

/**
 * La consulta del manifiesto incluye carrocería y motorización; para
 * buscar en Commons interesa recortarla («Volkswagen Golf Mk8»).
 */
function consultaCorta(string $consulta): string
{
    $sinCarroceria = preg_replace(
        '/\b(sedan|hatchback|suv|coupe|coupé|crossover|break|wagon|touring|estate|convertible|roadster|'
        . 'van|minivan|pickup|electric|eléctrico|berlina|utilitario|deportivo|familiar|compacto|gran coupé|'
        . 'gran coupe|multipurpose|mpv|limousine|cabrio)\b/iu',
        '',
        $consulta
    ) ?? $consulta;

    return trim(preg_replace('/\s+/', ' ', $sinCarroceria));
}

/**
 * Filtra y puntúa una candidata; null si no sirve.
 *
 * @param array<string,mixed> $ficha
 * @return array<string,mixed>|null
 */
function validar(array $ficha, int $anchoMinimo): ?array
{
    $titulo = (string) $ficha['titulo'];
    $w = (int) $ficha['ancho'];
    $h = (int) $ficha['alto'];

    // Sólo JPEG: sin transparencias (PNG) ni vectores
    if (!preg_match('/\.jpe?g$/i', $titulo)) {
        return null;
    }

    if (preg_match(
        '/logo|badge|emblem|icon|signage|wordmark|diagram|sketch|chart|map of|screenshot|poster|collage|drawing|patent|\bnew\.png/i',
        $titulo
    )) {
        return null;
    }

    $categoria = clasificar($titulo);

    // Si el nombre del fichero no lo dice, se aplica el contexto: la
    // subcategoría de Commons o la búsqueda que lo ha traído.
    if ($categoria === 'exterior') {
        $origen = mb_strtolower((string) ($ficha['origen'] ?? ''));

        if (preg_match('/interior|cockpit|dashboard|innenraum|cabin/', $origen)) {
            $categoria = 'interior';
        } elseif (preg_match('/engine|motor|triebwerk|antrieb|powertrain/', $origen)) {
            $categoria = 'motor';
        } elseif (preg_match('/wheel|llanta|headlight|faros|grille|badge|detail/', $origen)) {
            $categoria = 'detalle';
        }
    }

    $minW = $categoria === 'interior' ? 700 : 900;
    $minH = $categoria === 'interior' ? 450 : 500;

    if ($w < $minW || $h < $minH) {
        return null;
    }

    if ($w > 12000 || $h > 9000) {
        return null;
    }

    $ratio = $w / max(1, $h);
    if ($ratio < 0.75 || $ratio > 3.4) {
        return null;
    }

    $puntuacion = 100.0;
    $puntuacion -= abs($ratio - 1.7) * 12;
    if ($w >= $anchoMinimo) {
        $puntuacion += 10;
    }
    if ($w < 1200) {
        $puntuacion -= 15;
    }
    if ($categoria === 'exterior') {
        $puntuacion += 5;
    }
    if (preg_match('/\b(front|rear|side|profile|three.?quarter|3\/4)\b/i', $titulo)) {
        $puntuacion += 6;
    }
    if (preg_match('/\b(concept|render|illustration|scale model|toy|miniature|diecast)\b/i', $titulo)) {
        $puntuacion -= 40;
    }

    $ficha['categoria']   = $categoria;
    $ficha['puntuacion']  = $puntuacion;

    return $ficha;
}

/**
 * Elige una selección variada: alternativa exterior, motor, interior,
 * detalle y, si sobra sitio, lo mejor que quede.
 *
 * @param array<int,array<string,mixed>> $candidatos
 * @return array<int,array<string,mixed>>
 */
function seleccionar(array $candidatos, int $cuantas): array
{
    $plan = ['exterior', 'motor', 'interior', 'detalle', 'exterior', 'interior', 'motor', 'exterior'];
    $elegidas = [];
    $usados = [];

    foreach ($plan as $tipo) {
        if (count($elegidas) >= $cuantas) {
            break;
        }
        foreach ($candidatos as $i => $c) {
            if (isset($usados[$i]) || $c['categoria'] !== $tipo) {
                continue;
            }
            $usados[$i] = true;
            $elegidas[] = $c;
            break;
        }
    }

    // Relleno con lo mejor que haya quedado fuera
    foreach ($candidatos as $i => $c) {
        if (count($elegidas) >= $cuantas) {
            break;
        }
        if (isset($usados[$i])) {
            continue;
        }
        $usados[$i] = true;
        $elegidas[] = $c;
    }

    return $elegidas;
}

/** Clasifica una toma a partir del nombre del fichero de Commons. */
function clasificar(string $titulo): string
{
    if (preg_match(
        '/\b(engine|motors?|triebwerk|powertrain|hood|bonnet|motorraum|under the hood|cylinder|turbo|batter(y|ien)|fuel cell)\b/i',
        $titulo
    )) {
        return 'motor';
    }

    if (preg_match(
        '/\b(interior|cockpit|dashboard|instrument|lenkrad|steering|seats?|sitze|sitzbank|back ?seat|rear seat|innenraum|'
        . 'centre console|center console|gearshift|gear ?knob|odometer|speedometer|door open|tailgate|trunk|kofferraum)\b/i',
        $titulo
    )) {
        return 'interior';
    }

    if (preg_match(
        '/\b(wheels?|llantas?|rims?|headlights?|faros|taillights?|rear ?lights?|grille|radiator|badge|emblem|'
        . 'close-?up|detail|exhaust|spoiler|alloy)\b/i',
        $titulo
    )) {
        return 'detalle';
    }

    return 'exterior';
}

/** Localiza la categoría de Commons mejor adaptada a la consulta. */
function buscarCategoria(string $consulta): ?string
{
    $url = 'https://commons.wikimedia.org/w/api.php?' . http_build_query([
        'action'     => 'query',
        'list'       => 'search',
        'srsearch'   => $consulta,
        'srnamespace'=> 14,              // espacio de categorías
        'srlimit'    => 6,
        'format'     => 'json',
        'formatversion' => 2,
    ]);

    $json = http($url, 25);
    if ($json === false) {
        return null;
    }

    $datos = json_decode($json, true);
    $resultados = $datos['query']['search'] ?? [];

    $tokens = preg_split('/\s+/', mb_strtolower($consulta)) ?: [];
    $tokens = array_values(array_filter($tokens, static fn (string $t): bool => mb_strlen($t) > 2));

    $mejor = null;
    $mejorPuntos = 0;

    foreach ($resultados as $fila) {
        $titulo = (string) ($fila['title'] ?? '');
        $bajo = mb_strtolower($titulo);

        $puntos = 0;
        foreach ($tokens as $t) {
            if (str_contains($bajo, $t)) {
                $puntos++;
            }
        }

        if ($puntos > $mejorPuntos) {
            $mejorPuntos = $puntos;
            $mejor = $titulo;
        }
    }

    // Hace falta acertar al menos la mitad de los términos
    return ($mejor !== null && $mejorPuntos >= max(1, (int) ceil(count($tokens) / 2)))
        ? $mejor
        : null;
}

/**
 * @return array<int,string> títulos «File:…» de la categoría
 */
function miembrosCategoria(string $categoria, int $limite = 60): array
{
    $url = 'https://commons.wikimedia.org/w/api.php?' . http_build_query([
        'action'      => 'query',
        'list'        => 'categorymembers',
        'cmtitle'     => $categoria,
        'cmtype'      => 'file',
        'cmlimit'     => $limite,
        'format'      => 'json',
        'formatversion'=> 2,
    ]);

    $json = http($url, 25);
    if ($json === false) {
        return [];
    }

    $datos = json_decode($json, true);

    return array_map(
        static fn (array $f): string => (string) $f['title'],
        $datos['query']['categorymembers'] ?? []
    );
}

/**
 * @param array<int,string> $titulos
 * @return array<int,array<string,mixed>>
 */
function infoFicheros(array $titulos, string $origen = ''): array
{
    $salida = [];

    foreach (array_chunk($titulos, 40) as $lote) {
        $url = 'https://commons.wikimedia.org/w/api.php?' . http_build_query([
            'action'   => 'query',
            'titles'   => implode('|', $lote),
            'prop'     => 'imageinfo',
            'iiprop'   => 'url|size|extmetadata',
            'iiurlwidth' => 1600,
            'format'   => 'json',
            'formatversion' => 2,
        ]);

        $json = http($url, 30);
        if ($json === false) {
            continue;
        }

        $datos = json_decode($json, true);

        foreach (($datos['query']['pages'] ?? []) as $pagina) {
            $info = $pagina['imageinfo'][0] ?? null;
            if ($info === null || !empty($pagina['missing'])) {
                continue;
            }
            $ficha = construir($pagina, $info);
            $ficha['origen'] = $origen;
            $salida[] = $ficha;
        }

        pausa();
    }

    return $salida;
}

/**
 * Subcategorías que suelen guardar interiores, motores y detalles.
 *
 * @return array<int,string>
 */
function subcategoriasEspecificas(string $categoria, int $limite = 5): array
{
    $url = 'https://commons.wikimedia.org/w/api.php?' . http_build_query([
        'action'      => 'query',
        'list'        => 'categorymembers',
        'cmtitle'     => $categoria,
        'cmtype'      => 'subcat',
        'cmlimit'     => 60,
        'format'      => 'json',
        'formatversion'=> 2,
    ]);

    $json = http($url, 25);
    if ($json === false) {
        return [];
    }

    $datos = json_decode($json, true);
    $subcats = array_map(
        static fn (array $f): string => (string) $f['title'],
        $datos['query']['categorymembers'] ?? []
    );

    $prioridad = [];
    $resto = [];

    foreach ($subcats as $sub) {
        if (preg_match('/interior|cockpit|dashboard|cabin|engine|motor|triebwerk|antrieb|detail|wheel|seat/i', $sub)) {
            $prioridad[] = $sub;
        } else {
            $resto[] = $sub;
        }
    }

    return array_slice(array_merge($prioridad, $resto), 0, $limite);
}

/**
 * @return array<int,array<string,mixed>>
 */
function buscarFicheros(string $consulta, int $limite): array
{
    $url = 'https://commons.wikimedia.org/w/api.php?' . http_build_query([
        'action'     => 'query',
        'generator'  => 'search',
        'gsrsearch'  => $consulta,
        'gsrnamespace' => 6,
        'gsrlimit'   => $limite,
        'prop'       => 'imageinfo',
        'iiprop'     => 'url|size|extmetadata',
        'iiurlwidth' => 1600,
        'format'     => 'json',
        'formatversion' => 2,
    ]);

    $json = http($url, 25);
    if ($json === false) {
        return [];
    }

    $datos = json_decode($json, true);
    $salida = [];

    foreach (($datos['query']['pages'] ?? []) as $pagina) {
        $info = $pagina['imageinfo'][0] ?? null;
        if ($info === null) {
            continue;
        }
        $ficha = construir($pagina, $info);
        $ficha['origen'] = $consulta;
        $salida[] = $ficha;
    }

    pausa();

    return $salida;
}

/**
 * @param array<string,mixed> $pagina
 * @param array<string,mixed> $info
 * @return array<string,mixed>
 */
function construir(array $pagina, array $info): array
{
    $titulo = (string) ($pagina['title'] ?? '');
    $meta = $info['extmetadata'] ?? [];
    $autor = limpiarHtml((string) ($meta['Artist']['value'] ?? ''));
    $licencia = (string) ($meta['LicenseShortName']['value'] ?? 'CC BY-SA');
    $fuente = (string) ($meta['DescriptionUrl']['value'] ?? $info['descriptionurl'] ?? 'https://commons.wikimedia.org/');

    if ($autor === '' || mb_strlen($autor) > 120) {
        $autor = 'Wikimedia Commons';
    }

    return [
        'titulo'   => preg_replace('/^File:/', '', $titulo) ?? $titulo,
        'thumburl' => (string) ($info['thumburl'] ?? $info['url'] ?? ''),
        'autor'    => $autor,
        'licencia' => $licencia,
        'fuente'   => $fuente,
        'ancho'    => (int) ($info['width'] ?? 0),
        'alto'     => (int) ($info['height'] ?? 0),
    ];
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
        CURLOPT_USERAGENT      => 'MotorPress/1.0 (instalación local; contacto: admin@motorpress.dev)',
        CURLOPT_HTTPHEADER     => ['Accept: */*'],
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

function descargar(string $url, int $ancho = 0): string|false
{
    // Se elimina la telemetría que añade Commons a la URL
    $limpio = preg_replace('/\?.*$/', '', $url) ?? $url;

    // El servicio de miniaturas sólo admite anchos de catálogo (960, 1280,
    // 1920…): se pide el deseado y, si lo rechaza, se cae al original.
    $candidatas = [];

    if ($ancho > 0) {
        $candidatas[] = preg_replace('#/\d+px-#', '/' . $ancho . 'px-', $limpio, 1) ?? $limpio;
    }

    $candidatas[] = $limpio;

    foreach (array_unique($candidatas) as $candidata) {
        $bytes = http($candidata, 45);
        if ($bytes !== false) {
            return $bytes;
        }
    }

    return false;
}

function limpiarHtml(string $html): string
{
    $texto = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $texto = preg_replace('/\s+/u', ' ', $texto) ?? $texto;

    return trim($texto);
}

/** Cortesía hacia el servidor de la enciclopedia. */
function pausa(): void
{
    usleep(120000);
}

/** Todas las imágenes del sitio (carpetas planas y anidadas). */
function imagenesDelSitio(): array
{
    $base = BASE_PATH . '/public/assets/img';
    $ficheros = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        if (!preg_match('/\.(jpe?g|png|webp|avif)$/i', $file->getFilename())) {
            continue;
        }
        if (str_contains($file->getPathname(), '/uploads/')) {
            continue;
        }
        $ficheros[] = $file->getPathname();
    }

    return $ficheros;
}

/** Regenera el fichero público de créditos y licencias. */
function escribirCreditos(array $manifest): void
{
    $porLicencia = [];
    foreach ($manifest as $item) {
        $lic = (string) ($item['licencia'] ?? 'CC');
        $porLicencia[$lic] = ($porLicencia[$lic] ?? 0) + 1;
    }
    ksort($porLicencia);

    $salida = "# Créditos de imágenes\n\n";
    $salida .= "Todas las fotografías proceden de **Wikimedia Commons** y se usan bajo la\n";
    $salida .= "licencia indicada. Cada imagen enlaza a su ficha de origen, donde constan\n";
    $salida .= "el autor y la licencia completas.\n\n";
    $salida .= "Regenerado: " . date('Y-m-d H:i:s') . "\n\n";

    $salida .= "| Licencia | Imágenes |\n|---|---:|\n";
    foreach ($porLicencia as $lic => $n) {
        $salida .= sprintf("| %s | %d |\n", str_replace('|', '/', $lic), $n);
    }

    $salida .= "\n| Sección | Clave | Archivo | Autor | Licencia |\n|---|---|---|---|---|\n";

    ksort($manifest);
    foreach ($manifest as $clave => $item) {
        $salida .= sprintf(
            "| %s | %s | `%s` | %s | %s |\n",
            str_replace('|', '/', (string) ($item['seccion'] ?? '')),
            $clave,
            (string) ($item['ruta'] ?? ''),
            str_replace('|', '/', (string) ($item['autor'] ?? '—')),
            str_replace('|', '/', (string) ($item['licencia'] ?? '—'))
        );
    }

    file_put_contents(BASE_PATH . '/public/assets/img/CREDITOS.md', $salida);
}
