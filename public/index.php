<?php

/**
 * =====================================================================
 *  MotorPress — Front Controller
 * =====================================================================
 *  Toda la web (páginas, API, panel de administración) entra por aquí.
 *  Apache entrega todas las peticiones no estáticas a este fichero y
 *  el enrutador decide qué controlador las atiende.
 *
 *  Los ficheros privados (app/, config/, database/, storage/) viven
 *  fuera de este directorio y están bloqueados con .htaccess.
 * =====================================================================
 */

declare(strict_types=1);

use App\Core\App;
use App\Core\Autoloader;
use App\Core\Env;

// Ruta absoluta de la raíz del proyecto (fuera del árbol público)
define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Core/Autoloader.php';
(new Autoloader(BASE_PATH . '/app'))->register();

// Mantenimiento: storage/maintenance.on detiene todo el tráfico público
Env::load(BASE_PATH . '/.env');

$maintenance = BASE_PATH . '/storage/maintenance.on';
if (is_file($maintenance) && !isset($_COOKIE['mp_maintenance'])) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    header('Retry-After: 120');
    echo '<!doctype html><meta charset="utf-8"><title>En mantenimiento</title>'
        . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;'
        . 'background:#08080a;color:#f5f5f7;font:400 17px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;text-align:center}'
        . '.b{max-width:36rem;padding:2rem}h1{font-size:clamp(2rem,6vw,3.2rem);letter-spacing:-.03em;margin:0 0 .5rem}'
        . 'p{color:#a1a1aa}</style><div class="b"><h1>Estamos mejorando</h1>'
        . '<p>Volvemos en unos minutos. Gracias por tu paciencia.</p></div>';
    exit;
}

App::boot()->run();
