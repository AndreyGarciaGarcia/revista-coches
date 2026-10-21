<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Auth;
use App\Core\Env;
use App\Core\Exception\HttpException;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Acceso al panel de administración.
 *
 * Cinco barreras encadenadas antes de que se ejecute cualquier
 * controlador del panel:
 *
 *   1. sesión iniciada (que exige contraseña + clave maestra + TOTP);
 *   2. lista blanca de cuentas: sólo ADMIN_EMAIL puede entrar;
 *   3. nivel jerárquico mínimo (90, administrador);
 *   4. cierre automático por inactividad (PANEL_INACTIVIDAD segundos);
 *   5. límite de peticiones al panel por IP y por minuto.
 *
 * Además endurece la respuesta: el panel no puede incrustarse en
 * iframes ajenos (DENY + frame-ancestors 'none'), no se cachea y no
 * genera referencias hacia fuera.
 */
class RequireAdmin
{
    protected const MIN_LEVEL = 90;

    public function handle(Request $request, callable $next): Response
    {
        $usuario = Auth::user();

        if ($usuario === null) {
            throw new HttpException(401, 'Debes identificarte para acceder al panel.');
        }

        // -------------------------------------------------------------
        // 1. Lista blanca de cuentas (salvo que esté vacía a propósito)
        // -------------------------------------------------------------
        $permitido = Env::getString('ADMIN_EMAIL', '');

        if ($permitido !== '' && !hash_equals($permitido, (string) $usuario['email'])) {
            Logger::security('Acceso al panel denegado por lista blanca', [
                'email' => $usuario['email'],
                'ip'    => $request->ip(),
            ]);

            throw new HttpException(403, 'Esta cuenta no está autorizada para el panel.');
        }

        // -------------------------------------------------------------
        // 2. Nivel jerárquico
        // -------------------------------------------------------------
        if (!Auth::can(self::MIN_LEVEL)) {
            throw new HttpException(403, 'Tu rol no tiene permisos para acceder al panel.');
        }

        // -------------------------------------------------------------
        // 3. Expiración por inactividad
        // -------------------------------------------------------------
        $ultimo = (int) Session::get('panel_actividad', 0);
        $limite = Env::getInt('PANEL_INACTIVIDAD', 900);

        if ($ultimo > 0 && (time() - $ultimo) > $limite) {
            Logger::security('Sesión de panel cerrada por inactividad', [
                'email' => $usuario['email'],
                'ip'    => $request->ip(),
            ]);
            Auth::logout();

            throw new HttpException(401, 'Tu sesión ha caducado por inactividad. Vuelve a identificarte.');
        }

        // -------------------------------------------------------------
        // 4. Ritmo de peticiones al panel
        // -------------------------------------------------------------
        if (!RateLimiter::attempt('panel|' . $request->ip(), 600, 60)) {
            throw new HttpException(429, 'Demasiadas peticiones al panel. Espera un minuto.');
        }

        Session::set('panel_actividad', time());

        // Las cabeceras endurecidas del panel (sin iframe, sin caché,
        // sin referencias) las envía Security::sendHeaders() para todas
        // las respuestas de /admin, incluidas las de error.
        return $next($request);
    }
}
