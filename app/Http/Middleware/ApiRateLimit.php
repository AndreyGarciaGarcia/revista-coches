<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Exception\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;

/**
 * Límite de peticiones para la API pública.
 *
 * Ventana deslizante persistida en base de datos: sobrevive a los
 * reinicios del servidor y no se puede evitar abriendo varias sesiones.
 * Clave = IP + agente del cliente.
 */
final class ApiRateLimit
{
    private const MAX = 120;      // peticiones
    private const WINDOW = 60;    // por minuto

    public function handle(Request $request, callable $next): Response
    {
        $key = 'api|' . $request->clientKey();

        if (!RateLimiter::attempt($key, self::MAX, self::WINDOW)) {
            $retry = RateLimiter::retryAfter($key, self::WINDOW);

            if ($request->wantsJson()) {
                throw new HttpException(429, 'Demasiadas peticiones. Reintenta en ' . max(1, $retry) . ' s.', [
                    'Retry-After' => (string) max(1, $retry),
                ]);
            }

            throw new HttpException(429, 'Demasiadas peticiones. Espera un momento y vuelve a intentarlo.');
        }

        return $next($request);
    }
}
