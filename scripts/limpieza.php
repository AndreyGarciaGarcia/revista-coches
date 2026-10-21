<?php

/**
 * =====================================================================
 *  MotorPress · Mantenimiento programable
 * =====================================================================
 *
 *   php scripts/limpieza.php                    limpieza estándar
 *   php scripts/limpieza.php --dias=30          retención de 30 días
 *   php scripts/limpieza.php --dry-run          sólo informa, no borra
 *   php scripts/limpieza.php --sin-cache        conserva la caché
 *   php scripts/limpieza.php --sin-sesiones     conserva las sesiones
 *   php scripts/limpieza.php --optimizar        OPTIMIZE de las tablas
 *   php scripts/limpieza.php --info             estado sin tocar nada
 *
 * Qué hace:
 *   1. Sesiones caducadas de storage/sessions
 *   2. Ficheros de caché obsoletos de storage/cache
 *   3. Registros de log antiguos (se comprimen y se archivan)
 *   4. Ventanas de limitación de peticiones ya vencidas
 *   5. Intentos de acceso fallidos de cuentas bloqueadas hace > 1 día
 *   6. Filas huérfanas de `imagenes` (sin fichero en disco)
 *
 * Pensado para ejecutarse con la tarea programada del sistema:
 *   0 4 * * *  php C:\...\scripts\limpieza.php >> storage\logs\limpieza.log
 * =====================================================================
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Database;
use App\Core\Env;

$opciones = cli_args($argv, ['dias::', 'logs::', 'dry-run', 'sin-cache', 'sin-sesiones', 'optimizar', 'info', 'help']);

if (isset($opciones['help'])) {
    echo <<<TXT
MotorPress · Mantenimiento

  php scripts/limpieza.php                limpieza estándar
  php scripts/limpieza.php --dias=30      retención de 30 días
  php scripts/limpieza.php --logs=14      los log se conservan 14 días
  php scripts/limpieza.php --dry-run      informa sin borrar nada
  php scripts/limpieza.php --info         estado de storage y de la BD
  php scripts/limpieza.php --optimizar    además OPTIMIZE de las tablas

TXT;
    exit(0);
}

$dias = max(1, (int) ($opciones['dias'] ?? 30));
$diasLog = max(1, (int) ($opciones['logs'] ?? 14));
$simulacro = array_key_exists('dry-run', $opciones);
$sinCache = array_key_exists('sin-cache', $opciones);
$sinSesiones = array_key_exists('sin-sesiones', $opciones);
$soloInfo = array_key_exists('info', $opciones);
$db = Database::get();

/**
 * @return array{ficheros:int,bytes:int}
 */
function pesarDirectorio(string $ruta): array
{
    if (!is_dir($ruta)) {
        return ['ficheros' => 0, 'bytes' => 0];
    }

    $ficheros = 0;
    $bytes = 0;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($ruta, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fichero) {
        if ($fichero->isFile()) {
            $ficheros++;
            $bytes += (int) $fichero->getSize();
        }
    }

    return ['ficheros' => $ficheros, 'bytes' => $bytes];
}

function legible(int $bytes): string
{
    $unidades = ['B', 'KB', 'MB', 'GB'];
    $indice = 0;
    $valor = (float) $bytes;

    while ($valor >= 1024 && $indice < count($unidades) - 1) {
        $valor /= 1024;
        $indice++;
    }

    return number_format($valor, $valor < 10 && $indice > 0 ? 1 : 0, ',', '.') . ' ' . $unidades[$indice];
}

$cache = pesarDirectorio(BASE_PATH . '/storage/cache');
$sesiones = pesarDirectorio(BASE_PATH . '/storage/sessions');
$logs = pesarDirectorio(BASE_PATH . '/storage/logs');
$imagenes = pesarDirectorio(BASE_PATH . '/public/assets/img');

// -------------------------------------------------------------------------
//  Estado
// -------------------------------------------------------------------------

echo "┌──────────────────────────────────────────────────────────────┐\n";
echo '│  MotorPress · mantenimiento'
    . str_repeat(' ', max(0, 59 - mb_strwidth('MotorPress · mantenimiento')))
    . "│\n";
