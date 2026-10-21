<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Registro de sucesos en storage/logs.
 *  - app.log       -> todo
 *  - errors.log    -> errores y avisos
 *  - security.log  -> intentos de acceso, CSRF, login fallido
 *  - audit.log     -> acciones de administración (quién hizo qué)
 *
 * En desarrollo los mensajes también van a la salida de PHP.
 */
final class Logger
{
    private const DIR = 'storage/logs';

    public static function debug(string $message, array $context = []): void
    {
        self::write('debug', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    public static function notice(string $message, array $context = []): void
    {
        self::write('notice', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context, 'errors.log');
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context, 'errors.log');
    }

    public static function critical(string $message, array $context = []): void
    {
        self::write('critical', $message, $context, 'errors.log');
    }

    /** Evento de seguridad (login, CSRF, subida de ficheros…). */
    public static function security(string $message, array $context = []): void
    {
        self::write('security', $message, $context, 'security.log');
    }

    /** Trazabilidad de administración: quién hizo qué y desde dónde. */
    public static function audit(string $action, array $context = []): void
    {
        $context = array_merge(['accion' => $action], $context);
        self::write('audit', $action, $context, 'audit.log');
    }

    private static function write(
        string $level,
        string $message,
        array $context = [],
        string $file = 'app.log'
    ): void {
        $dir = dirname(__DIR__, 2) . '/' . self::DIR;

        $line = sprintf(
            "[%s] %s.%d %s | %s | %s",
            date('Y-m-d H:i:s'),
            basename($_SERVER['SCRIPT_FILENAME'] ?? 'cli'),
            getmypid() ?: 0,
            strtoupper($level),
            $message,
            $context === [] ? '-' : self::encode($context)
        ) . PHP_EOL;

        // Volcado en consola durante el desarrollo
        if (Env::getBool('APP_DEBUG') && PHP_SAPI === 'cli') {
            fwrite(STDOUT, $line);
        }

        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            return;
        }

        $path = $dir . '/' . $file;
        @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);

        // Rotación: si el fichero pasa de 5 MB, se archiva
        if (@filesize($path) > 5 * 1024 * 1024) {
            @rename($path, $dir . '/' . $file . '.' . date('Ymd-His'));
        }
    }

    private static function encode(array $context): string
    {
        $safe = [];
        foreach ($context as $key => $value) {
            $safe[$key] = is_scalar($value) || $value === null
                ? (string) $value
                : (is_object($value) ? get_class($value) : gettype($value));
        }

        return json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
