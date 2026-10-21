<?php

/**
 * Configuración general de la aplicación.
 * Los valores por defecto son seguros para producción; .env sólo
 * sobrescribe lo que haga falta en desarrollo local.
 */

declare(strict_types=1);

use App\Core\Env;

return [
    'name'     => Env::getString('APP_NAME', 'MotorPress'),
    'env'      => Env::getString('APP_ENV', 'production'),
    'debug'    => Env::getBool('APP_DEBUG', false),
    'url'      => rtrim(Env::getString('APP_URL', ''), '/'),
    'locale'   => Env::getString('APP_LOCALE', 'es_ES'),
    'timezone' => Env::getString('APP_TIMEZONE', 'Europe/Madrid'),

    'mail' => [
        'from' => Env::getString('MAIL_FROM', 'no-reply@localhost'),
        'name' => Env::getString('MAIL_FROM_NAME', 'MotorPress'),
    ],

    'session' => [
        'name'     => Env::getString('SESSION_NAME', 'mp_session'),
        'lifetime' => Env::getInt('SESSION_LIFETIME', 7200),
    ],
];
