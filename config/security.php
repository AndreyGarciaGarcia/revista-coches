<?php

/**
 * Políticas de seguridad: cabeceras HTTP, límites y reglas de contraseñas.
 */

declare(strict_types=1);

use App\Core\Env;

$https = str_starts_with(Env::getString('APP_URL'), 'https://');

return [
    // Cabeceras enviadas en TODAS las respuestas (se aplican en App::send())
    'headers' => array_filter([
        'X-Content-Type-Options'    => 'nosniff',
        'X-Frame-Options'           => 'SAMEORIGIN',
        'Referrer-Policy'           => 'strict-origin-when-cross-origin',
        'Permissions-Policy'        => 'geolocation=(), microphone=(), camera=(), payment=()',
        'Cross-Origin-Resource-Policy' => 'same-origin',
        'X-Permitted-Cross-Domain-Policies' => 'none',
        'X-XSS-Protection'          => '0',
        'Strict-Transport-Security' => $https ? 'max-age=31536000; includeSubDomains' : null,
    ]),

    // Política de contenido: sólo recursos propios y data:/https: explícitos
    'csp' => implode('; ', array_filter([
        "default-src 'self'",
        "base-uri 'self'",
        "object-src 'none'",
        "frame-ancestors 'self'",
        "form-action 'self'",
        "img-src 'self' data: blob:",
        "font-src 'self' data:",
        "style-src 'self' 'unsafe-inline'",
        "script-src 'self'",
        "connect-src 'self'",
        "media-src 'self'",
        "manifest-src 'self'",
        // Reproductor de vídeo: el iframe sólo se inyecta al pulsar la
        // miniatura y apunta al modo sin cookies de YouTube.
        "frame-src 'self' https://www.youtube-nocookie.com https://www.youtube.com",
    ])),

    // Límite de peticiones: 5 intentos cada 5 minutos por clave
    'rate_limit' => [
        'max'    => Env::getInt('RATE_LIMIT_MAX', 5),
        'window' => Env::getInt('RATE_LIMIT_WINDOW', 300),
    ],

    // Política de contraseñas (se comprueba en el registro y el cambio)
    'password' => [
        'min'          => 10,
        'max'          => 200,
        'require_upper'=> true,
        'require_lower'=> true,
        'require_digit'=> true,
        'require_special' => true,
        'common'       => [
            'password', '12345678', '123456789', 'qwertyuiop', 'admin123',
            'motorpress', 'contrasena', 'iloveyou', '11111111', 'abc12345',
        ],
    ],

    'uploads' => [
        'max_bytes' => 3 * 1024 * 1024,           // 3 MB
        'allowed'   => ['image/jpeg', 'image/png', 'image/webp', 'image/avif'],
        'dir'       => 'public/assets/img/uploads',
    ],
];
