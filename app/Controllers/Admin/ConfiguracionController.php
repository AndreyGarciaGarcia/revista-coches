<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AdminRepository;
use App\Repositories\SitioRepository;

/**
 * Configuración editable del sitio (tabla `configuracion`) más un
 * resumen del estado del sistema. Los valores marcados como no
 * editables (claves, credenciales) no se muestran ni se aceptan.
 */
final class ConfiguracionController extends Controller
{
    public function index(Request $request): Response
    {
        $sitio = new SitioRepository();

        return $this->view('pages/admin/configuracion', [
            'title'     => 'Configuración — Panel',
            'valores'   => $sitio->configuracion(),
            'entorno'   => $this->entorno(),
            'metricas'  => (new AdminRepository())->metricas(),
        ], 'layouts/admin');
    }

    public function guardar(Request $request): Response
    {
        $sitio = new SitioRepository();
        $guardados = 0;

        foreach ($sitio->configuracion() as $clave => $fila) {
            if (empty($fila['editable'])) {
                continue;
            }

            $valor = $request->input('cfg_' . $clave);

            if ($valor === null) {
                continue;
            }

            $valor = is_scalar($valor) ? trim((string) $valor) : '';

            // Reglas según el tipo declarado en la tabla
            $valido = match ((string) $fila['tipo']) {
                'numero'   => is_numeric($valor),
                'booleano' => in_array($valor, ['0', '1', 'true', 'false', 'on', 'off', ''], true),
                'json'     => $valor === '' || json_decode($valor, true) !== null,
                default    => mb_strlen($valor) <= 4000,
            };

            if (!$valido) {
                $this->flash('error', 'Valor no válido para «' . $clave . '».');

                return $this->redirect(url('admin/configuracion'));
            }

            $sitio->guardarValor($clave, $valor);
            $guardados++;
        }

        (new AdminRepository())->auditar('configuracion.guardar', 'configuracion', null, [
            'cambios' => $guardados,
        ]);

        $this->flash('success', "Se han guardado {$guardados} valores.");

        return $this->redirect(url('admin/configuracion'));
    }

    /** @return array<string,string> */
    private function entorno(): array
    {
        return [
            'aplicacion' => Env::getString('APP_ENV', 'production'),
            'version'    => '1.0.0',
            'php'        => PHP_VERSION,
            'base_datos' => 'MySQL ' . $this->versionMysql(),
            'zona'       => Env::getString('APP_TIMEZONE', 'Europe/Madrid'),
            'debug'      => Env::getBool('APP_DEBUG') ? 'activado' : 'desactivado',
            'usuario'    => Env::getString('DB_USER', ''),
        ];
    }

    private function versionMysql(): string
    {
        try {
            return (string) \App\Core\Database::get()->scalar('SELECT VERSION()');
        } catch (\Throwable) {
            return 'desconocida';
        }
    }
}