echo "└──────────────────────────────────────────────────────────────┘\n\n";

printf(
    "  storage/cache     %6d ficheros  %10s\n",
    $cache['ficheros'],
    legible($cache['bytes'])
);
printf(
    "  storage/sessions  %6d ficheros  %10s\n",
    $sesiones['ficheros'],
    legible($sesiones['bytes'])
);
printf(
    "  storage/logs      %6d ficheros  %10s\n",
    $logs['ficheros'],
    legible($logs['bytes'])
);
printf(
    "  public/assets/img %6d ficheros  %10s\n",
    $imagenes['ficheros'],
    legible($imagenes['bytes'])
);

$pendientes = (int) $db->scalar(
    'SELECT COUNT(*) FROM migrations WHERE aplicada_en IS NULL'
);
$totales = (int) $db->scalar('SELECT COUNT(*) FROM migrations');

printf(
    "  migraciones       %6d aplicadas / %d\n",
    $totales - $pendientes,
    $totales
);

if ($soloInfo) {
    echo "\n";
    exit($pendientes > 0 ? 1 : 0);
}

echo "\n  Retención: {$dias} días (log: {$diasLog})\n";
echo $simulacro ? "  Modo: SIMULACIÓN (no se borra nada)\n" : "  Modo: ejecución\n";
echo "\n";

$limpiado = 0;
$borrado = 0;

// -------------------------------------------------------------------------
//  1 y 2 · Caché y sesiones caducadas
// -------------------------------------------------------------------------

$corte = time() - ($dias * 86400);

foreach ([
    ['sesiones', BASE_PATH . '/storage/sessions', !$sinSesiones],
    ['caché', BASE_PATH . '/storage/cache', !$sinCache],
] as [$nombre, $ruta, $activo]) {
    if (!$activo || !is_dir($ruta)) {
        continue;
    }

    $ficheros = array_values(array_filter(
        (array) glob($ruta . '/*'),
        static fn (string $f): bool => is_file($f) && filemtime($f) < $corte
    ));

    $peso = array_sum(array_map(static fn (string $f): int => (int) filesize($f), $ficheros));

    foreach ($ficheros as $fichero) {
        if (!$simulacro) {
            @unlink($fichero);
        }
    }

    $limpiado += count($ficheros);
    $borrado += $peso;

    printf(
        "  %-18s %6d fichero(s)  %10s %s\n",
        $nombre,
        count($ficheros),
        legible($peso),
        $simulacro ? '(simulado)' : ''
    );
}

// -------------------------------------------------------------------------
//  3 · Rotación de logs
// -------------------------------------------------------------------------

$carpetaLogs = BASE_PATH . '/storage/logs';
$antiguoLog = time() - ($diasLog * 86400);
$comprimidos = 0;

foreach ((array) glob($carpetaLogs . '/*.log') as $fichero) {
    if (!is_file($fichero) || filemtime($fichero) >= $antiguoLog) {
        continue;
    }

    $destino = $fichero . '.' . date('Ymd', filemtime($fichero)) . '.gz';

    if ($simulacro) {
        printf("  %-18s %6s          %10s (simulado)\n", 'log', basename($fichero), legible((int) filesize($fichero)));
        continue;
    }

    $origen = fopen($fichero, 'rb');

    if ($origen === false) {
        continue;
    }

    $salida = gzopen($destino, 'wb6');

    if ($salida === false) {
        fclose($origen);
        continue;
    }

    while (!feof($origen)) {
        $bloque = fread($origen, 1 << 20);

        if ($bloque !== false && $bloque !== '') {
            gzwrite($salida, $bloque);
        }
    }

    fclose($origen);
    gzclose($salida);

    // Se deja el fichero actual vacío para que el manejador siga escribiendo
    file_put_contents($fichero, '');
    touch($fichero);
    $comprimidos++;
}

printf(
    "  %-18s %6d rotado(s)\n",
    'log rotado',
    $comprimidos
);

// Archivos comprimidos con más de 90 días
$podridos = 0;
foreach ((array) glob($carpetaLogs . '/*.gz') as $viejo) {
    if (is_file($viejo) && filemtime($viejo) < time() - 90 * 86400) {
        if ($simulacro) {
            $podridos++;
            continue;
        }

        if (@unlink($viejo)) {
            $podridos++;
        }
    }
}

