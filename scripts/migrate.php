<?php

/**
 * =====================================================================
 *  MotorPress · Migraciones
 * =====================================================================
 *
 *   php scripts/migrate.php              aplica las migraciones pendientes
 *   php scripts/migrate.php --status     sólo muestra el estado
 *   php scripts/migrate.php --dry-run    no escribe nada
 *
 * Utiliza las credenciales de mantenimiento de .env (DB_ROOT_*), nunca
 * el usuario de aplicación, porque las migraciones ejecutan DDL.
 * =====================================================================
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Console\Migrator;

$opciones = cli_args($argv, ['status', 'dry-run', 'help']);

if (isset($opciones['help'])) {
    echo <<<'TXT'
MotorPress · Migraciones

  php scripts/migrate.php             aplica las pendientes
  php scripts/migrate.php --status    lista el estado
  php scripts/migrate.php --dry-run   no ejecuta nada

TXT;
    exit(0);
}

$migrator = new Migrator();

echo "┌──────────────────────────────────────────────┐\n";
echo "│  MotorPress · Migraciones                    │\n";
echo "└──────────────────────────────────────────────┘\n";

try {
    $aplicadas = $migrator->status();
} catch (\Throwable $e) {
    fwrite(STDERR, "\n  ✗ No se pudo leer el estado: {$e->getMessage()}\n");
    fwrite(STDERR,    "    ¿Existe la tabla `migrations`? Ejecuta scripts/install.php\n\n");
    exit(1);
}

$pendientes = array_diff_key($migrator->files(), $aplicadas);

if (isset($opciones['status'])) {
    echo "\n  Migraciones registradas: " . count($aplicadas) . "\n";
    foreach ($migrator->files() as $archivo => $checksum) {
        if (isset($aplicadas[$archivo])) {
            $estado = $aplicadas[$archivo]['checksum'] === $checksum
                ? "\033[32m✔ aplicada\033[0m"
                : "\033[33m! modificada\033[0m";
            printf(
                "  %-42s %s  %s\n",
                $archivo,
                $estado,
                $aplicadas[$archivo]['aplicada_en']
            );
        } else {
            printf("  %-42s \033[36m… pendiente\033[0m\n", $archivo);
        }
    }
    echo "\n";
    exit(0);
}

if ($pendientes === []) {
    echo "\n  \033[32m✔\033[0m No hay migraciones pendientes.\n\n";
    exit(0);
}

if (isset($opciones['dry-run'])) {
    echo "\n  Migraciones pendientes (dry-run):\n";
    foreach ($pendientes as $archivo => $_) {
        echo "    · {$archivo}\n";
    }
    echo "\n";
    exit(0);
}

try {
    $resultado = $migrator->migrate(static function (string $msg): void {
        echo "  · {$msg}\n";
    });
} catch (\Throwable $e) {
    fwrite(STDERR, "\n  ✗ {$e->getMessage()}\n\n");
    fwrite(STDERR, "    Si una migración falla a medias (el DDL confirma solo),\n");
    fwrite(STDERR, "    corrige el error y vuelve a ejecutarla.\n\n");
    exit(1);
}

printf(
    "\n  \033[32m✔\033[0m Migraciones aplicadas: %d · ya estaban: %d\n\n",
    count($resultado['aplicadas']),
    $resultado['omitidas']
);
