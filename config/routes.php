<?php

/**
 * =====================================================================
 *  MotorPress · Mapa de rutas
 * =====================================================================
 *
 * Este fichero devuelve el callable que App invoca durante el arranque:
 *
 *     return function (Router $router, string $root): void { … };
 *
 * Convenciones:
 *   · El orden importa: la primera coincidencia gana.
 *   · Los segmentos fijos se registran SIEMPRE antes que los
 *     marcadores {param} del mismo nivel.
 *   · El middleware se aplica por grupo; cada grupo reutiliza el mismo
 *     prefijo con distinta cadena de autorización.
 * =====================================================================
 */

declare(strict_types=1);

use App\Controllers\Admin;
use App\Controllers\Api\V1;
use App\Controllers\AuthController;
use App\Controllers\BusquedaController;
use App\Controllers\CatalogoController;
use App\Controllers\ComparadorController;
use App\Controllers\CuentaController;
use App\Controllers\HomeController;
use App\Controllers\NoticiaController;
use App\Controllers\PaginaController;
use App\Controllers\SeoController;
use App\Core\Router;
use App\Core\View;
use App\Http\Middleware\ApiRateLimit;
use App\Http\Middleware\FormRateLimit;
use App\Http\Middleware\RequireAdmin;
use App\Http\Middleware\RequireAuth;
use App\Http\Middleware\RequireSuperAdmin;

