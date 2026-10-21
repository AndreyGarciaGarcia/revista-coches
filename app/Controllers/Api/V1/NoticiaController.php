<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\Controller;
use App\Core\Exception\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\NoticiaRepository;

/**
 * API pública v1 · Contenido editorial.
 *
 *   GET /api/v1/noticias        listado con filtros
 *   GET /api/v1/noticias/{id}   artículo completo
 */
final class NoticiaController extends Controller
{
    private const POR_PAGINA = 20;

    public function index(Request $request): Response
    {
        $repo = new NoticiaRepository();

        $orden = (string) $request->get('orden', 'recientes');
        if (!in_array($orden, ['recientes', 'vistas', 'comentarios'], true)) {
            $orden = 'recientes';
        }

        $resultado = $repo->listado(
            $this->page(),
            min(50, max(1, $this->intQuery('limite', self::POR_PAGINA, 50))),
            (string) ($request->get('categoria') ?: null) ?: null,
            trim((string) $request->get('q', '')) ?: null,
            null,
            $orden
        );

        return $this->json([
            'ok'   => true,
            'data' => array_map([$this, 'resumir'], $resultado['items']),
            'meta' => [
                'total'   => $resultado['total'],
                'pagina'  => $resultado['pagina'],
                'paginas' => $resultado['paginas'],
            ],
        ]);
    }

    public function show(Request $request): Response
    {
        $id = $request->intParam('id');
        if ($id <= 0) {
            throw HttpException::badRequest('Identificador de artículo no válido.');
        }

        $repo = new NoticiaRepository();
        $noticia = $repo->porIdPublicado($id);

        if ($noticia === null) {
            throw HttpException::notFound('El artículo no existe.');
        }

        return $this->json([
            'ok'   => true,
            'data' => $noticia + [
                'etiquetas' => array_column($repo->etiquetas($id), 'nombre'),
            ],
        ]);
    }

    public function categorias(Request $request): Response
    {
        return $this->json([
            'ok'   => true,
            'data' => (new NoticiaRepository())->categorias(),
        ]);
    }

    /** @param array<string,mixed> $fila */
    private function resumir(array $fila): array
    {
        return [
            'id'            => (int) $fila['id'],
            'titulo'        => $fila['titulo'] ?? null,
            'slug'          => $fila['slug'] ?? null,
            'resumen'       => $fila['resumen'] ?? null,
            'categoria'     => $fila['categoria'] ?? null,
            'autor'         => $fila['autor'] ?? null,
            'imagen'        => $fila['imagen'] ?? null,
            'vistas'        => (int) ($fila['vistas'] ?? 0),
            'comentarios'   => (int) ($fila['num_comentarios'] ?? 0),
            'tiempo_lectura' => (int) ($fila['tiempo_lectura'] ?? 1),
            'publicado_at'  => $fila['publicado_at'] ?? null,
            'enlace'        => isset($fila['slug']) ? url('noticias/' . $fila['slug']) : null,
        ];
    }
}
