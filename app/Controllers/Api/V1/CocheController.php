<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\Controller;
use App\Core\Exception\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\CocheRepository;

/**
 * API pública v1 · Catálogo de vehículos.
 *
 * Endpoints:
 *   GET /api/v1/coches             listado con filtros
 *   GET /api/v1/coches/{id}        ficha completa
 *   GET /api/v1/marcas             marcas con conteo
 *   GET /api/v1/comparar?ids=1,2   comparación
 *
 * Todas las respuestas siguen el mismo sobre:
 *   { "ok": true, "data": [...], "meta": { … } }
 */
final class CocheController extends Controller
{
    private const POR_PAGINA = 20;

    public function index(Request $request): Response
    {
        $repo = new CocheRepository();

        $filtros = [
            'texto'        => mb_substr(trim((string) $request->get('q', '')), 0, 120),
            'marcas'       => implode(',', $this->intListQuery('marca_id', 40)),
            'categorias'   => implode(',', $this->listQuery('categoria', [
                'turismo', 'berlina', 'suv', 'utilitario', 'deportivo',
                'monovolumen', 'pickup', 'furgoneta', 'coupé', 'descapotable',
            ])),
            'combustibles' => implode(',', $this->listQuery('combustible', [
                'gasolina', 'diesel', 'hibrido', 'hibrido_enchufable', 'electrico', 'gas_natural',
            ])),
            'precio_min'   => $this->intQuery('precio_min', 0, 500000) ?: null,
            'precio_max'   => $this->intQuery('precio_max', 0, 500000) ?: null,
            'potencia_min' => $this->intQuery('potencia_cv_min', 0, 2000) ?: null,
            'orden'        => (string) $request->get('orden', 'relevancia'),
        ];

        $limite = $this->intQuery('limite', self::POR_PAGINA, 100);
        $resultado = $repo->buscar($filtros, $this->page(), max(1, $limite));

        return $this->json([
            'ok'   => true,
            'data' => array_map([$this, 'resumir'], $resultado['items']),
            'meta' => [
                'total'   => $resultado['total'],
                'pagina'  => $resultado['pagina'],
                'paginas' => $resultado['paginas'],
                'por_pagina' => max(1, $limite),
            ],
        ]);
    }

    public function show(Request $request): Response
    {
        $id = $request->intParam('id');
        if ($id <= 0) {
            throw HttpException::badRequest('Identificador de vehículo no válido.');
        }

        $coche = (new CocheRepository())->porId($id);
        if ($coche === null) {
            throw HttpException::notFound('El vehículo no existe.');
        }

        $repo = new CocheRepository();

        return $this->json([
            'ok'   => true,
            'data' => $coche + [
                'galeria'     => $repo->galeria($id),
                'videos'      => $repo->videos($id),
                'relacionados' => array_map([$this, 'resumir'], $repo->relacionados($coche, 4)),
                'precios'     => $repo->preciosHistoricos($id),
            ],
        ]);
    }

    public function marcas(Request $request): Response
    {
        return $this->json([
            'ok'   => true,
            'data' => (new CocheRepository())->marcas(),
        ]);
    }

    public function comparar(Request $request): Response
    {
        $ids = $this->intListQuery('ids', 4);
        $coches = (new CocheRepository())->comparar($ids);

        if ($coches === []) {
            throw HttpException::badRequest('Indica hasta cuatro identificadores en «ids».');
        }

        return $this->json([
            'ok'   => true,
            'data' => $coches,
            'meta' => ['recursos' => count($coches)],
        ]);
    }

    /** @param array<string,mixed> $fila */
    private function resumir(array $fila): array
    {
        return [
            'id'            => (int) $fila['id'],
            'uuid'          => $fila['uuid'] ?? null,
            'marca'         => $fila['marca'] ?? null,
            'marca_slug'    => $fila['marca_slug'] ?? null,
            'modelo'        => $fila['modelo'] ?? null,
            'version'       => $fila['version'] ?? null,
            'categoria'     => $fila['categoria'] ?? null,
            'combustible'   => $fila['combustible'] ?? null,
            'potencia_cv'   => $fila['potencia_cv'] !== null ? (int) $fila['potencia_cv'] : null,
            'precio_nuevo'  => $fila['precio_nuevo'] !== null ? (int) $fila['precio_nuevo'] : null,
            'precio_ocasion' => $fila['precio_ocasion'] !== null ? (int) $fila['precio_ocasion'] : null,
            'valoracion'    => $fila['valoracion'] !== null ? (float) $fila['valoracion'] : null,
            'imagen'        => $fila['imagen_principal'] ?? null,
            'enlace'        => isset($fila['slug']) ? url('coches/' . $fila['slug']) : null,
        ];
    }
}
