<?php

/**
 * =====================================================================
 *  MotorPress · Prueba de humo de rutas
 * =====================================================================
 *
 *   php scripts/smoke.php              sólo GET (páginas y plantillas)
 *   php scripts/smoke.php --verbose    muestra el cuerpo de los fallos
 *   php scripts/smoke.php --sin-admin  omite el panel de administración
 *
 * Recorre todas las rutas registradas, las ejecuta contra la base de
 * datos real y señala cualquier respuesta 5xx, cualquier aviso de PHP
 * (Warning/Notice/Deprecated) y cualquier petición que devuelva una
 * página vacía. Sirve como red de seguridad antes de un despliegue.
 * =====================================================================
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\App;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;

$opciones = cli_args($argv, ['verbose', 'sin-admin', 'help']);

if (isset($opciones['help'])) {
    echo <<<TXT
MotorPress · Prueba de humo

  php scripts/smoke.php             recorre todas las rutas GET
  php scripts/smoke.php --verbose   imprime el detalle de cada fallo
  php scripts/smoke.php --sin-admin no prueba el panel de administración

TXT;
    exit(0);
}

$verbose = array_key_exists('verbose', $opciones);
$conAdmin = !array_key_exists('sin-admin', $opciones);

// Marcas de agua del registro de errores: lo que se escriba durante la
// prueba durante a avisos de PHP que la propia página no muestra.
$logErrores = dirname(__DIR__) . '/storage/logs/errors.log';
$bytesLog = is_file($logErrores) ? (int) filesize($logErrores) : 0;

// -------------------------------------------------------------------------
//  Arranque (sin imprimir nada todavía: la sesión no admite salida previa)
// -------------------------------------------------------------------------

ob_start();

$app = App::boot();
$router = $app->router();

$reflexion = new ReflectionClass($router);
$propiedad = $reflexion->getProperty('routes');
$propiedad->setAccessible(true);

/** @var array<int,array{method:string,pattern:string,compiled:string,handler:array|string,middleware:string[],name:?string}> $rutas */
$rutas = $propiedad->getValue($router);

$db = Database::get();

$scalar = static fn (string $sql, array $p = []): mixed => $db->scalar($sql, $p);

$ejemplos = [
    'noticia.slug'   => (string) $scalar("SELECT slug FROM noticias WHERE borrado_en IS NULL AND estado = 'publicado' ORDER BY id LIMIT 1"),
    'noticia.id'     => (int)   ($scalar("SELECT id FROM noticias WHERE borrado_en IS NULL ORDER BY id LIMIT 1") ?: 1),
    'coche.slug'     => (string) $scalar("SELECT slug FROM coches WHERE borrado_en IS NULL ORDER BY id LIMIT 1"),
    'coche.id'       => (int)   ($scalar("SELECT id FROM coches WHERE borrado_en IS NULL ORDER BY id LIMIT 1") ?: 1),
    'marca.slug'     => (string) $scalar("SELECT slug FROM marcas ORDER BY id LIMIT 1"),
    'categoria.slug' => (string) $scalar("SELECT slug FROM categorias ORDER BY id LIMIT 1"),
    'etiqueta.slug'  => (string) $scalar("SELECT slug FROM etiquetas ORDER BY id LIMIT 1"),
    'comentario.id'  => (int)   ($scalar("SELECT id FROM comentarios ORDER BY id DESC LIMIT 1") ?: 1),
    'mensaje.id'     => (int)   ($scalar("SELECT id FROM mensajes_contacto ORDER BY id DESC LIMIT 1") ?: 1),
    'usuario.id'     => (int)   ($scalar("SELECT id FROM usuarios WHERE borrado_en IS NULL ORDER BY id LIMIT 1") ?: 1),
];

$adminId = (int) ($scalar('SELECT id FROM usuarios WHERE email = :e AND borrado_en IS NULL', [
    'e' => 'admin@motorpress.dev',
]) ?: 0);

