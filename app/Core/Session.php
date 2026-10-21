<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Sesión endurecida:
 *  - cookie HttpOnly + SameSite=Lax (a la vez que Strict en formularios)
 *  - id de sesión regenerado al iniciar y al subir de privilegio
 *  - almacenamiento en ficheros del sistema con permisos restringidos
 *  - expiración por inactividad y por vida máxima absoluta
 */
final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $name = Env::getString('SESSION_NAME', 'mp_session');
        $lifetime = Env::getInt('SESSION_LIFETIME', 7200);

        $storagePath = dirname(__DIR__, 2) . '/storage/sessions';
        if (is_dir($storagePath) && is_writable($storagePath)) {
            session_save_path($storagePath);
        }

        // En HTTPS se exige cookie segura; en local (http) no, para no romper
        $secure = str_starts_with(Env::getString('APP_URL'), 'https://');

        session_name($name);
        session_set_cookie_params([
            'lifetime' => 0,              // cookie de sesión: se cierra al cerrar el navegador
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,           // JavaScript no puede leerla (mitiga XSS)
            'samesite' => 'Lax',
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', (string) $lifetime);

        session_start();

        // Rotar el id si es antiguo (previene fijación de sesión)
        $now = time();
        $_SESSION['_last_activity'] ??= $now;
        $_SESSION['_created'] ??= $now;

        if ($now - (int) $_SESSION['_last_activity'] > $lifetime
            || $now - (int) $_SESSION['_created'] > $lifetime * 12) {
            self::regenerate();
        }
        $_SESSION['_last_activity'] = $now;

        self::$started = true;
    }

    /** Fuerza una nueva identidad de sesión. */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['_created'] = time();
        $_SESSION['_last_activity'] = time();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    // -----------------------------------------------------------------
    //  Mensajes flash (una sola lectura)
    // -----------------------------------------------------------------

    public static function flash(string $type, string $message): void
    {
        self::start();
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return array<int,array{type:string,message:string}> */
    public static function pullFlash(): array
    {
        self::start();
        $flash = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        return is_array($flash) ? $flash : [];
    }

    public static function old(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION['_old'][$key] ?? $default;
    }

    /** @param array<string,mixed> $values */
    public static function keepOld(array $values): void
    {
        self::start();
        $_SESSION['_old'] = $values;
    }

    public static function clearOld(): void
    {
        self::start();
        unset($_SESSION['_old']);
    }

    /** Id actual de la sesión (para tokens y auditoría). */
    public static function id(): string
    {
        self::start();
        return session_id();
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'domain'   => $p['domain'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'],
            ]);
        }

        session_destroy();
        self::$started = false;
    }
}
