<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\CocheRepository;
use App\Repositories\NoticiaRepository;

/**
 * Búsqueda global: devuelve vehículos, artículos y marcas en una sola
 * respuesta para que el visitante no tenga que adivinar dónde está lo
 * que busca.
 */
final class BusquedaController extends Controller
{
    private const LIMITE_COCHES = 8;
    private const LIMITE_NOTICIAS = 8;

    public function index(Request $request): Response
    {
        $texto = mb_substr(trim((string) $request->get('q', '')), 0, 120);

        $coches = [];
        $noticias = [];
        $marcas = [];
        $total = 0;

        if ($texto !== '') {
            $cocheRepo = new CocheRepository();
            $noticiaRepo = new NoticiaRepository();

            $coches = $cocheRepo->buscar(['texto' => $texto], 1, self::LIMITE_COCHES);
            $noticias = $noticiaRepo->listado(1, self::LIMITE_NOTICIAS, null, $texto);
            $marcas = $this->marcasQueCoinciden($texto);

            $total = $coches['total'] + $noticias['total'] + count($marcas);
        }

        return $this->view('pages/buscar', [
            'title'    => $texto !== '' ? 'Resultados para «' . $texto . '»' : 'Buscar',
            'description' => 'Busca coches, noticias y marcas en todo MotorPress.',
            'canonical' => url('buscar'),
            'texto'     => $texto,
            'coches'    => $coches,
            'noticias'  => $noticias,
            'marcas'    => $marcas,
            'total'     => $total,
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    private function marcasQueCoinciden(string $texto): array
    {
        try {
            return \App\Core\Database::get()->select(
                'SELECT v.* FROM v_marcas_conteo v
                  WHERE v.nombre LIKE :q AND v.num_modelos > 0
                  ORDER BY v.nombre ASC LIMIT 6',
                ['q' => '%' . $texto . '%']
            );
        } catch (\Throwable) {
            return [];
        }
    }
}
