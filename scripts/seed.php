<?php

/**
 * =====================================================================
 *  MotorPress · Datos de partida
 * =====================================================================
 *
 *   php scripts/seed.php          carga los datos iniciales
 *   php scripts/seed.php --force  los fuerza (borra antes)
 *
 * El sembrador es idempotente: se puede ejecutar tantas veces como haga
 * falta sin duplicar registros. Útil tras descargar las fotografías para
 * vincularlas a los vehículos y artículos.
 * =====================================================================
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Console\Migrator;
use App\Console\Seeder;

$opciones = cli_args($argv, ['force', 'help']);

if (isset($opciones['help'])) {
    echo <<<'TXT'
MotorPress · Datos de partida

  php scripts/seed.php           carga (o completa) los datos iniciales
  php scripts/seed.php --force   borra el contenido sembrado antes de crearlo

TXT;
    exit(0);
}

echo "┌──────────────────────────────────────────────┐\n";
echo "│  MotorPress · Datos de partida               │\n";
echo "└──────────────────────────────────────────────┘\n";

if (isset($opciones['force'])) {
    if (!function_exists('readline')) {
        fwrite(STDERR, "\n  ✗ --force requiere readline\n\n");
        exit(1);
    }
    $respuesta = readline("\n  ⚠ Borrará noticias, coches y comentarios. ¿Seguro? [s/N] ");
    if (strtolower(trim((string) $respuesta)) !== 's') {
        echo "\n  Operación cancelada.\n\n";
        exit(0);
    }

    $pdo = \App\Core\Migrator::privilegedConnection();
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ([
        'noticias_coches', 'noticias_etiquetas', 'comentarios', 'noticias',
        'coches_videos', 'valoraciones', 'precios_historicos',
        'coches_imagenes', 'coches',
    ] as $tabla) {
        $pdo->exec("DELETE FROM `{$tabla}`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    echo "\n  \033[33m!\033[0m Contenido editorial eliminado.\n";
}

$inicio = microtime(true);

try {
    $resultado = (new Seeder(static function (string $msg): void {
        echo "  \033[32m✔\033[0m {$msg}\n";
    }))->run();
} catch (\Throwable $e) {
    fwrite(STDERR, "\n  ✗ {$e->getMessage()}\n\n");
    exit(1);
}

printf(
    "\n  \033[1mResumen\033[0m  %d roles · %d usuarios · %d categorías · %d marcas\n"
    . "            %d vehículos · %d artículos · %d imágenes · %d comentarios\n"
    . "            %d fotos de galería · %d vídeos\n\n",
    $resultado['roles'],
    $resultado['usuarios'],
    $resultado['categorias'],
    $resultado['marcas'],
    $resultado['coches'],
    $resultado['noticias'],
    $resultado['imagenes'],
    $resultado['comentarios'],
    $resultado['fotos'] ?? 0,
    $resultado['videos'] ?? 0
);

printf("  Listo en %.1f s\n\n", microtime(true) - $inicio);