printf("  %-18s %6d archivado(s) > 90 días\n", 'log archivo', $podridos);

// -------------------------------------------------------------------------
//  4 · Rate limiting vencido
// -------------------------------------------------------------------------

try {
    $vencidos = $db->count(
        'SELECT COUNT(*) FROM rate_limits WHERE ventana_hasta < DATE_SUB(NOW(), INTERVAL 1 DAY)'
    );

    if ($vencidos > 0 && !$simulacro) {
        $db->execute(
            'DELETE FROM rate_limits WHERE ventana_hasta < DATE_SUB(NOW(), INTERVAL 1 DAY)'
        );
    }

    printf(
        "  %-18s %6d ventana(es) vencida(s)%s\n",
        'rate_limits',
        $vencidos,
        $simulacro ? ' (simulado)' : ''
    );
} catch (Throwable $e) {
    printf("  %-18s no disponible (%s)\n", 'rate_limits', $e->getCode());
}

// -------------------------------------------------------------------------
//  5 · Cuentas con la ventana de bloqueo caducada
// -------------------------------------------------------------------------

try {
    $reiniciadas = $db->count(
        'SELECT COUNT(*) FROM usuarios
          WHERE bloqueado_hasta IS NOT NULL AND bloqueado_hasta < NOW()'
    );

    if ($reiniciadas > 0 && !$simulacro) {
        $db->execute(
            'UPDATE usuarios
                SET bloqueado_hasta = NULL, intentos_fallidos = 0, actualizado_en = NOW()
              WHERE bloqueado_hasta IS NOT NULL AND bloqueado_hasta < NOW()'
        );
    }

    printf(
        "  %-18s %6d cuenta(s) desbloqueada(s)%s\n",
        'usuarios',
        $reiniciadas,
        $simulacro ? ' (simulado)' : ''
    );
} catch (Throwable $e) {
    printf("  %-18s no disponible (%s)\n", 'usuarios', $e->getCode());
}

// -------------------------------------------------------------------------
//  6 · Imágenes huérfanas (registro sin fichero en disco)
// ---------------------------------------------------------------------

try {
    $filas = $db->select('SELECT id, ruta FROM imagenes');
    $sinFichero = [];

    foreach ($filas as $fila) {
        $ruta = BASE_PATH . '/public/' . ltrim((string) $fila['ruta'], '/');

        if (!is_file($ruta)) {
            $sinFichero[] = (int) $fila['id'];
        }
    }

    printf(
        "  %-18s %6d registro(s) sin fichero%s\n",
        'imagenes',
        count($sinFichero),
        $sinFichero !== [] && $simulacro ? ' (simulado)' : ''
    );

    if ($sinFichero !== [] && !$simulacro && Env::getBool('APP_DEBUG', false)) {
        echo '                    ids: ' . implode(', ', array_slice($sinFichero, 0, 20)) . "\n";
    }
} catch (Throwable $e) {
    printf("  %-18s no disponible (%s)\n", 'imagenes', $e->getCode());
}

// -------------------------------------------------------------------------
//  7 · Reorganización opcional
// -------------------------------------------------------------------------

if (array_key_exists('optimizar', $opciones) && !$simulacro) {
    $tablas = $db->tables();

    foreach ($tablas as $tabla) {
        $db->execute('OPTIMIZE TABLE `' . str_replace('`', '', $tabla) . '`');
    }

    printf("  %-18s %6d tabla(s) optimizada(s)\n", 'optimize', count($tablas));
}

// -------------------------------------------------------------------------
//  Informe final
// -------------------------------------------------------------------------

echo "\n";

if ($simulacro) {
    echo "  Simulación terminada: no se ha borrado nada.\n";
    echo "  Repite sin --dry-run para aplicar los cambios.\n\n";
} else {
    printf(
        "  Limpieza terminada: %d fichero(s), %s liberados.\n",
        $limpiado,
        legible($borrado)
    );
    echo "  Ejecuta este script cada día con la tarea programada del sistema.\n\n";
}

exit(0);