// Consultas de prueba para las rutas con parámetros de query
$consultas = [
    'coches'        => '?pagina=1&orden=precio-asc&marcas=' . $scalar('SELECT id FROM marcas ORDER BY id LIMIT 1'),
    'noticias'      => '?pagina=1',
    'buscar'        => '?q=coche',
    'comparador'    => '?ids=' . $ejemplos['coche.id'] . ',' . max(1, $ejemplos['coche.id'] - 1),
    'categoria'     => '',
    'api/v1/coches' => '?page=1&per_page=5',
    'api/v1/noticias' => '?page=1&per_page=5',
];

ob_end_clean();

// -------------------------------------------------------------------------
//  Sesión de administrador (el panel exige nivel 90+ y que la cuenta
//  esté en la lista blanca ADMIN_EMAIL; aquí se entra por consola)
// -------------------------------------------------------------------------

if ($conAdmin && $adminId > 0) {
    Auth::login($adminId);
}

// -------------------------------------------------------------------------
//  Sustitución de marcadores {param}
// -------------------------------------------------------------------------

/**
 * @param array<int,array{method:string,pattern:string}> $rutas
 * @return array<int,string>
 */
function rutasDe(array $rutas, string $metodo, string $prefijo): array
{
    $salida = [];

    foreach ($rutas as $ruta) {
        if ($ruta['method'] !== $metodo) {
            continue;
        }

        if ($prefijo !== '' && !str_starts_with($ruta['pattern'], $prefijo)) {
            continue;
        }

        $salida[] = $ruta['pattern'];
    }

    return array_values(array_unique($salida));
}

function muestraPara(string $patron, array $ejemplos): string
{
    $contexto = explode('/', $patron)[0];
    $contexto2 = isset(explode('/', $patron)[1]) ? $contexto . '/' . explode('/', $patron)[1] : $contexto;

    $mapa = [];

    if (str_starts_with($patron, 'admin/noticias')) {
        $mapa = ['id' => $ejemplos['noticia.id']];
    } elseif (str_starts_with($patron, 'admin/coches')) {
        $mapa = ['id' => $ejemplos['coche.id']];
    } elseif (str_starts_with($patron, 'admin/comentarios')) {
        $mapa = ['id' => $ejemplos['comentario.id']];
    } elseif (str_starts_with($patron, 'admin/mensajes')) {
        $mapa = ['id' => $ejemplos['mensaje.id']];
    } elseif (str_starts_with($patron, 'admin/usuarios')) {
        $mapa = ['id' => $ejemplos['usuario.id']];
    } elseif ($contexto2 === 'api/v1/coches') {
        $mapa = ['id' => $ejemplos['coche.id']];
    } elseif ($contexto2 === 'api/v1/noticias') {
        $mapa = ['id' => $ejemplos['noticia.id']];
    } elseif ($contexto === 'noticias') {
        $mapa = ['slug' => $ejemplos['noticia.slug']];
    } elseif ($contexto === 'coches') {
        $mapa = ['slug' => $ejemplos['coche.slug']];
    } elseif ($contexto === 'marcas') {
        $mapa = ['slug' => $ejemplos['marca.slug']];
    } elseif ($contexto === 'categoria') {
        $mapa = ['slug' => $ejemplos['categoria.slug']];
    } elseif ($contexto === 'etiqueta') {
        $mapa = ['slug' => $ejemplos['etiqueta.slug']];
    }

    return (string) preg_replace_callback(
        '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::[^}]+)?\}/',
        static fn (array $m): string => (string) ($mapa[$m[1]] ?? '1'),
        $patron
    );
}

// -------------------------------------------------------------------------
//  Ejecución
// -------------------------------------------------------------------------

$probar = [];

foreach (rutasDe($rutas, 'GET', '') as $patron) {
    if (!$conAdmin && str_starts_with($patron, 'admin')) {
        continue;
    }

    $ruta = muestraPara($patron, $ejemplos);
    $query = $consultas[$patron] ?? '';
    $probar[] = ['GET', $ruta, $query, $patron];
}

$titulo = 'MotorPress · prueba de humo (' . count($probar) . ' rutas GET)';
$relleno = str_repeat(' ', max(0, 59 - mb_strwidth($titulo)));

echo "┌──────────────────────────────────────────────────────────────┐\n";
echo '│  ' . $titulo . $relleno . "│\n";
echo "└──────────────────────────────────────────────────────────────┘\n\n";

$fallos = 0;
$avisos = 0;

