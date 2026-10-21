<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Autenticación y control de acceso (RBAC).
 *
 * Contraseñas: Argon2id con parámetros por encima del mínimo recomendado
 * (se almacena todo en el propio hash: coste + salt + verificación).
 * Bloqueo de cuenta progresivo tras N intentos fallidos.
 */
final class Auth
{
    private const TABLE = 'usuarios';

    private static ?array $user = null;
    private static bool $resolved = false;

    // -----------------------------------------------------------------
    //  Hash de contraseñas
    // -----------------------------------------------------------------

    public static function hash(string $plain): string
    {
        $hash = password_hash($plain, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,   // 64 MB  -> hace la fuerza bruta cara
            'time_cost'   => 4,
            'threads'     => 1,
        ]);

        // Fallback si la build de PHP no incluye Argon2
        if ($hash === false || $hash === '') {
            $hash = password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
        }

        return $hash;
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost'   => 4,
            'threads'     => 1,
        ]);
    }

    // -----------------------------------------------------------------
    //  Intento de acceso
    // -----------------------------------------------------------------

    /**
     * Comprueba credenciales.
     *
     * Con $diferir = true, una cuenta privilegiada (nivel ≥ PANEL_NIVEL,
     * es decir, la del propietario del sitio) NO recibe todavía sesión:
     * se guarda un estado «pendiente» que sólo se convierte en sesión
     * completa tras superar la verificación en dos pasos (clave maestra
     * y, si está activo, código TOTP).
     *
     * @return array{ok:bool, reason?:string, user?:array<string,mixed>, verificacion?:bool}
     */
    public static function attempt(string $email, string $password, string $ip = '', bool $diferir = false): array
    {
        $db = Database::get();
        $email = mb_strtolower(trim($email));

        $user = $db->first(
            'SELECT u.*, r.nivel AS rol_nivel
               FROM ' . self::TABLE . ' u
               LEFT JOIN roles r ON r.id = u.rol_id
              WHERE u.email = :email AND u.borrado_en IS NULL
              LIMIT 1',
            ['email' => $email]
        );

        // Hash "falso" para no delatar cuentas inexistentes por tiempos
        $hash = $user['password_hash'] ?? '$2y$12$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
        $valid = password_verify($password, $hash);

        if ($user === null || !$valid) {
            if ($user !== null) {
                self::registerFailure($user);
            }
            Logger::security('Intento de acceso fallido', ['email' => $email, 'ip' => $ip]);

            return ['ok' => false, 'reason' => 'credentials'];
        }

        // Cuenta bloqueada o desactivada
        if (($user['estado'] ?? 'activo') !== 'activo') {
            Logger::security('Acceso a cuenta bloqueada', ['email' => $email, 'ip' => $ip]);
            return ['ok' => false, 'reason' => 'disabled'];
        }

        if (!empty($user['bloqueado_hasta']) && strtotime((string) $user['bloqueado_hasta']) > time()) {
            return ['ok' => false, 'reason' => 'locked'];
        }

        // Rehash si los parámetros han cambiado
        if (self::needsRehash((string) $user['password_hash'])) {
            $db->update(self::TABLE, ['password_hash' => self::hash($password)], ['id' => (int) $user['id']]);
        }

        self::registerSuccess((int) $user['id'], $ip);

        unset($user['password_hash']);

        // Cuentas privilegiadas: aún no hay sesión. Falta la verificación
        // en dos pasos (clave maestra + TOTP).
        if ($diferir && self::esPrivilegiada($user)) {
            Session::set('pendiente_verificacion', [
                'uid'       => (int) $user['id'],
                'email'     => (string) $user['email'],
                'ip'        => (string) $ip,
                'expira'    => time() + 300,   // 5 minutos para completarla
                'intentos'  => 0,
            ]);

            Logger::security('Verificación en dos pasos requerida', [
                'email' => $user['email'],
                'ip'    => $ip,
            ]);

            return ['ok' => true, 'user' => $user, 'verificacion' => true];
        }

        // Credenciales correctas: se abre la sesión (regenerando el id para
        // cerrar cualquier sesión previa ajena al usuario).
        self::login((int) $user['id']);

        return ['ok' => true, 'user' => $user];
    }

    // -----------------------------------------------------------------
    //  Verificación en dos pasos (nivel de propietario)
    // -----------------------------------------------------------------

    /** ¿Esta cuenta exige clave maestra + TOTP para entrar? */
    public static function esPrivilegiada(array $user): bool
    {
        $nivel = (int) ($user['rol_nivel'] ?? 0);

        return $nivel >= Env::getInt('PANEL_NIVEL', 90);
    }

    /** Estado pendiente de verificación, si existe y no ha caducado. */
    public static function pendiente(): ?array
    {
        $pendiente = Session::get('pendiente_verificacion');

        if (!is_array($pendiente) || !isset($pendiente['uid'])) {
            return null;
        }

        if ((int) ($pendiente['expira'] ?? 0) < time()) {
            self::cancelarPendiente();

            return null;
        }

        return $pendiente;
    }

    /** Convierte el estado pendiente en sesión de verdad. */
    public static function confirmarPendiente(): void
    {
        $pendiente = self::pendiente();

        if ($pendiente === null) {
            return;
        }

        Session::forget('pendiente_verificacion');
        self::login((int) $pendiente['uid']);
    }

    public static function cancelarPendiente(): void
    {
        Session::forget('pendiente_verificacion');
    }

    /** Registra un fallo de verificación y devuelve los intentos usados. */
    public static function falloVerificacion(): int
    {
        $pendiente = self::pendiente();

        if ($pendiente === null) {
            return 0;
        }

        $intentos = (int) ($pendiente['intentos'] ?? 0) + 1;
        $pendiente['intentos'] = $intentos;
        Session::set('pendiente_verificacion', $pendiente);

        if ($intentos >= 5) {
            self::cancelarPendiente();
            Logger::security('Verificación abortada por intentos fallidos', [
                'email'    => $pendiente['email'] ?? '-',
                'ip'       => $pendiente['ip'] ?? '-',
                'intentos' => $intentos,
            ]);
        }

        return $intentos;
    }

    private static function registerFailure(array $user): void
    {
        $db = Database::get();
        $intentos = (int) ($user['intentos_fallidos'] ?? 0) + 1;
        $max = Env::getInt('RATE_LIMIT_MAX', 5);

        $data = ['intentos_fallidos' => $intentos];

        if ($intentos >= $max) {
            // Bloqueo temporal: 2^(intentos-max) minutos
            $minutes = min(60, 2 ** ($intentos - $max));
            $data['bloqueado_hasta'] = date('Y-m-d H:i:s', time() + $minutes * 60);
            Logger::security('Cuenta bloqueada por intentos fallidos', [
                'email'    => $user['email'],
                'intentos' => $intentos,
                'minutos'  => $minutes,
            ]);
        }

        $db->update(self::TABLE, $data, ['id' => (int) $user['id']]);
    }

    private static function registerSuccess(int $userId, string $ip): void
    {
        Database::get()->update(self::TABLE, [
            'intentos_fallidos' => 0,
            'bloqueado_hasta'   => null,
            'ultimo_acceso'     => date('Y-m-d H:i:s'),
            'ultima_ip'         => substr($ip, 0, 45),
        ], ['id' => $userId]);
    }

    // -----------------------------------------------------------------
    //  Sesión
    // -----------------------------------------------------------------

    public static function login(int $userId, bool $remember = false): void
    {
        Session::regenerate();
        Session::set('auth_user_id', $userId);
        Session::set('auth_time', time());
        Session::set('auth_remember', (bool) $remember);

        self::$user = null;
        self::$resolved = false;

        $user = self::user();
        Logger::security('Acceso concedido', [
            'user' => $user['email'] ?? $userId,
            'ip'   => $_SERVER['REMOTE_ADDR'] ?? '-',
        ]);
        Logger::audit('login', ['usuario_id' => $userId]);
    }

    public static function logout(): void
    {
        $user = self::user();
        if ($user !== null) {
            Logger::audit('logout', ['usuario_id' => $user['id'], 'email' => $user['email']]);
        }

        Session::destroy();
        self::$user = null;
        self::$resolved = true;
    }

    public static function id(): ?int
    {
        $id = Session::get('auth_user_id');
        return is_numeric($id) ? (int) $id : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function guest(): bool
    {
        return !self::check();
    }

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$user;
        }

        self::$resolved = true;
        $id = self::id();

        if ($id === null) {
            return self::$user = null;
        }

        try {
            $user = Database::get()->first(<<<'SQL'
                SELECT u.*, r.nombre AS rol_nombre, r.nivel AS rol_nivel
                  FROM usuarios u
                  LEFT JOIN roles r ON r.id = u.rol_id
                 WHERE u.id = :id AND u.borrado_en IS NULL AND u.estado = 'activo'
                 LIMIT 1
                SQL, ['id' => $id]);
        } catch (\Throwable $e) {
            Logger::error('No se pudo cargar el usuario: ' . $e->getMessage());
            return self::$user = null;
        }

        if ($user === null) {
            Session::forget('auth_user_id');
            return self::$user = null;
        }

        return self::$user = $user;
    }

    public static function refresh(): void
    {
        self::$user = null;
        self::$resolved = false;
    }

    // -----------------------------------------------------------------
    //  Autorización
    // -----------------------------------------------------------------

    public static function isAdmin(): bool
    {
        $user = self::user();
        return $user !== null && (int) $user['rol_nivel'] >= 50;
    }

    /** Nivel jerárquico: 0=lector 30=autor 50=editor 90=administrador 100=root */
    public static function level(): int
    {
        $user = self::user();
        return $user === null ? 0 : (int) $user['rol_nivel'];
    }

    public static function can(int $minLevel): bool
    {
        return self::level() >= $minLevel;
    }

    /** ¿Tiene el permiso concreto (tabla rol_permisos)? */
    public static function allows(string $permission): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }

        // Nivel root o admin cubre todo
        if ((int) $user['rol_nivel'] >= 90) {
            return true;
        }

        static $cache = null;
        if ($cache === null) {
            try {
                $rows = Database::get()->select(
                    'SELECT DISTINCT p.clave
                       FROM permisos p
                       JOIN rol_permiso rp ON rp.permiso_id = p.id
                      WHERE rp.rol_id = :rol',
                    ['rol' => (int) $user['rol_id']]
                );
                $cache = array_column($rows, 'clave');
            } catch (\Throwable) {
                $cache = [];
            }
        }

        return in_array($permission, $cache, true);
    }
}
