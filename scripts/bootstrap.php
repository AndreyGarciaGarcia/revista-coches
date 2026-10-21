<?php

/**
 * Punto de arranque común a todos los scripts de consola.
 *
 *   require __DIR__ . '/bootstrap.php';
 *
 * Carga el autoloader, el entorno (.env) y las funciones de ayuda
 * globales, igual que hace el front controller de la web.
 */

declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

if (!class_exists(App\Core\Autoloader::class, false)) {
    require BASE_PATH . '/app/Core/Autoloader.php';
    (new App\Core\Autoloader(BASE_PATH . '/app'))->register();
}

App\Core\Env::load(BASE_PATH . '/.env');

require_once BASE_PATH . '/app/helpers.php';

date_default_timezone_set(App\Core\Env::getString('APP_TIMEZONE', 'Europe/Madrid'));
mb_internal_encoding('UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', App\Core\Env::getBool('APP_DEBUG') ? '1' : '0');
ini_set('log_errors', '1');

/**
 * Analiza los argumentos de línea de comandos.
 *
 * A diferencia de getopt(), admite las opciones DESPUÉS de los argumentos
 * posicionales (getopt se detiene en el primero que no empieza por «--»):
 *
 *     php scripts/make-admin.php correo@x.com --rol=root
 *
 * Devuelve el mismo formato que getopt(): las opciones con clave de texto
 * (`--rol=root` -> ['rol' => 'root'], `--yes` -> ['yes' => true]) y los
 * argumentos posicionales con claves numéricas.
 *
 * @param array<int,string> $argv
 * @param array<int,string> $permitidas  lista blanca; vacía = cualquiera
 * @return array<int|string,string|true>
 */
function cli_args(array $argv, array $permitidas = []): array
{
    $salida = [];

    // Los nombres de getopt traen «::» o «:» detrás ('out::'); aquí sólo
    // interesa la raíz de la opción.
    $permitidasNorm = array_map(
        static fn (string $p): string => rtrim($p, ':'),
        $permitidas
    );

    foreach (array_slice($argv, 1) as $arg) {
        if (str_starts_with($arg, '--')) {
            $parte = explode('=', substr($arg, 2), 2);
            $clave = $parte[0];

            if ($permitidasNorm !== [] && !in_array($clave, $permitidasNorm, true)) {
                fwrite(STDERR, "Opción desconocida: --{$clave}  (usa --help)\n");
                exit(1);
            }

            $salida[$clave] = $parte[1] ?? true;
            continue;
        }

        if (str_starts_with($arg, '-') && strlen($arg) > 1) {
            $salida[substr($arg, 1)] = true;
            continue;
        }

        $salida[] = $arg;
    }

    return $salida;
}
