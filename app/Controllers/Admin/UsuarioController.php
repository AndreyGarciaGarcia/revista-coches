<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Exception\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Repositories\AdminRepository;

/**
 * Gestión de cuentas y roles. Sólo administrador (nivel 90+): alterar
 * identidades es la operación de mayor impacto de todo el sistema.
 */
final class UsuarioController extends Controller
{
    public function index(Request $request): Response
    {
        $texto = trim((string) $request->get('q', ''));
        $admin = new AdminRepository();

        return $this->view('pages/admin/usuarios', [
            'title'     => 'Usuarios — Panel',
            'resultado' => $admin->usuarios($this->page(), 20, $texto),
            'roles'     => $admin->roles(),
            'texto'     => $texto,
        ], 'layouts/admin');
    }

    public function actualizar(Request $request): Response
    {
        $id = $request->intParam('id');
        $admin = new AdminRepository();

        if ($admin->usuario($id) === null) {
            throw HttpException::notFound('El usuario no existe.');
        }

        if ($id === Auth::id()) {
            $this->flash('error', 'No puedes cambiar tu propio rol ni tu estado.');

            return $this->redirect(url('admin/usuarios'));
        }

        $datos = Validator::make($request->payload(), [
            'rol_id' => 'required|integer',
            'estado' => 'required|in:activo,inactivo,suspendido',
        ], ['rol_id' => 'El rol', 'estado' => 'El estado'])->validated();

        $roles = array_column($admin->roles(), 'id');
        if (!in_array((int) $datos['rol_id'], array_map('intval', $roles), true)) {
            throw HttpException::badRequest('El rol indicado no existe.');
        }

        $ok = $admin->actualizarUsuario($id, [
            'rol_id' => (int) $datos['rol_id'],
            'estado' => (string) $datos['estado'],
        ], 'Cambio desde el panel');

        $this->flash($ok ? 'success' : 'error', $ok
            ? 'Usuario actualizado y acción auditada.'
            : 'No se pudo actualizar el usuario.');

        return $this->redirect(url('admin/usuarios'));
    }

    public function borrar(Request $request): Response
    {
        $id = $request->intParam('id');

        if ($id === Auth::id()) {
            $this->flash('error', 'No puedes darte de baja a ti mismo.');

            return $this->redirect(url('admin/usuarios'));
        }

        $ok = (new AdminRepository())->borrarUsuario($id);

        $this->flash($ok ? 'success' : 'error', $ok
            ? 'Usuario desactivado (borrado lógico, conservado para auditoría).'
            : 'No se pudo eliminar el usuario.');

        return $this->redirect(url('admin/usuarios'));
    }
}
