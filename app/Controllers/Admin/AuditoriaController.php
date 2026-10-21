<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AdminRepository;

/**
 * Traza de auditoría: quién hizo qué, cuándo y desde dónde.
 * Es la pieza que convierte el registro en algo auditable.
 */
final class AuditoriaController extends Controller
{
    public function index(Request $request): Response
    {
        $accion = trim((string) $request->get('accion', ''));
        $texto = trim((string) $request->get('q', ''));
        $admin = new AdminRepository();

        return $this->view('pages/admin/auditoria', [
            'title'     => 'Auditoría — Panel',
            'resultado' => $admin->auditoria($this->page(), 30, $accion, $texto),
            'acciones'  => $admin->accionesFrecuentes(),
            'accion'    => $accion,
            'texto'     => $texto,
        ], 'layouts/admin');
    }
}
