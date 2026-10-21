<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Exception\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Repositories\NoticiaRepository;

/**
 * Sección editorial: listado, categorías, etiquetas, ficha de artículo
 * y alta de comentarios.
 */
final class NoticiaController extends Controller
{
    private const POR_PAGINA = 9;

    private const ORDENES = ['recientes', 'vistas', 'comentarios'];

    public function index(Request $request): Response
    {
        $repo = new NoticiaRepository();

        $categoria = (string) $request->get('categoria', '');
        $texto = trim((string) $request->get('q', ''));
        $orden = (string) $request->get('orden', 'recientes');
        if (!in_array($orden, self::ORDENES, true)) {
            $orden = 'recientes';
        }

        $resultado = $repo->listado(
            $this->page(),
            self::POR_PAGINA,
            $categoria !== '' ? $categoria : null,
            $texto !== '' ? $texto : null,
            null,
            $orden
        );

        $categorias = $repo->categorias();

        $actual = null;
        foreach ($categorias as $fila) {
            if ($fila['slug'] === $categoria) {
                $actual = $fila;
            }
        }

        return $this->view('pages/noticias/index', [
            'title'       => ($actual !== null ? $actual['nombre'] . ' — ' : '')
                . ($texto !== '' ? 'Resultados de «' . $texto . '»' : 'Noticias de coches'),
            'description' => 'Todas las noticias, pruebas y análisis de MotorPress.'
                . ($actual !== null ? ' Categoría ' . $actual['nombre'] . '.' : ''),
            'canonical'   => url('noticias'),
            'resultado'   => $resultado,
            'categorias'  => $categorias,
            'actual'      => $actual,
            'texto'       => $texto,
            'orden'       => $orden,
            'etiquetas'   => $repo->etiquetasPopulares(14),
            'masLeidas'   => $repo->masLeidas(5),
        ]);
    }

    public function categoria(Request $request): Response
    {
        return $this->index($request);
    }

    public function etiqueta(Request $request): Response
    {
        $repo = new NoticiaRepository();
        $slug = (string) $request->param('slug', '');

        $etiquetas = $repo->etiquetas();
        $etiqueta = null;
        foreach ($etiquetas as $fila) {
            if ($fila['slug'] === $slug) {
                $etiqueta = $fila;
            }
        }

        if ($etiqueta === null) {
            throw HttpException::notFound('La etiqueta solicitada no existe.');
        }

        $resultado = $repo->listado(
            $this->page(),
            self::POR_PAGINA,
            null,
            null,
            $slug,
            'recientes'
        );

        return $this->view('pages/noticias/index', [
            'title'       => 'Etiqueta: ' . $etiqueta['nombre'],
            'description' => 'Artículos etiquetados con «' . $etiqueta['nombre'] . '» en MotorPress.',
            'canonical'   => url('etiqueta/' . $slug),
            'resultado'   => $resultado,
            'categorias'  => $repo->categorias(),
            'actual'      => null,
            'etiqueta'    => $etiqueta,
            'texto'       => '',
            'orden'       => 'recientes',
            'etiquetas'   => $etiquetas,
            'masLeidas'   => $repo->masLeidas(5),
        ]);
    }

    public function show(Request $request): Response
    {
        $repo = new NoticiaRepository();
        $slug = (string) $request->param('slug', '');

        $noticia = $repo->porSlug($slug);
        if ($noticia === null) {
            throw HttpException::notFound('El artículo que buscas no existe o ya no está publicado.');
        }

        // Se cuenta la visita (máx. una cada 30 min por IP)
        $repo->registrarVista((int) $noticia['id'], $request->ip());

        $comentarios = $noticia['permitir_comentarios']
            ? $repo->comentarios((int) $noticia['id'])
            : [];

        return $this->view('pages/noticias/show', [
            'title'       => $noticia['titulo'],
            'description' => $noticia['resumen'] ?? '',
            'canonical'   => url('noticias/' . $noticia['slug']),
            'noticia'     => $noticia,
            'comentarios' => $comentarios,
            'relacionadas' => $repo->relacionadas($noticia, 3),
            'etiquetas'   => $repo->etiquetas((int) $noticia['id']),
        ]);
    }

    /**
     * Alta de comentario. Sólo usuarios identificados: la moderación
     * previa y la trazabilidad son imposibles con mensajes anónimos.
     */
    public function comentar(Request $request): Response
    {
        $slug = (string) $request->param('slug', '');
        $repo = new NoticiaRepository();

        $noticia = $repo->porSlug($slug);
        if ($noticia === null) {
            throw HttpException::notFound('El artículo que buscas no existe.');
        }

        $destino = url('noticias/' . $noticia['slug']);

        if (empty($noticia['permitir_comentarios'])) {
            $this->flash('error', 'Este artículo tiene los comentarios cerrados.');
            return $this->redirect($destino);
        }

        $usuario = Auth::user();
        if ($usuario === null) {
            Session::set('intended', 'noticias/' . $noticia['slug']);
            $this->flash('info', 'Inicia sesión para comentar.');
            return $this->redirect(url('acceso'));
        }

        $datos = $this->validate(
            $request->payload(),
            [
                'contenido' => 'required|string|min:8|max:2000',
            ],
            ['contenido' => 'El comentario']
        );

        $id = $repo->agregarComentario([
            'noticia_id'  => (int) $noticia['id'],
            'usuario_id'  => (int) $usuario['id'],
            'autor'       => trim($usuario['nombre'] . ' ' . ($usuario['apellidos'] ?? '')),
            'email'       => $usuario['email'],
            'contenido'   => $datos['contenido'],
            'ip'          => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'estado'      => 'pendiente',
        ]);

        if ($id > 0) {
            $this->flash('success', 'Comentario enviado. Será visible tras la moderación.');
        } elseif ($id === 0) {
            $this->flash('info', 'Ese comentario ya estaba registrado.');
        } else {
            $this->flash('error', 'No se ha podido enviar el comentario. Inténtalo de nuevo en unos instantes.');
        }

        return $this->redirect($destino . '#comentarios');
    }
}
