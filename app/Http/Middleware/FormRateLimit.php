<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Exception\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;

/**
 * Límite de envíos para formularios con efectos externos
 * (contacto, suscripción, acceso). Se aplica por IP.
 */
final class FormRateLimit
{
    private const MAX = 6;
    private const WINDOW = 300;

    public function handle(Request $request, callable $next): Response
    {
        $key = 'form|' . $request->ip();

        if (!RateLimiter::attempt($key, self::MAX, self::WINDOW)) {
            $retry = RateLimiter::retryAfter($key, self::WINDOW);

            throw new HttpException(
                429,
                'Has enviado varios formularios seguidos. Vuelve a intentarlo en '
                . (int) ceil($retry / 60) . ' minutos.'
            );
        }

        return $next($request);
    }
}
