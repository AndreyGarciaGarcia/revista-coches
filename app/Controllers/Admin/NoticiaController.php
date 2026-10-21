<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Exception\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use App\Repositories\AdminRepository;
use App\Repositories\NoticiaRepository;

/**
 * CRUD editorial del panel. El alta y la edición validan antes de tocar
 * la base de datos; la publicación pasa por `sp_publicar_noticia`, que
 * exige un cuerpo mínimo y registra la fecha.
 */
final class NoticiaController extends Controller
{
    public function index(Request $request): Response
    {
        $estado = (string) $request->get('estado', '');
        $texto = trim((string) $request->get('q', ''));
        $repo = new NoticiaRepository();

        return $this->view('pages/admin/noticias/index', [
            'title'      => 'Noticias — Panel',
            'resultado'  => $repo->listadoAdmin($this->page(), 15, $estado, $texto),
            'estados'    => $repo->contarPorEstado(),
            'estado'     => $estado,
            'texto'      => $texto,
        ], 'layouts/admin');
    }

    public function crearForm(Request $request): Response
    {
        return $this->formulario(null);
    }

    public function crear(Request $request): Response
    {
        $datos = $this->validar($request);
        $repo = new NoticiaRepository();

        $id = $repo->crear($datos + ['autor_id' => Auth::id()]);

        (new AdminRepository())->auditar('noticia.crear', 'noticias', $id, ['titulo' => $datos['titulo']]);

        $this->flash('success', 'Artículo creado. Ya puedes publicarlo cuando quieras.');

        return $this->redirect(url('admin/noticias/' . $id . '/editar'));
    }

    public function editarForm(Request $request): Response
    {
        $id = $request->intParam('id');
        $noticia = (new NoticiaRepository())->porIdAdmin($id);

        if ($noticia === null) {
            throw HttpException::notFound('El artículo no existe.');
        }

        return $this->formulario($noticia);
    }

    public function actualizar(Request $request): Response
    {
        $id = $request->intParam('id');
        $repo = new NoticiaRepository();

        if ($repo->porIdAdmin($id) === null) {
            throw HttpException::notFound('El artículo no existe.');
        }

        $datos = $this->validar($request);
        $repo->actualizar($id, $datos);

        (new AdminRepository())->auditar('noticia.editar', 'noticias', $id, ['titulo' => $datos['titulo']]);

        $this->flash('success', 'Cambios guardados.');

        return $this->redirect(url('admin/noticias/' . $id . '/editar'));
    }

    public function publicar(Request $request): Response
    {
        $id = $request->intParam('id');
        $repo = new NoticiaRepository();

        if ($repo->publicar($id)) {
            (new AdminRepository())->auditar('noticia.publicar', 'noticias', $id);
            $this->flash('success', 'Artículo publicado.');
        } else {
            $this->flash('error', 'No se pudo publicar: el artículo necesita un cuerpo más extenso.');
        }

        return $this->redirect(url('admin/noticias'));
    }

    public function archivar(Request $request): Response
    {
        $id = $request->intParam('id');
        $repo = new NoticiaRepository();

        if ($repo->archivar($id)) {
            (new AdminRepository())->auditar('noticia.archivar', 'noticias', $id);
            $this->flash('info', 'Artículo archivado.');
        } else {
            $this->flash('error', 'No se pudo archivar el artículo.');
        }

        return $this->redirect(url('admin/noticias'));
    }

    public function borrar(Request $request): Response
    {
        $id = $request->intParam('id');
        $repo = new NoticiaRepository();

        if ($repo->borrar($id)) {
            (new AdminRepository())->auditar('noticia.borrar', 'noticias', $id);
            $this->flash('success', 'Artículo eliminado (borrado lógico).');
        } else {
            $this->flash('error', 'No se pudo eliminar el artículo.');
        }

        return $this->redirect(url('admin/noticias'));
    }

    // -----------------------------------------------------------------
    //  Formulario
    // -----------------------------------------------------------------

    private function formulario(?array $noticia): Response
    {
        $db = Database::get();

        $categorias = $db->select('SELECT id, nombre FROM categorias WHERE activa = 1 ORDER BY orden, nombre');
        $imagenes = (new AdminRepository())->imagenes('news');
        $esNuevo = $noticia === null;

        return $this->view('pages/admin/noticias/form', [
            'title'      => $esNuevo ? 'Nuevo artículo — Panel' : 'Editar artículo — Panel',
            'noticia'    => $noticia,
            'categorias' => $categorias,
            'imagenes'   => $imagenes,
        ], 'layouts/admin');
    }

    /** @return array<string,mixed> */
    private function validar(Request $request): array
    {
        $datos = $this->validate(
            $request->payload(),
            [
                'titulo'      => 'required|string|min:10|max:200',
                'resumen'     => 'sometimes|nullable|string|max:400',
                'cuerpo'      => 'required|string|min:50|max:200000',
                'categoria_id' => 'sometimes|nullable|integer',
                'imagen_id'   => 'sometimes|nullable|integer',
                'pie_foto'    => 'sometimes|nullable|string|max:255',
                'estado'      => 'required|in:borrador,revisado,publicado,archivado',
            ],
            [
                'titulo'  => 'El título',
                'cuerpo'  => 'El cuerpo',
                'estado'  => 'El estado',
            ]
        );

        // Los casilleros no enviados llegan como ausentes: se normalizan
        // a entero 0/1 para que MySQL reciba siempre un tipo numérico.
        $datos['destacada'] = $request->input('destacada') ? 1 : 0;
        $datos['fijada'] = $request->input('fijada') ? 1 : 0;
        $datos['permitir_comentarios'] = $request->input('permitir_comentarios') ? 1 : 0;

        // Higiene del contenido: lista blanca de etiquetas, sin manejadores
        // de eventos ni URL «javascript:». Se vuelve a aplicar al mostrarlo.
        $datos['cuerpo'] = Security::htmlSeguro((string) $datos['cuerpo']);
        $datos['resumen'] = Security::textoSeguro((string) ($datos['resumen'] ?? ''));
        $datos['pie_foto'] = Security::textoSeguro((string) ($datos['pie_foto'] ?? ''));

        if (mb_strlen($datos['cuerpo']) < 50) {
            throw new HttpException(422, 'El cuerpo del artículo es demasiado corto tras depurarlo.');
        }

        return $datos;
    }
}
