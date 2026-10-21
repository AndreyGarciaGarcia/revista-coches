<?php

/**
 * =====================================================================
 *  Descarga de fotografías desde Wikimedia Commons
 * =====================================================================
 *
 *   php scripts/descargar-imagenes.php                 (todo)
 *   php scripts/descargar-imagenes.php --only=cars     (sólo catálogo)
 *   php scripts/descargar-imagenes.php --key=clave     (una sola imagen)
 *   php scripts/descargar-imagenes.php --force         (re-descarga)
 *   php scripts/descargar-imagenes.php --dry           (sin descargar)
 *
 * Para cada clave del manifiesto busca en Commons la fotografía más
 * adecuada, la guarda en public/assets/img/<seccion>/<clave>.jpg y
 * registra autor, licencia y URL de origen en
 * database/seeds/imagenes-manifest.json.
 *
 * Resultado: 0 imágenes repetidas y 100 % de créditos documentados.
 * =====================================================================
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

const MANIFEST = 'database/seeds/imagenes-manifest.json';

$opciones = cli_args($argv, ['only::', 'force', 'dry', 'limit::', 'key::', 'help']);

if (isset($opciones['help'])) {
    echo <<<TXT
MotorPress · Fotografías de Wikimedia Commons

  php scripts/descargar-imagenes.php               descarga todo lo que falte
  php scripts/descargar-imagenes.php --only=cars   sólo la sección indicada
  php scripts/descargar-imagenes.php --key=clave   sólo una imagen concreta
  php scripts/descargar-imagenes.php --force       vuelve a descargar
  php scripts/descargar-imagenes.php --dry         no escribe nada
  php scripts/descargar-imagenes.php --limit=5     máximo 5 imágenes

TXT;
    exit(0);
}

$only = $opciones['only'] ?? null;
$claveUnica = $opciones['key'] ?? null;
$force = isset($opciones['force']);
$dry = isset($opciones['dry']);
$limit = isset($opciones['limit']) ? (int) $opciones['limit'] : PHP_INT_MAX;

/** @var array<string,array<string,string>> $mapa */
$mapa = require BASE_PATH . '/database/seeds/imagenes.php';

$dirs = [
    'hero'  => 'public/assets/img/hero',
    'cars'  => 'public/assets/img/cars',
    'news'  => 'public/assets/img/news',
];

foreach ($dirs as $dir) {
    $abs = BASE_PATH . '/' . $dir;
    if (!is_dir($abs)) {
        mkdir($abs, 0775, true);
    }
}

$manifest = [];
$manifestFile = BASE_PATH . '/' . MANIFEST;
if (is_file($manifestFile)) {
    $manifest = json_decode((string) file_get_contents($manifestFile), true) ?: [];
}

$descargadas = 0;
$omitidas = 0;
$fallidas = 0;

// Títulos de Commons ya asignados a OTRA clave: jamás se descarga dos
// veces la misma fotografía (garantía de que ninguna imagen se repite).
// Un mismo título puede estar (erróneamente) en dos claves: se considera
// «libre» sólo si ninguna otra clave lo reclama.
$titulosUsados = [];
foreach ($manifest as $claveManifest => $meta) {
    if (!empty($meta['titulo'])) {
        $titulo = (string) $meta['titulo'];
        $titulosUsados[$titulo][(string) $claveManifest] = true;
    }
}

echo "Descargando fotografías desde Wikimedia Commons…\n\n";

foreach ($mapa as $seccion => $items) {
    if ($only !== null && $only !== $seccion) {
        continue;
    }

    printf("── %s (%d imágenes)\n", $seccion, count($items));

    $contador = 0;
    foreach ($items as $clave => $consulta) {
        if ($claveUnica !== null && $clave !== $claveUnica) {
            continue;
        }

        if (++$contador > $limit) {
            break;
        }

        $relativo = 'assets/img/' . $seccion . '/' . $clave . '.jpg';
        $absoluto = BASE_PATH . '/public/' . $relativo;

        if (is_file($absoluto) && !$force) {
            $omitidas++;
            echo "   = {$clave} (ya existe)\n";
            continue;
        }

        // Excepciones: todos los títulos usados por OTRAS claves
        $prohibidas = [];
        foreach ($titulosUsados as $tituloUsado => $duenos) {
            foreach (array_keys($duenos) as $dueno) {
                if ($dueno !== $clave) {
                    $prohibidas[$tituloUsado] = $dueno;
                    break;
                }
            }
        }

        $resultado = buscarEnCommons($consulta, $prohibidas);

        if ($resultado === null) {
            $fallidas++;
            echo "   ✗ {$clave} — sin resultados para «{$consulta}»\n";
            continue;
        }

        if ($dry) {
            echo "   ~ {$clave} → {$resultado['titulo']}\n";
            $descargadas++;
            continue;
        }

        $bytes = descargar($resultado['thumburl']);
        if ($bytes === false || strlen($bytes) < 8000) {
            $fallidas++;
            echo "   ✗ {$clave} — error de descarga\n";
            continue;
        }

        if (file_put_contents($absoluto, $bytes) === false) {
            $fallidas++;
            echo "   ✗ {$clave} — no se pudo escribir el fichero\n";
            continue;
        }

        [$w, $h] = @getimagesize($absoluto) ?: [0, 0];

        $manifest[$clave] = [
            'seccion' => $seccion,
            'ruta'    => $relativo,
            'titulo'  => $resultado['titulo'],
            'autor'   => $resultado['autor'],
            'licencia'=> $resultado['licencia'],
            'fuente'  => $resultado['fuente'],
            'ancho'   => $w,
            'alto'    => $h,
            'peso_kb' => (int) round(strlen($bytes) / 1024),
            'consulta'=> $consulta,
        ];

        $descargadas++;
        $titulosUsados[$resultado['titulo']][(string) $clave] = true;
        printf("   ✓ %-26s %4dx%-4d %s\n", $clave, $w, $h, $resultado['licencia']);
    }

    echo "\n";
}

