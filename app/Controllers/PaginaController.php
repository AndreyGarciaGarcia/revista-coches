<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Exception\HttpException;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\SitioRepository;

/**
 * Páginas institucionales y formularios con efecto externo
 * (contacto y newsletter).
 */
final class PaginaController extends Controller
{
    public function sobreNosotros(Request $request): Response
    {
        return $this->view('pages/estaticas/sobre-nosotros', [
            'title'       => 'Sobre nosotros — MotorPress',
            'description' => 'Quiénes somos, cómo trabajamos y qué criterios seguimos para publicar '
                . 'nuestras pruebas y análisis.',
            'canonical'   => url('sobre-nosotros'),
            'metricas'    => (new SitioRepository())->metricas(),
        ]);
    }

    public function privacidad(Request $request): Response
    {
        return $this->view('pages/estaticas/privacidad', [
            'title'       => 'Política de privacidad',
            'description' => 'Cómo tratamos tus datos, con qué base legal y cómo ejercer tus derechos.',
            'canonical'   => url('privacidad'),
        ]);
    }

    public function avisoLegal(Request $request): Response
    {
        return $this->view('pages/estaticas/aviso-legal', [
            'title'       => 'Aviso legal',
            'description' => 'Titularidad del sitio, condiciones de uso y propiedad intelectual.',
            'canonical'   => url('aviso-legal'),
        ]);
    }

    public function cookies(Request $request): Response
    {
        return $this->view('pages/estaticas/cookies', [
            'title'       => 'Política de cookies',
            'description' => 'Qué cookies utilizamos y cómo gestionarlas.',
            'canonical'   => url('cookies'),
        ]);
    }

    public function contacto(Request $request): Response
    {
        return $this->view('pages/contacto', [
            'title'       => 'Contacto — MotorPress',
            'description' => 'Escribe a la redacción, propón una prueba o solicita información publicitaria.',
            'canonical'   => url('contacto'),
            'suscriptores' => (new SitioRepository())->totalSuscriptores(),
        ]);
    }

    /** Envío del formulario de contacto (con limitador de peticiones). */
    public function enviarContacto(Request $request): Response
    {
        $clave = 'contacto|' . $request->ip();

        if (!RateLimiter::attempt($clave, 3, 900)) {
            $minutos = (int) ceil(RateLimiter::retryAfter($clave, 900) / 60);
            $this->flash('error', 'Has enviado varios mensajes seguidos. Inténtalo en ' . max(1, $minutos) . ' minutos.');

            return $this->redirect(url('contacto'));
        }

        $datos = $this->validate(
            $request->payload(),
            [
                'nombre'  => 'required|string|min:2|max:120',
                'email'   => 'required|email|max:190',
                'asunto'  => 'required|string|min:3|max:160',
                'mensaje' => 'required|string|min:20|max:4000',
            ],
            [
                'nombre'  => 'El nombre',
                'asunto'  => 'El asunto',
                'mensaje' => 'El mensaje',
            ]
        );

        // Honeypot: los bots rellenan el campo oculto, las personas no
        if ((string) $request->input('empresa', '') !== '') {
            RateLimiter::clear($clave);
            $this->flash('success', 'Mensaje enviado. Gracias por escribirnos.');

            return $this->redirect(url('contacto'));
        }

        $sitio = new SitioRepository();
        [$ok, $mensaje] = $sitio->guardarMensaje($datos + [
            'ip'          => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'usuario_id'  => \App\Core\Auth::id(),
        ]);

        if ($ok) {
            RateLimiter::clear($clave);
            $this->flash('success', $mensaje);

            return $this->redirect(url('contacto'));
        }

        $this->flash('error', $mensaje);

        return $this->redirect(url('contacto'));
    }

    /** Suscripción al boletín (alta idempotente). */
    public function newsletter(Request $request): Response
    {
        $clave = 'newsletter|' . $request->ip();

        if (!RateLimiter::attempt($clave, 5, 600)) {
            $this->flash('error', 'Demasiados intentos. Espera unos minutos.');

            return $this->redirect($this->destinoVuelta($request));
        }

        $datos = $this->validate(
            $request->payload(),
            ['email' => 'required|email|max:190'],
            ['email' => 'El correo electrónico']
        );

        if ((string) $request->input('web', '') !== '') {
            RateLimiter::clear($clave);
            $this->flash('success', '¡Gracias! Te enviaremos lo mejor de la semana.');

            return $this->redirect($this->destinoVuelta($request));
        }

        $sitio = new SitioRepository();
        [$ok, $mensaje] = $sitio->suscribir(
            (string) $datos['email'],
            $request->input('nombre') !== null ? (string) $request->input('nombre') : null,
            $request->ip()
        );

        if ($ok) {
            RateLimiter::clear($clave);
            $this->flash('success', $mensaje);
        } else {
            $this->flash('error', $mensaje);
        }

        return $this->redirect($this->destinoVuelta($request));
    }

    /** Página de confirmación de baja (enlace del correo). */
    public function bajaNewsletter(Request $request): Response
    {
        $token = (string) $request->get('token', '');

        if ($token === '' || strlen($token) !== 40) {
            throw HttpException::badRequest('El enlace de baja no es válido.');
        }

        $ok = (new SitioRepository())->darDeBaja($token);

        $this->flash($ok ? 'info' : 'error', $ok
            ? 'Ya no recibirás más correos. Puedes darte de alta cuando quieras.'
            : 'Este enlace de baja ya había sido utilizado o no es válido.');

        return $this->redirect(url(''));
    }

    private function destinoVuelta(Request $request): string
    {
        $referer = (string) $request->header('referer', '');
        $path = (string) (parse_url($referer, PHP_URL_PATH) ?? '');

        $base = '/' . trim((string) (parse_url(\App\Core\Env::getString('APP_URL'), PHP_URL_PATH) ?? ''), '/');

        if ($path !== '' && $base !== '/' && str_starts_with($path, $base)) {
            return url(ltrim(substr($path, strlen($base)), '/'));
        }

        if ($path !== '' && !str_contains($path, '://')) {
            return url(ltrim($path, '/'));
        }

        return url('');
    }
}
