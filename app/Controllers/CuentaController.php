<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Exception\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;

/**
 * Área personal: perfil, cambio de contraseña y actividad propia.
 */
final class CuentaController extends Controller
{
    public function index(Request $request): Response
    {
        $usuario = Auth::user();
        if ($usuario === null) {
            throw HttpException::unauthorized();
        }

        $db = Database::get();

        $comentarios = $db->select(
            'SELECT c.id, c.contenido, c.estado, c.creado_en, n.titulo, n.slug
               FROM comentarios c
               JOIN noticias n ON n.id = c.noticia_id
              WHERE c.usuario_id = :u
              ORDER BY c.creado_en DESC
              LIMIT 10',
            ['u' => (int) $usuario['id']]
        );

        $favoritos = [];
        try {
            $favoritos = $db->select(
                'SELECT v.id, v.marca, v.modelo, v.slug, v.precio_nuevo
                   FROM v_coches_catalogo v
                   JOIN coches_favoritos f ON f.coche_id = v.id
                  WHERE f.usuario_id = :u
                  ORDER BY f.creado_en DESC
                  LIMIT 8',
                ['u' => (int) $usuario['id']]
            );
        } catch (\Throwable) {
            // La tabla de favoritos es opcional
        }

        return $this->view('pages/cuenta', [
            'title'       => 'Mi cuenta — MotorPress',
            'description' => 'Gestiona tu perfil, tu contraseña y tu actividad.',
            'canonical'   => url('cuenta'),
            'usuario'     => $usuario,
            'comentarios' => $comentarios,
            'favoritos'   => $favoritos,
        ]);
    }

    public function actualizar(Request $request): Response
    {
        $usuario = Auth::user();
        if ($usuario === null) {
            throw HttpException::unauthorized();
        }

        $datos = $this->validate(
            $request->payload(),
            [
                'nombre'    => 'required|string|min:2|max:100',
                'apellidos' => 'sometimes|nullable|string|max:120',
                'bio'       => 'sometimes|nullable|string|max:400',
                'web'       => 'sometimes|nullable|url|max:255',
            ],
            ['nombre' => 'El nombre']
        );

        Database::get()->update('usuarios', [
            'nombre'         => trim((string) $datos['nombre']),
            'apellidos'      => isset($datos['apellidos']) && $datos['apellidos'] !== null
                ? trim((string) $datos['apellidos'])
                : null,
            'bio'            => $datos['bio'] ?? null,
            'web'            => $datos['web'] ?? null,
            'actualizado_en' => date('Y-m-d H:i:s'),
        ], ['id' => (int) $usuario['id']]);

        $this->flash('success', 'Perfil actualizado.');

        return $this->redirect(url('cuenta'));
    }

    public function cambiarPassword(Request $request): Response
    {
        $usuario = Auth::user();
        if ($usuario === null) {
            throw HttpException::unauthorized();
        }

        $datos = $this->validate(
            $request->payload(),
            [
                'actual' => 'required|string|min:1|max:200',
                'nueva'  => 'required|string|min:10|max:200',
            ],
            ['actual' => 'La contraseña actual', 'nueva' => 'La contraseña nueva']
        );

        $fila = Database::get()->first(
            'SELECT password_hash FROM usuarios WHERE id = :id',
            ['id' => (int) $usuario['id']]
        );

        if ($fila === null || !password_verify((string) $datos['actual'], (string) $fila['password_hash'])) {
            $this->flash('error', 'La contraseña actual no es correcta.');

            return $this->redirect(url('cuenta'));
        }

        $problemas = Security::passwordIssues((string) $datos['nueva']);
        if ($problemas !== []) {
            $this->flash('error', implode(' ', $problemas));

            return $this->redirect(url('cuenta'));
        }

        if (password_verify((string) $datos['nueva'], (string) $fila['password_hash'])) {
            $this->flash('error', 'La contraseña nueva debe ser distinta de la actual.');

            return $this->redirect(url('cuenta'));
        }

        Database::get()->update('usuarios', [
            'password_hash'       => Auth::hash((string) $datos['nueva']),
            'password_cambiado_at' => date('Y-m-d H:i:s'),
            'intentos_fallidos'   => 0,
            'bloqueado_hasta'     => null,
            'actualizado_en'      => date('Y-m-d H:i:s'),
        ], ['id' => (int) $usuario['id']]);

        \App\Core\Auditoria::registrar('usuario.password', 'usuarios', (int) $usuario['id']);

        $this->flash('success', 'Contraseña actualizada.');

        return $this->redirect(url('cuenta'));
    }
}
