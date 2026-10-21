<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\CocheRepository;
use App\Repositories\NoticiaRepository;
use App\Repositories\SitioRepository;

/**
 * Portada del sitio: la mezcla de lo editorial y lo comercial que da
 * sentido al proyecto (revista + catálogo).
 */
final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $noticias = new NoticiaRepository();
        $coches = new CocheRepository();
        $sitio = new SitioRepository();

        $portada = $noticias->portada(7);
        $principal = array_shift($portada);

        return $this->view('pages/home', [
            'title'          => 'MotorPress — Noticias, pruebas y catálogo de coches',
            'description'    => 'Actualidad, pruebas al volante, comparativas y un catálogo con ficha técnica '
                . 'y precios de más de 25 marcas. Todo lo que necesitas para elegir coche.',
            'canonical'      => url(''),
            'principal'      => $principal,
            'secundarias'    => $portada,
            'destacados'     => $coches->destacados(6),
            'novedades'      => $coches->novedades(4),
            'marcas'         => $coches->marcas(),
            'masLeidas'      => $noticias->masLeidas(5),
            'categorias'     => $noticias->categorias(),
            'metricas'       => $sitio->metricas(),
            'newsletter'     => $sitio->totalSuscriptores(),
        ]);
    }
}
