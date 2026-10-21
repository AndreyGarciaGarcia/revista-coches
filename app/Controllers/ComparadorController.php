<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\CocheRepository;

/**
 * Comparador de vehículos: hasta cuatro modelos en paralelo con sus
 * diferencias destacadas. Los identificadores se sanean y se limitan a
 * cuatro para que la consulta sea siempre de tamaño acotado.
 */
final class ComparadorController extends Controller
{
    public const MAXIMO = 4;

    public function index(Request $request): Response
    {
        $repo = new CocheRepository();

        $ids = $this->intListQuery('ids', self::MAXIMO);

        // El formulario «añadir otro» reenvía la selección actual más el nuevo
        $extra = (int) $request->get('add', 0);
        if ($extra > 0 && !in_array($extra, $ids, true) && count($ids) < self::MAXIMO) {
            $ids[] = $extra;
        }

        $seleccion = $repo->comparar($ids);

        // Sugerencias cuando todavía no se ha elegido nada
        $sugerencias = $seleccion === []
            ? $repo->destacados(self::MAXIMO)
            : [];

        return $this->view('pages/comparador', [
            'title'       => 'Comparador de coches',
            'description' => 'Compara hasta cuatro vehículos ficha a ficha: motor, consumos, '
                . 'dimensiones, precio y valoraciones.',
            'canonical'   => url('comparador'),
            'seleccion'   => $seleccion,
            'sugerencias' => $sugerencias,
            'maximo'      => self::MAXIMO,
            'catalogo'    => $repo->buscar(['orden' => 'vistas'], 1, 12)['items'],
        ]);
    }
}
