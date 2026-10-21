<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Cargador de variables de entorno (.env).
 *
 * El fichero .env vive fuera del árbol público y NUNCA se sube a git.
 * Cualquier variable ya definida en el entorno del servidor (SetEnv de
 * Apache, variables de sistema, contenedor Docker…) tiene prioridad,
 * de modo que en producción no hace falta ningún fichero en disco.
 */
final class Env
{
    /** @var array<string,string> */
    private static array $vars = [];

    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            // Quitar comillas envolventes
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[0] === $value[-1]) {
                $value = substr($value, 1, -1);
                if ($value[0] === '"') {
                    $value = str_replace(['\\n', '\\r', '\\t', '\\"'], ["\n", "\r", "\t", '"'], $value);
                }
            }

            // El entorno del sistema gana al fichero
            if (getenv($key) === false) {
                self::$vars[$key] = $value;
            }
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $env = getenv($key);
        if ($env !== false) {
            return $env;
        }

        return self::$vars[$key] ?? $default;
    }

    public static function getString(string $key, string $default = ''): string
    {
        return (string) self::get($key, $default);
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $value = self::get($key);
        return $value === null || $value === '' ? $default : (int) $value;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /** Secreto crítico: si falta, la aplicación no debe arrancar. */
    public static function required(string $key): string
    {
        $value = self::get($key);
        if ($value === null || $value === '') {
            throw new \RuntimeException("Falta la variable obligatoria '{$key}' en .env");
        }

        return $value;
    }
}
