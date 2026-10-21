<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Limitador de peticiones (rate limiting) persistente en base de datos.
 *
 * Se usa para frenar:
 *   - intentos de login fallidos (por IP y por cuenta)
 *   - envíos del formulario de contacto/newsletter
 *   - llamadas a la API pública
 *
 * Ventana deslizante: se cuentan los intentos de los últimos N segundos.
 */
final class RateLimiter
{
    private const TABLE = 'rate_limits';

    /** Comprueba si la clave todavía tiene intentos disponibles. */
    public static function attempt(string $key, int $max, int $window): bool
    {
        return self::hit($key, $max, $window) <= $max;
    }

    /**
     * Registra un intento y devuelve cuántos intentos hay en la ventana.
     * Si la ventana está vacía se crea el registro.
     */
    public static function hit(string $key, int $max, int $window): int
    {
        $hash = hash('sha256', $key);
        $now = time();
        $since = $now - $window;

        $db = Database::get();

        try {
            return (int) $db->transactional(static function () use ($db, $hash, $now, $since, $window): int {
                $row = $db->first(
                    'SELECT id, intentos, ventana_hasta FROM rate_limits WHERE clave_hash = :k FOR UPDATE',
                    ['k' => $hash]
                );

                if ($row === null || (int) $row['ventana_hasta'] <= $since) {
                    // Ventana nueva o caducada
                    if ($row === null) {
                        $db->insert('rate_limits', [
                            'clave_hash'    => $hash,
                            'intentos'      => 1,
                            'ventana_hasta' => date('Y-m-d H:i:s', $now + $window),
                            'actualizado'   => date('Y-m-d H:i:s', $now),
                        ]);
                    } else {
                        $db->execute(
                            'UPDATE rate_limits SET intentos = 1,
                                    ventana_hasta = :h, actualizado = :a
                              WHERE id = :id',
                            [
                                'h' => date('Y-m-d H:i:s', $now + $window),
                                'a' => date('Y-m-d H:i:s', $now),
                                'id' => $row['id'],
                            ]
                        );
                    }

                    return 1;
                }

                $db->execute(
                    'UPDATE rate_limits SET intentos = intentos + 1, actualizado = :a WHERE id = :id',
                    ['a' => date('Y-m-d H:i:s', $now), 'id' => $row['id']]
                );

                return (int) $row['intentos'] + 1;
            });
        } catch (\Throwable $e) {
            // Si la tabla no existe (instalación a medias) no bloqueamos la app
            Logger::warning('RateLimiter no disponible: ' . $e->getMessage());
            return 0;
        }
    }

    /** Los intentos legítimos resetean la ventana. */
    public static function clear(string $key): void
    {
        try {
            Database::get()->delete(self::TABLE, ['clave_hash' => hash('sha256', $key)]);
        } catch (\Throwable) {
            // silencioso
        }
    }

    /** Segundos restantes hasta poder volver a intentarlo. */
    public static function retryAfter(string $key, int $window): int
    {
        try {
            $row = Database::get()->first(
                'SELECT ventana_hasta FROM rate_limits WHERE clave_hash = :k',
                ['k' => hash('sha256', $key)]
            );
            if ($row === null) {
                return 0;
            }
            $until = strtotime((string) $row['ventana_hasta']);
            return $until === false ? 0 : max(0, $until - time());
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Limpieza de registros caducados (la llama el script de mantenimiento). */
    public static function purge(int $maxAge = 86400): int
    {
        try {
            return Database::get()->execute(
                'DELETE FROM rate_limits WHERE actualizado < :t',
                ['t' => date('Y-m-d H:i:s', time() - $maxAge)]
            );
        } catch (\Throwable) {
            return 0;
        }
    }
}