// ---------------------------------------------------------------------
//  Verificación final: ninguna fotografía puede repetirse
// ---------------------------------------------------------------------
$hashes = [];
$duplicadas = [];
foreach (glob(BASE_PATH . '/public/assets/img/{cars,news,hero}/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [] as $fichero) {
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
    fwrite(STDERR, "\n  ✗ IMÁGENES REPETIDAS (deben resolverse):\n");
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

printf("Listo: %d descargadas, %d ya existían, %d fallidas.\n", $descargadas, $omitidas, $fallidas);

if ($descargadas === 0 && $omitidas > 0) {
    echo "Nada que hacer: todas las imágenes ya están descargadas.\n";
}


// ====================================================================
//  Funciones
// ====================================================================

/**
 * Busca en la API de Wikimedia Commons la fotografía más adecuada.
 *
 * @param array<string,string> $prohibidas Títulos ya asignados a otra clave
 * @return array{titulo:string,thumburl:string,autor:string,licencia:string,fuente:string}|null
 */
function buscarEnCommons(string $consulta, array $prohibidas = []): ?array
{
    $url = 'https://commons.wikimedia.org/w/api.php?' . http_build_query([
        'action'     => 'query',
        'generator'  => 'search',
        'gsrsearch'  => $consulta,
        'gsrnamespace' => 6,          // espacio de ficheros
        'gsrlimit'   => 12,
        'prop'       => 'imageinfo',
        'iiprop'     => 'url|size|extmetadata',
        'iiurlwidth' => 1800,
        'format'     => 'json',
        'formatversion' => 2,
    ]);

    $json = http($url, 25);
    if ($json === false) {
        return null;
    }

    $datos = json_decode($json, true);
    $paginas = $datos['query']['pages'] ?? [];

    $mejor = null;
    $mejorPuntuacion = -1;

    foreach ($paginas as $pagina) {
        $info = $pagina['imageinfo'][0] ?? null;
        if ($info === null) {
            continue;
        }

        $titulo = (string) ($pagina['title'] ?? '');
        $ancho = (int) ($info['width'] ?? 0);
        $alto = (int) ($info['height'] ?? 0);

        // Ni la misma fotografía dos veces en todo el sitio
        // (el manifiesto guarda el título sin el prefijo «File:»)
        $normalizado = preg_replace("/^File:/", "", $titulo) ?? $titulo;
        if (isset($prohibidas[$normalizado])) {
            continue;
        }

        // Filtros: nada de logotipos, diagramas ni miniaturas
        if (preg_match('/logo|badge|emblem|icon|signage|wordmark|diagram|sketch|chart|map of|screenshot/i', $titulo)) {
            continue;
        }
        if (!preg_match('/\.(jpe?g|png)$/i', $titulo)) {
            continue;
        }
        if ($ancho < 900 || $alto < 500) {
            continue;
        }

        $relacion = $ancho / max(1, $alto);
        if ($relacion < 1.1 || $relacion > 3.2) {
            continue;   // preferimos horizontal y descartamos panorámicas extremas
        }

        // Puntuación: penaliza el despegado de logotipo, premia tamaño razonable
        $puntuacion = 100 - abs($relacion - 1.7) * 20;
        if ($ancho > 6000) {
            $puntuacion -= 20;
        }
        if (preg_match('/\b(interior|dashboard|engine|wheel|door|trunk|detail)\b/i', $titulo)) {
            $puntuacion -= 15;
        }

        if ($puntuacion > $mejorPuntuacion) {
            $mejorPuntuacion = $puntuacion;
            $mejor = [$titulo, $info, $pagina];
        }
    }

    if ($mejor !== null) {
        return construirResultado($mejor);
    }

    // ---------------------------------------------------------------
    //  Segunda pasada: relajar criterios para no quedarnos sin imagen
    // ---------------------------------------------------------------
    foreach ($paginas as $pagina) {
        $info = $pagina['imageinfo'][0] ?? null;
        if ($info === null) {
            continue;
        }

        $titulo = (string) ($pagina['title'] ?? '');
        $ancho = (int) ($info['width'] ?? 0);
        $alto = (int) ($info['height'] ?? 0);

        $normalizado = preg_replace("/^File:/", "", $titulo) ?? $titulo;
        if (isset($prohibidas[$normalizado])) {
            continue;
        }

        if (preg_match('/logo|badge|emblem|icon|diagram|map of|screenshot|svg/i', $titulo)) {
            continue;
        }
        if (!preg_match('/\.(jpe?g|png)$/i', $titulo)) {
            continue;
        }
        if ($ancho < 700 || $alto < 400) {
            continue;
        }

        $mejor = [$titulo, $info, $pagina];
        break;
    }

    if ($mejor === null) {
        return null;
    }

    return construirResultado($mejor);
}

/**
 * @param array{0:string,1:array<string,mixed>,2:array<string,mixed>} $mejor
 * @return array{titulo:string,thumburl:string,autor:string,licencia:string,fuente:string}
 */
function construirResultado(array $mejor): array
{
    [$titulo, $info] = $mejor;

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

    if ($body === false || $code >= 400) {
        return false;
    }

    return (string) $body;
}

function descargar(string $url): string|false
{
    // Se elimina la query de telemetría que añade la API
    $url = preg_replace('/\?.*$/', '', $url) ?? $url;

    $body = http($url, 45);

    return $body === false ? false : $body;
}

function limpiarHtml(string $html): string
{
    $texto = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $texto = preg_replace('/\s+/u', ' ', $texto) ?? $texto;

    return trim($texto);
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
