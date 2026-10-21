<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\CocheRepository;
use App\Repositories\NoticiaRepository;

/**
 * Sitemap y robots.txt generados dinámicamente: siempre reflejan el
 * contenido publicado en el momento, sin scripts programados.
 */
final class SeoController extends Controller
{
    public function robots(Request $request): Response
    {        $base = rtrim(Env::getString('APP_URL'), '/');

        $texto = "User-agent: *\n"
            . "Allow: /\n"
            . "Disallow: /admin\n"
            . "Disallow: /cuenta\n"
            . "Disallow: /acceso\n"
            . "Disallow: /registro\n"
            . "Disallow: /salir\n"
            . "Disallow: /api/\n"
            . "Disallow: /*?q=\n"
            . "Disallow: /*?orden=\n"
            . "\nSitemap: {$base}/sitemap.xml\n";

        return Response::html($texto)
            ->header('Content-Type', 'text/plain; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    public function sitemap(Request $request): Response
    {
        $base = rtrim(Env::getString('APP_URL'), '/');
        $urls = [];

        $agregar = static function (string $ruta, string $prioridad, ?string $cambio = null) use (&$urls, $base): void {
            $urls[] = [
                'loc'  => $base . '/' . ltrim($ruta, '/'),
                'last' => $cambio ?? date('c'),
                'pri'  => $prioridad,
            ];
        };

        $agregar('', '1.0', date('c'));
        $agregar('noticias', '0.9');
        $agregar('coches', '0.9');
        $agregar('marcas', '0.8');
        $agregar('comparador', '0.6');
        $agregar('contacto', '0.4');
        $agregar('sobre-nosotros', '0.3');
        $agregar('privacidad', '0.2');

        try {
            $noticias = new NoticiaRepository();
            foreach ($noticias->recientes(500) as $fila) {
                $agregar('noticias/' . $fila['slug'], '0.8', (string) $fila['publicado_at']);
            }

            foreach ((new CocheRepository())->marcas() as $marca) {
                $agregar('marcas/' . $marca['slug'], '0.7');
            }

            $db = Database::get();
            foreach ($db->select('SELECT slug FROM v_coches_catalogo') as $fila) {
                $agregar('coches/' . $fila['slug'], '0.7');
            }
        } catch (\Throwable $e) {
            \App\Core\Logger::warning('Sitemap incompleto: ' . $e->getMessage());
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            $xml .= "  <url>\n"
                . '    <loc>' . htmlspecialchars($url['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n"
                . '    <lastmod>' . htmlspecialchars(substr((string) $url['last'], 0, 19)) . "</lastmod>\n"
                . '    <changefreq>weekly</changefreq>' . "\n"
                . '    <priority>' . $url['pri'] . "</priority>\n"
                . "  </url>\n";
        }

        $xml .= '</urlset>' . "\n";

        return Response::html($xml)
            ->header('Content-Type', 'application/xml; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Créditos fotográficos. Las licencias CC BY-SA exigen atribución,
     * y este listado se genera del manifiesto real de descargas.
     */
    public function creditos(Request $request): Response
    {
        $fichero = dirname(__DIR__, 2) . '/database/seeds/imagenes-manifest.json';
        $manifiesto = is_file($fichero)
            ? (json_decode((string) file_get_contents($fichero), true) ?: [])
            : [];

        ksort($manifiesto);

        // Fotografías subidas desde el panel (no proceden de Commons):
        // se identifican porque no llevan URL de origen externa.
        $subidas = [];

        try {
            $subidas = Database::get()->select(
                "SELECT ruta, alt, titulo, autor, licencia, creado_en
                   FROM imagenes
                  WHERE fuente_url IS NULL AND ruta LIKE 'assets/img/%'
                  ORDER BY creado_en DESC, id DESC
                  LIMIT 300"
            );
        } catch (\Throwable) {
            $subidas = [];
        }

        return $this->view('pages/creditos', [
            'title'       => 'Créditos fotográficos',
            'description' => 'Autoría y licencia de cada fotografía utilizada en MotorPress.',
            'canonical'   => url('creditos'),
            'manifiesto'  => $manifiesto,
            'subidas'     => $subidas,
        ]);
    }
}
