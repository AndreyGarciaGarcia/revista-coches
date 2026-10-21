<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AdminRepository;
use App\Repositories\CocheRepository;
use App\Repositories\NoticiaRepository;

/** Panel principal: cifras, pendientes y actividad reciente. */
final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $admin = new AdminRepository();

        return $this->view('pages/admin/dashboard', [
            'title'       => 'Panel — MotorPress',
            'description' => 'Resumen de la publicación.',
            'metricas'    => $admin->metricas(),
            'actividad'   => $admin->actividadReciente(8),
            'borradores'  => (new NoticiaRepository())->listadoAdmin(1, 5, 'borrador'),
            'ultimosCoches' => (new CocheRepository())->listadoAdmin(1, 5),
            'acciones'    => $admin->accionesFrecuentes(),
        ], 'layouts/admin');
    }
}
