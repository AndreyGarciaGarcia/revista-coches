<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Exception\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\CocheRepository;

/**
 * Catálogo tipo coches.net: búsqueda con filtros combinables, ficha
 * técnica completa, precios, galería y marcas.
 *
 * Todos los filtros se validan contra listas blancas antes de llegar a
 * la base de datos; el orden también sale de una enumeración cerrada.
 */
final class CatalogoController extends Controller
{
    private const POR_PAGINA = 12;

    private const CATEGORIAS = [
        'turismo', 'berlina', 'suv', 'utilitario', 'deportivo',
        'monovolumen', 'pickup', 'furgoneta', 'coupé', 'descapotable',
    ];

    private const COMBUSTIBLES = [
        'gasolina', 'diesel', 'hibrido', 'hibrido_enchufable', 'electrico', 'gas_natural',
    ];

    public function index(Request $request): Response
    {
        $repo = new CocheRepository();

        $filtros = $this->filtros($request);
        $resultado = $repo->buscar($filtros, $this->page(), self::POR_PAGINA);

        return $this->view('pages/catalogo/index', [
            'title'       => 'Catálogo de coches — precios, fichas técnicas y comparativas',
            'description' => 'Busca coches por marca, categoría, precio o combustible: ficha técnica, '
                . 'precio nuevo y de ocasión, consumos y valoraciones.',
            'canonical'   => url('coches'),
            'resultado'   => $resultado,
            'filtros'     => $filtros,
            'facetas'     => $repo->facetas(),
            'marcas'      => $repo->marcas(),
            'rango'       => $repo->rangoPrecios(),
            'categorias'  => self::CATEGORIAS,
            'combustibles' => self::COMBUSTIBLES,
            'total'       => $repo->totalPublicados(),
        ]);
    }

    public function marcas(Request $request): Response
    {
        $repo = new CocheRepository();

        return $this->view('pages/catalogo/marcas', [
            'title'       => 'Todas las marcas de coches',
            'description' => 'Directorio de fabricantes con número de modelos y precio de entrada.',
            'canonical'   => url('marcas'),
            'marcas'      => $repo->marcas(),
            'total'       => $repo->totalPublicados(),
        ]);
    }

    public function marca(Request $request): Response
    {
        $repo = new CocheRepository();
        $slug = (string) $request->param('slug', '');

        $marca = $repo->marcaPorSlug($slug);
        if ($marca === null) {
            throw HttpException::notFound('La marca solicitada no existe.');
        }

        $pagina = $this->page();
        $total = $repo->totalPorMarca($slug);

        return $this->view('pages/catalogo/marca', [
            'title'       => 'Coches de ' . $marca['nombre'] . ' — modelos, precios y fichas',
            'description' => 'Todos los modelos de ' . $marca['nombre']
                . ' disponibles en el catálogo de MotorPress.',
            'canonical'   => url('marcas/' . $marca['slug']),
            'marca'       => $marca,
            'resultado'   => [
                'items'   => $repo->porMarcaSlug($slug, self::POR_PAGINA, ($pagina - 1) * self::POR_PAGINA),
                'total'   => $total,
                'paginas' => max(1, (int) ceil($total / self::POR_PAGINA)),
                'pagina'  => $pagina,
            ],
            'relacionadas' => $repo->destacados(3),
        ]);
    }

    public function show(Request $request): Response
    {
        $repo = new CocheRepository();
        $slug = (string) $request->param('slug', '');

        $coche = $repo->porSlug($slug);
        if ($coche === null) {
            throw HttpException::notFound('El vehículo que buscas no existe o ya no está publicado.');
        }

        $repo->registrarVista((int) $coche['id'], $request->ip());

        $galeria = $repo->galeria((int) $coche['id']);
        $videos = $repo->videos((int) $coche['id']);
        $historico = $repo->preciosHistoricos((int) $coche['id']);

        return $this->view('pages/catalogo/show', [
            'title'       => $coche['marca'] . ' ' . $coche['modelo']
                . ($coche['version'] !== null ? ' ' . $coche['version'] : '') . ' — ficha técnica y precios',
            'description' => $coche['resumen'] ?? ('Ficha técnica, precios y valoraciones de '
                . $coche['marca'] . ' ' . $coche['modelo'] . '.'),
            'canonical'   => url('coches/' . $coche['slug']),
            'coche'       => $coche,
            'galeria'     => $galeria,
            'videos'      => $videos,
            'historico'   => $historico,
            'relacionados' => $repo->relacionados($coche, 4),
            'noticias'    => $repo->noticiasRelacionadas((int) $coche['id'], 3),
            'valoraciones' => $repo->valoraciones((int) $coche['id'], 5),
        ]);
    }

    /**
     * Lectura y normalización de los parámetros de filtrado.
     *
     * @return array<string,mixed>
     */
    private function filtros(Request $request): array
    {
        $marcasIds = $this->intListQuery('marcas', 40);

        return [
            'texto'        => mb_substr(trim((string) $request->get('q', '')), 0, 120),
            'marcas'       => implode(',', $marcasIds),
            'categorias'   => implode(',', $this->listQuery('categorias', self::CATEGORIAS)),
            'combustibles' => implode(',', $this->listQuery('combustibles', self::COMBUSTIBLES)),
            'precio_min'   => $this->intQuery('precio_min', 0, 500000) ?: null,
            'precio_max'   => $this->intQuery('precio_max', 0, 500000) ?: null,
            'potencia_min' => $this->intQuery('potencia_min', 0, 2000) ?: null,
            'orden'        => in_array((string) $request->get('orden', ''), CocheRepository::ORDENES, true)
                ? (string) $request->get('orden')
                : 'relevancia',
        ];
    }
}
