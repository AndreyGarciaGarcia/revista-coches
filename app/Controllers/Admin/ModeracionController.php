<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Exception\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AdminRepository;

/**
 * Comunidad: moderación de comentarios y bandeja de mensajes.
 * Cada acción queda registrada en la auditoría con autor y IP.
 */
final class ModeracionController extends Controller
{
    private const ESTADOS_COMENTARIO = ['pendiente', 'aprobado', 'spam', 'rechazado'];
    private const ESTADOS_MENSAJE = ['nuevo', 'en_curso', 'resuelto', 'spam'];

    public function comentarios(Request $request): Response
    {
        $estado = (string) $request->get('estado', '');
        if (!in_array($estado, self::ESTADOS_COMENTARIO, true)) {
            $estado = 'pendiente';
        }

        $admin = new AdminRepository();

        return $this->view('pages/admin/comentarios', [
            'title'     => 'Comentarios — Panel',
            'resultado' => $admin->comentarios($this->page(), 20, $estado),
            'estado'    => $estado,
            'estados'   => self::ESTADOS_COMENTARIO,
        ], 'layouts/admin');
    }

    public function moderar(Request $request): Response
    {
        $id = $request->intParam('id');
        $estado = (string) $request->input('estado', '');

        if (!in_array($estado, self::ESTADOS_COMENTARIO, true)) {
            throw HttpException::badRequest('Estado de moderación no válido.');
        }

        $motivo = $request->input('motivo');
        $motivo = is_string($motivo) && trim($motivo) !== '' ? mb_substr(trim($motivo), 0, 200) : null;

        $ok = (new AdminRepository())->moderarComentario($id, $estado, $motivo);

        $this->flash($ok ? 'success' : 'error', $ok
            ? 'Comentario marcado como «' . $estado . '».'
            : 'No se pudo moderar el comentario.');

        return $this->back('admin/comentarios');
    }

    public function mensajes(Request $request): Response
    {
        $estado = (string) $request->get('estado', '');
        if (!in_array($estado, self::ESTADOS_MENSAJE, true)) {
            $estado = '';
        }

        $admin = new AdminRepository();

        return $this->view('pages/admin/mensajes', [
            'title'     => 'Mensajes — Panel',
            'resultado' => $admin->mensajes($this->page(), 20, $estado),
            'estado'    => $estado,
            'estados'   => self::ESTADOS_MENSAJE,
        ], 'layouts/admin');
    }

    public function estadoMensaje(Request $request): Response
    {
        $id = $request->intParam('id');
        $estado = (string) $request->input('estado', '');

        if (!in_array($estado, self::ESTADOS_MENSAJE, true)) {
            throw HttpException::badRequest('Estado no válido.');
        }

        $ok = (new AdminRepository())->estadoMensaje($id, $estado);

        $this->flash($ok ? 'success' : 'error', $ok
            ? 'Mensaje actualizado.'
            : 'No se pudo actualizar el mensaje.');

        return $this->back('admin/mensajes');
    }
}