return function (Router $router, string $root): void {
    // Datos disponibles en todas las plantillas
    View::share('appName', 'MotorPress');
    View::share('appUrl', rtrim(\App\Core\Env::getString('APP_URL'), '/'));

    // -----------------------------------------------------------------
    //  Portada
    // -----------------------------------------------------------------
    $router->get('', [HomeController::class, 'index'], [], 'inicio');

    // -----------------------------------------------------------------
    //  Editorial
    // -----------------------------------------------------------------
    $router->get('noticias', [NoticiaController::class, 'index'], [], 'noticias');
    $router->get('noticias/{slug}', [NoticiaController::class, 'show'], [], 'noticia');
    $router->post('noticias/{slug}/comentarios', [NoticiaController::class, 'comentar'], [
        RequireAuth::class,
        FormRateLimit::class,
    ], 'noticia.comentar');
    $router->get('categoria/{slug}', [NoticiaController::class, 'categoria'], [], 'categoria');
    $router->get('etiqueta/{slug}', [NoticiaController::class, 'etiqueta'], [], 'etiqueta');

    // -----------------------------------------------------------------
    //  Catálogo
    // -----------------------------------------------------------------
    $router->get('coches', [CatalogoController::class, 'index'], [], 'coches');
    $router->get('marcas', [CatalogoController::class, 'marcas'], [], 'marcas');
    $router->get('marcas/{slug}', [CatalogoController::class, 'marca'], [], 'marca');
    $router->get('coches/{slug}', [CatalogoController::class, 'show'], [], 'coche');

    // -----------------------------------------------------------------
    //  Herramientas
    // -----------------------------------------------------------------
    $router->get('comparador', [ComparadorController::class, 'index'], [], 'comparador');
    $router->get('buscar', [BusquedaController::class, 'index'], [], 'buscar');

    // -----------------------------------------------------------------
    //  Institucional y formularios
    // -----------------------------------------------------------------
    $router->get('sobre-nosotros', [PaginaController::class, 'sobreNosotros'], [], 'sobre-nosotros');
    $router->get('privacidad', [PaginaController::class, 'privacidad'], [], 'privacidad');
    $router->get('aviso-legal', [PaginaController::class, 'avisoLegal'], [], 'aviso-legal');
    $router->get('cookies', [PaginaController::class, 'cookies'], [], 'cookies');

    $router->get('contacto', [PaginaController::class, 'contacto'], [], 'contacto');
    $router->post('contacto', [PaginaController::class, 'enviarContacto'], [FormRateLimit::class]);
    $router->post('newsletter', [PaginaController::class, 'newsletter'], [FormRateLimit::class]);
    $router->get('baja-newsletter', [PaginaController::class, 'bajaNewsletter'], [], 'baja');

    // -----------------------------------------------------------------
    //  Cuenta
    // -----------------------------------------------------------------
    $router->get('acceso', [AuthController::class, 'mostrarAcceso'], [], 'acceso');
    $router->post('acceso', [AuthController::class, 'acceder']);

    // Segundo paso del acceso: clave maestra + código TOTP
    $router->get('acceso/verificacion', [AuthController::class, 'mostrarVerificacion'], [], 'verificacion');
    $router->post('acceso/verificacion', [AuthController::class, 'verificar']);

    $router->get('registro', [AuthController::class, 'mostrarRegistro'], [], 'registro');
    $router->post('registro', [AuthController::class, 'registrarse'], [FormRateLimit::class]);
    $router->post('salir', [AuthController::class, 'salir'], [], 'salir');

    $router->get('cuenta', [CuentaController::class, 'index'], [RequireAuth::class], 'cuenta');
    $router->post('cuenta', [CuentaController::class, 'actualizar'], [RequireAuth::class]);
    $router->post('cuenta/password', [CuentaController::class, 'cambiarPassword'], [RequireAuth::class]);

    // -----------------------------------------------------------------
    //  SEO
    // -----------------------------------------------------------------
    $router->get('robots.txt', [SeoController::class, 'robots'], [], 'robots');
    $router->get('sitemap.xml', [SeoController::class, 'sitemap'], [], 'sitemap');
    $router->get('creditos', [SeoController::class, 'creditos'], [], 'creditos');

    // -----------------------------------------------------------------
    //  Panel de administración
    // -----------------------------------------------------------------
    $router->group('admin', function (Router $r): void {
        $r->get('', [Admin\DashboardController::class, 'index'], [], 'admin');

        // Contenido editorial
        $r->get('noticias', [Admin\NoticiaController::class, 'index'], [], 'admin.noticias');
        $r->get('noticias/nueva', [Admin\NoticiaController::class, 'crearForm']);
        $r->post('noticias/nueva', [Admin\NoticiaController::class, 'crear']);
        $r->get('noticias/{id}/editar', [Admin\NoticiaController::class, 'editarForm']);
        $r->post('noticias/{id}/editar', [Admin\NoticiaController::class, 'actualizar']);
        $r->post('noticias/{id}/publicar', [Admin\NoticiaController::class, 'publicar']);
        $r->post('noticias/{id}/archivar', [Admin\NoticiaController::class, 'archivar']);
        $r->post('noticias/{id}/borrar', [Admin\NoticiaController::class, 'borrar']);

        // Catálogo
        $r->get('coches', [Admin\CocheController::class, 'index'], [], 'admin.coches');
        $r->get('coches/nuevo', [Admin\CocheController::class, 'crearForm']);
        $r->post('coches/nuevo', [Admin\CocheController::class, 'crear']);
        $r->get('coches/{id}/editar', [Admin\CocheController::class, 'editarForm']);
        $r->post('coches/{id}/editar', [Admin\CocheController::class, 'actualizar']);
        $r->post('coches/{id}/borrar', [Admin\CocheController::class, 'borrar']);

        // Galería fotográfica y material en vídeo de la ficha
        $r->get('coches/{id}/galeria', [Admin\GaleriaController::class, 'index'], [], 'admin.coches.galeria');
        $r->post('coches/{id}/galeria/fotos', [Admin\GaleriaController::class, 'subirFotos']);
        $r->post('coches/{id}/galeria/fotos/{imagenId}/categoria', [Admin\GaleriaController::class, 'categoriaFoto']);
        $r->post('coches/{id}/galeria/fotos/{imagenId}/principal', [Admin\GaleriaController::class, 'principalFoto']);
        $r->post('coches/{id}/galeria/fotos/{imagenId}/orden', [Admin\GaleriaController::class, 'ordenFoto']);
        $r->post('coches/{id}/galeria/fotos/{imagenId}/borrar', [Admin\GaleriaController::class, 'borrarFoto']);
        $r->post('coches/{id}/galeria/videos', [Admin\GaleriaController::class, 'agregarVideo']);
        $r->post('coches/{id}/galeria/videos/{videoId}/orden', [Admin\GaleriaController::class, 'ordenVideo']);
        $r->post('coches/{id}/galeria/videos/{videoId}/borrar', [Admin\GaleriaController::class, 'borrarVideo']);

        // Comunidad
        $r->get('comentarios', [Admin\ModeracionController::class, 'comentarios'], [], 'admin.comentarios');
        $r->post('comentarios/{id}/moderar', [Admin\ModeracionController::class, 'moderar']);
        $r->get('mensajes', [Admin\ModeracionController::class, 'mensajes'], [], 'admin.mensajes');
        $r->post('mensajes/{id}/estado', [Admin\ModeracionController::class, 'estadoMensaje']);
    }, [RequireAdmin::class]);

    // Operaciones sensibles: únicamente administrador (nivel 90+)
    $router->group('admin', function (Router $r): void {
        $r->get('usuarios', [Admin\UsuarioController::class, 'index'], [], 'admin.usuarios');
        $r->post('usuarios/{id}/editar', [Admin\UsuarioController::class, 'actualizar']);
        $r->post('usuarios/{id}/borrar', [Admin\UsuarioController::class, 'borrar']);

        $r->get('auditoria', [Admin\AuditoriaController::class, 'index'], [], 'admin.auditoria');

        $r->get('configuracion', [Admin\ConfiguracionController::class, 'index'], [], 'admin.config');
        $r->post('configuracion', [Admin\ConfiguracionController::class, 'guardar']);

        // Segundo factor (TOTP) y estado de la defensa del panel
        $r->get('seguridad', [Admin\SeguridadController::class, 'index'], [], 'admin.seguridad');
        $r->post('seguridad/totp', [Admin\SeguridadController::class, 'activar']);
        $r->post('seguridad/totp/borrar', [Admin\SeguridadController::class, 'desactivar']);
    }, [RequireSuperAdmin::class]);

    // -----------------------------------------------------------------
    //  API pública v1
    // -----------------------------------------------------------------
    $router->group('api/v1', function (Router $r): void {
        $r->get('salud', [V1\SistemaController::class, 'salud'], [], 'api.salud');

        $r->get('coches', [V1\CocheController::class, 'index'], [], 'api.coches');
        $r->get('coches/{id}', [V1\CocheController::class, 'show']);
        $r->get('marcas', [V1\CocheController::class, 'marcas'], [], 'api.marcas');
        $r->get('comparar', [V1\CocheController::class, 'comparar'], [], 'api.comparar');

        $r->get('noticias', [V1\NoticiaController::class, 'index'], [], 'api.noticias');
        $r->get('noticias/{id}', [V1\NoticiaController::class, 'show']);
        $r->get('categorias', [V1\NoticiaController::class, 'categorias'], [], 'api.categorias');
    }, [ApiRateLimit::class]);
};
