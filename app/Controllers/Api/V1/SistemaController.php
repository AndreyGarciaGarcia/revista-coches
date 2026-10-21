<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Str;

/**
 * API pública v1 · Estado del sistema.
 *
 *   GET /api/v1/salud    comprobación de vida para supervisores
 *
 * No filtra información sensible: sólo confirma que la base de datos
 * responde y cuántas migraciones están aplicadas.
 */
final class SistemaController extends Controller
{
    public function salud(Request $request): Response
    {
        $inicio = microtime(true);
        $estado = 'ok';
        $detalles = [];

        try {
            $detalles['base_datos'] = (string) Database::get()->scalar('SELECT VERSION()');
            $detalles['migraciones'] = (int) Database::get()->scalar('SELECT COUNT(*) FROM migrations');
            $detalles['vehiculos'] = (int) Database::get()->scalar('SELECT COUNT(*) FROM v_coches_catalogo');
            $detalles['articulos'] = (int) Database::get()->scalar('SELECT COUNT(*) FROM v_noticias_publicadas');
        } catch (\Throwable $e) {
            $estado = 'degradado';
            $detalles['error'] = 'No se pudo consultar la base de datos.';
            \App\Core\Logger::critical('Salud de la API: ' . $e->getMessage());
        }

        $latencia = (int) round((microtime(true) - $inicio) * 1000);

        return $this->json([
            'ok'     => $estado === 'ok',
            'estado' => $estado,
            'data'   => $detalles + [
                'version'      => '1.0.0',
                'php'          => PHP_VERSION,
                'latencia_ms'  => $latencia,
                'hora'         => date('c'),
                'firma'        => Str::token(8),
            ],
        ], $estado === 'ok' ? 200 : 503);
    }
}