foreach ($probar as [$metodo, $ruta, $query, $patron]) {
    $uri = '/' . trim($ruta, '/') . $query;
    $uri = $uri === '' ? '/' : $uri;

    $server = [
        'REQUEST_METHOD' => $metodo,
        'REQUEST_URI'    => $uri,
        'HTTP_HOST'      => 'localhost',
        'REMOTE_ADDR'    => '127.0.0.1',
        'HTTP_ACCEPT'    => str_contains($patron, 'api/') ? 'application/json' : 'text/html',
    ];

    $request = new Request($server, [], [], []);

    ob_start();

    try {
        $respuesta = $app->dispatch($request);
        $cuerpo = $respuesta->body();
        $estado = $respuesta->getStatus();
    } catch (Throwable $e) {
        $cuerpo = $e->getMessage();
        $estado = 599;
    }

    $emitido = (string) ob_get_clean();

    $problemas = [];

    // Rutas que responden 404 a propósito cuando el registro está
    // cerrado (REGISTRO_ABIERTO=false): nadie puede crear cuentas.
    $cerrada = in_array($patron, ['registro'], true)
        && !\App\Core\Env::getBool('REGISTRO_ABIERTO', false);

    if ($estado >= 500) {
        $problemas[] = 'HTTP ' . $estado;
    }

    if ($estado === 404 && !$cerrada) {
        $problemas[] = '404';
    }

    if ($cerrada && $estado !== 404) {
        $problemas[] = 'registro abierto';
    }

    if (trim($cuerpo) === '' && $estado < 300) {
        $problemas[] = 'cuerpo vacío';
    }

    $rastro = $cuerpo . "\n" . $emitido;

    foreach (['Warning:', 'Notice:', 'Deprecated:', 'Fatal error:', 'Undefined variable', 'Undefined array key', 'Undefined property'] as $clave) {
        if (str_contains($rastro, $clave)) {
            $problemas[] = 'PHP: ' . $clave;
            break;
        }
    }

    if (str_contains($rastro, 'SQLSTATE[')) {
        $problemas[] = 'SQL';
    }

    if ($problemas === []) {
        printf("  \033[32m%-4s\033[0m %-46s %s\n", $metodo, $uri, $estado);
        continue;
    }

    $fallos++;
    printf("  \033[31m%-4s\033[0m %-46s %s  \033[31m%s\033[0m\n", $metodo, $uri, $estado, implode(', ', $problemas));
    $avisos += count($problemas);

    if ($verbose) {
        $resumen = trim(preg_replace('/\s+/', ' ', strip_tags($emitido . ' ' . $cuerpo)) ?? '');
        echo '        ' . substr($resumen, 0, 600) . "\n";
    }
}

echo "\n";

// -------------------------------------------------------------------------
//  Registros escritos durante la prueba (avisos que la página no muestra)
// -------------------------------------------------------------------------

$entradas = [];

if (is_file($logErrores)) {
    $mano = fopen($logErrores, 'rb');

    if ($mano !== false) {
        fseek($mano, $bytesLog);
        $nuevo = (string) stream_get_contents($mano);
        fclose($mano);

        foreach (preg_split('/\R/', $nuevo) ?: [] as $linea) {
            if (preg_match('/\]\s+\S+\s+(WARNING|CRITICAL|ERROR)\s*\|/', $linea)) {
                $entradas[] = trim($linea);
            }
        }
    }
}

if ($entradas !== []) {
    $avisos += count($entradas);
    printf("  \033[33m%d aviso(s) de PHP registrado(s) durante la prueba:\033[0m\n\n", count($entradas));

    foreach (array_slice($entradas, 0, $verbose ? count($entradas) : 8) as $entrada) {
        echo '    ' . substr($entrada, 0, 220) . "\n";
    }

    if (!$verbose && count($entradas) > 8) {
        printf("    … y %d más (usa --verbose)\n", count($entradas) - 8);
    }

    echo "\n";
    $fallos++;
}

if ($fallos === 0) {
    echo "  Todas las rutas responden correctamente. ✔\n\n";
    exit(0);
}

printf("  %d ruta(s) con incidencias (%d señales). ✘\n\n", $fallos, $avisos);
echo "  Relanza con --verbose para ver el detalle.\n\n";
exit(1);
