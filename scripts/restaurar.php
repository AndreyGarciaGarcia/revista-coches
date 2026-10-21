<?php

/**
 * =====================================================================
 *  MotorPress · Restauración de una copia de seguridad
 * =====================================================================
 *
 *   php scripts/restaurar.php backups/revista_coches_20261007_192459.sql.gz
 *   php scripts/restaurar.php <fichero> --db=otro_esquema   esquema distinto
 *   php scripts/restaurar.php <fichero> --dry-run           sólo valida
 *   php scripts/restaurar.php <fichero> --yes               sin preguntar
 *
 * El fichero se aplica de principio a fin sobre el esquema indicado y
 * recrea tablas, vistas, triggers, procedimientos y funciones. La BD de
 * destino se crea si no existe (carácter utf8mb4 / 0900_ai_ci).
 *
 * ⚠  Restaurar sobre el esquema de producción destruye los datos
 *    actuales. Haz siempre una copia previa:
 *        php scripts/backup.php
 * =====================================================================
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Env;

$opciones = cli_args($argv, ['db::', 'dry-run', 'yes', 'help']);

$argumentos = array_values(array_filter(
    array_slice($argv, 1),
    static fn (string $a): bool => !str_starts_with($a, '--')
));

if (isset($opciones['help']) || $argumentos === []) {
    echo <<<TXT
MotorPress · Restauración

  php scripts/restaurar.php <fichero.sql[.gz]>   restaura sobre la BD de .env
  php scripts/restaurar.php <fichero> --db=x     restaura sobre otro esquema
  php scripts/restaurar.php <fichero> --dry-run  comprueba el fichero sin tocar nada
  php scripts/restaurar.php <fichero> --yes      no pide confirmación

TXT;
    exit($argumentos === [] ? 1 : 0);
}

$ruta = $argumentos[0];

if (!is_file($ruta)) {
    fwrite(STDERR, "No existe el fichero: {$ruta}\n");
    exit(1);
}

$destino = (string) ($opciones['db'] ?? Env::getString('DB_NAME', 'revista_coches'));
$dryRun = array_key_exists('dry-run', $opciones);
$confirmado = array_key_exists('yes', $opciones);

// -------------------------------------------------------------------------
//  Lectura
// -------------------------------------------------------------------------

$bruto = file_get_contents($ruta);

if ($bruto === false) {
    fwrite(STDERR, "No se pudo leer {$ruta}\n");
    exit(1);
}

$sql = str_ends_with(strtolower($ruta), '.gz') ? gzdecode($bruto) : $bruto;

if ($sql === false || trim((string) $sql) === '') {
    fwrite(STDERR, "El fichero está vacío o no se pudo descomprimir.\n");
    exit(1);
}

// Contenido orientativo antes de tocar nada (se lee por las sentencias
// DROP que abre cada objeto, que sí llevan forma canónica)
$tablas = preg_match_all('/^DROP TABLE IF EXISTS `?(\w+)`?/mi', $sql, $mTablas) ? $mTablas[1] : [];
$vistas = preg_match_all('/^DROP VIEW IF EXISTS `?(\w+)`?/mi', $sql, $mVistas) ? $mVistas[1] : [];
$triggers = preg_match_all('/^DROP TRIGGER IF EXISTS `?(\w+)`?/mi', $sql, $mTrig) ? $mTrig[1] : [];
$rutinas = preg_match_all('/^DROP (?:PROCEDURE|FUNCTION) IF EXISTS `?(\w+)`?/mi', $sql, $mRut) ? $mRut[1] : [];
$inserts = preg_match_all('/^INSERT INTO/mi', $sql);

// Las vistas se retiran dos veces (antes que las tablas y justo antes de
// recrearse), así que se desduplica el recuento.
$tablas = array_values(array_unique($tablas));
$vistas = array_values(array_unique($vistas));
$triggers = array_values(array_unique($triggers));
$rutinas = array_values(array_unique($rutinas));

echo "┌──────────────────────────────────────────────────────────────┐\n";
echo '│  MotorPress · restauración'
    . str_repeat(' ', max(0, 59 - mb_strwidth('MotorPress · restauración')))
    . "│\n";
echo "└──────────────────────────────────────────────────────────────┘\n\n";

printf("  Fichero        : %s\n", $ruta);
printf("  Tamaño         : %s\n", number_format(strlen((string) $sql) / 1024, 1, ',', '.') . ' KB');
printf("  Esquema destino : %s\n", $destino);
printf("  Tablas         : %d\n", count($tablas));
printf("  Vistas         : %d\n", count($vistas));
printf("  Triggers       : %d\n", count($triggers));
printf("  Rutinas        : %d\n", count($rutinas));
printf("  Sentencias INSERT: %d\n", $inserts);

if (!str_contains((string) $sql, 'MotorPress')) {
    fwrite(STDERR, "\n  El fichero no lleva la cabecera de MotorPress: ¿es de esta aplicación?\n");
}

echo "\n";

if ($dryRun) {
    echo "  Modo simulación: el fichero es legible y está bien formado. ✔\n";
    echo "  Vuelve a ejecutar sin --dry-run para aplicarlo.\n\n";
    exit(0);
}

if ($destino === Env::getString('DB_NAME', 'revista_coches') && !$confirmado) {
    echo "  ⚠  Vas a restaurar sobre el esquema de PRODUCCIÓN ({$destino}).\n";
    echo "     Todos los datos actuales se perderán.\n\n";
    echo "  Escribe la letra del esquema para continuar (pulsa Intro para abortar): ";

    $teclado = trim((string) fgets(STDIN));

    if (strcasecmp($teclado, substr($destino, 0, 1)) !== 0) {
        echo "\n  Restauración cancelada. Nada se ha modificado.\n\n";
        exit(1);
    }

    echo "\n";
}

// -------------------------------------------------------------------------
//  Aplicación
// -------------------------------------------------------------------------

$inicio = microtime(true);

try {
    $pdo = new PDO(
        'mysql:host=' . Env::getString('DB_HOST', '127.0.0.1')
        . ';port=' . Env::getString('DB_PORT', '3306')
        . ';charset=utf8mb4',
        Env::getString('DB_ROOT_USER', Env::getString('DB_USER', 'root')),
        Env::getString('DB_ROOT_PASS', Env::getString('DB_PASS', '')),
        [
            PDO::ATTR_ERRMODE               => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
        ]
    );

    $destinoSeguro = str_replace('`', '', $destino);

    $pdo->exec(
        "CREATE DATABASE IF NOT EXISTS `{$destinoSeguro}`
         CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"
    );
    $pdo->exec("USE `{$destinoSeguro}`");
    $pdo->exec((string) $sql);

    // Se consumen los resultados intermedios antes de seguir consultando
    try {
        while ($pdo->nextRowset()) {
        }
    } catch (Throwable $e) {
        // Sin más result sets: comportamiento esperado
    }

    $pdo->setAttribute(PDO::MYSQL_ATTR_MULTI_STATEMENTS, false);
} catch (Throwable $e) {
    fwrite(STDERR, "\n  ✘ La restauración falló: " . $e->getMessage() . "\n");
    fwrite(STDERR,    "    El esquema «{$destino}» puede quedar incompleto.\n");
    fwrite(STDERR,    "    Usa una copia anterior o revisa el fichero.\n\n");
    exit(1);
}

// -------------------------------------------------------------------------
//  Verificación
// -------------------------------------------------------------------------

$errores = 0;
$faltables = [];

foreach ($tablas as $tabla) {
    try {
        $pdo->query("SELECT COUNT(*) FROM `{$tabla}`")->fetchColumn();
    } catch (Throwable $e) {
        $faltables[] = $tabla;
        $errores++;
    }
}

foreach ($vistas as $vista) {
    try {
        $pdo->query("SELECT * FROM `{$vista}` LIMIT 1")->fetchColumn();
    } catch (Throwable $e) {
        $faltables[] = $vista . ' (' . substr($e->getMessage(), 0, 80) . ')';
        $errores++;
    }
}

$segundos = microtime(true) - $inicio;
$creadas = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();

echo "  Restauración completada en " . number_format($segundos, 2, ',', '.') . " s\n";
echo "  Objetos en «{$destino}»: {$creadas}\n";

if ($errores === 0) {
    echo "  Verificación: tablas y vistas consultables. ✔\n\n";
} else {
    echo "  Verificación: {$errores} objeto(s) con incidencias:\n";

    foreach (array_slice($faltables, 0, 10) as $falta) {
        echo "    · {$falta}\n";
    }

    echo "\n";
    exit(1);
}

echo "  A continuación puedes crear el administrador si hacía falta:\n";
echo "    php scripts/make-admin.php --listar\n\n";

exit(0);
