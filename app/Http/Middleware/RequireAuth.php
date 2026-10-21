<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Auth;
use App\Core\Exception\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Exige una sesión de usuario válida.
 * Si no la hay, guarda a dónde iba el visitante y redirige al acceso.
 */
final class RequireAuth
{
    public function handle(Request $request, callable $next): Response
    {
        if (!Auth::check()) {
            if ($request->wantsJson()) {
                throw new HttpException(401, 'Debes identificarte para continuar.');
            }

            Session::set('intended', $request->path() . ($request->query() !== '' ? '?' . $request->query() : ''));
            Session::flash('info', 'Inicia sesión para continuar.');

            return Response::redirect(url('acceso'), 302);
        }

        return $next($request);
    }
}
