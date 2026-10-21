<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exception\CsrfException;

/**
 * Protección CSRF basada en el patrón "double submit cookie" + token
 * sincronizado con la sesión.
 *
 * - Cada formulario lleva un campo oculto `_token`.
 * - Cada petición POST/PUT/DELETE desde el navegador debe llevarlo.
 * - La comparación es en tiempo constante (hash_equals).
 * - El token se rota tras cada uso válido para evitar su repetición.
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        Session::start();

        $token = Session::get(self::KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set(self::KEY, $token);
        }

        return $token;
    }

    /** Campo oculto listo para imprimir dentro de un <form>. */
    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }

    /** Cabecera para peticiones fetch()/XHR. */
    public static function header(): string
    {
        return 'X-CSRF-Token: ' . self::token();
    }

    public static function verify(?string $token): bool
    {
        Session::start();

        $expected = Session::get(self::KEY);
        if (!is_string($expected) || $expected === '' || !is_string($token) || $token === '') {
            return false;
        }

        return hash_equals($expected, $token);
    }

    /**
     * Lanza si el token no es válido. Se llama desde el middleware global.
     *
     * @throws \RuntimeException
     */
    public static function assertValid(?string $token): void
    {
        if (!self::verify($token)) {
            Logger::warning('Token CSRF rechazado', [
                'ip'  => $_SERVER['REMOTE_ADDR'] ?? '-',
                'uri' => $_SERVER['REQUEST_URI'] ?? '-',
            ]);

            throw new CsrfException(
                'La sesión ha caducado o el formulario no es válido. Vuelve atrás, recarga la página e inténtalo de nuevo.'
            );
        }

        // Rota el token tras un uso legítimo
        self::rotate();
    }

    public static function rotate(): void
    {
        Session::set(self::KEY, bin2hex(random_bytes(32)));
    }
}
