<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Contexto de auditoría para los disparadores de MySQL.
 *
 * Varios disparadores (tr_noticias_bu, tr_coches_bu…) registran quién
 * hizo el cambio. Como MySQL no conoce a la aplicación, el usuario y la
 * IP se publican como variables de sesión ANTES de escribir, de modo que
 * el disparador pueda guardarlos sin que ningún dato del usuario se
 * concatene en la sentencia.
 */
final class Auditoria
{
    private static bool $publicado = false;

    public static function contexto(Request $request): void
    {
        if (self::$publicado) {
            return;
        }

        // Sólo interesa cuando va a haber escrituras: los GET de lectura
        // no tocan las tablas con disparadores de auditoría.
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)
            && !str_starts_with($request->path(), 'admin')) {
            return;
        }

        self::$publicado = true;

        try {
            $usuario = Auth::user();

            Database::get()->run(
                'SET @mp_actor_id = :id, @mp_actor_email = :email, @mp_ip = :ip, @mp_ua = :ua',
                [
                    'id'    => $usuario['id'] ?? null,
                    'email' => $usuario['email'] ?? ($_SERVER['REMOTE_ADDR'] ?? 'anónimo'),
                    'ip'    => $request->ip(),
                    'ua'    => $request->userAgent(),
                ]
            );
        } catch (\Throwable $e) {
            Logger::warning('No se pudo fijar el contexto de auditoría: ' . $e->getMessage());
        }
    }

    /** Registra una acción de aplicación en la traza de auditoría. */
    public static function registrar(string $accion, ?string $entidad = null, ?int $id = null, array $datos = []): void
    {
        try {
            $usuario = Auth::user();

            Database::get()->insert('auditoria', [
                'actor_id'    => $usuario['id'] ?? null,
                'actor_email' => $usuario['email'] ?? 'anónimo',
                'accion'      => mb_substr($accion, 0, 60),
                'entidad'     => $entidad !== null ? mb_substr($entidad, 0, 60) : null,
                'entidad_id'  => $id,
                'datos'       => $datos === [] ? null : json_encode($datos, JSON_UNESCAPED_UNICODE),
                'ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent'  => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
            ]);
        } catch (\Throwable $e) {
            Logger::warning('No se pudo auditar «' . $accion . '»: ' . $e->getMessage());
        }
    }
}
