<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Exception\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;

/**
 * Controlador base.
 *
 * Encapsula las operaciones que repiten todos los controladores:
 * renderizar con plantilla, redirigir, responder JSON, abortar con un
 * código HTTP y validar la entrada. Las plantillas siempre reciben
 * únicamente las variables que se les pasan (aislamiento del motor).
 */
abstract class Controller
{
    protected Request $request;

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    protected function request(): Request
    {
        return $this->request;
    }

    // -----------------------------------------------------------------
    //  Respuestas
    // -----------------------------------------------------------------

    /** Renderiza una plantilla con el layout del sitio. */
    protected function view(string $template, array $data = [], string $layout = 'layouts/site'): Response
    {
        return Response::html($this->html($template, $data, $layout));
    }

    protected function html(string $template, array $data = [], string $layout = 'layouts/site'): string
    {
        $this->shareContext($data);

        return View::render($template, $data, $layout);
    }

    protected function redirect(string $to, int $status = 302): Response
    {
        return Response::redirect($to, $status);
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function abort(int $status, string $message = ''): never
    {
        throw new HttpException($status, $message);
    }

    // -----------------------------------------------------------------
    //  Contexto común de todas las plantillas
    // -----------------------------------------------------------------

    /** @param array<string,mixed> $data */
    private function shareContext(array $data): void
    {
        View::share('authUser', Auth::user());
        View::share('currentPath', $this->request->path());
        View::share('flash', Session::pullFlash());
        View::share('query', $_GET);
    }

    // -----------------------------------------------------------------
    //  Validación y mensajes
    // -----------------------------------------------------------------

    /** @param array<string,mixed> $data @param array<string,string> $rules */
    protected function validate(array $data, array $rules, array $labels = []): array
    {
        return Validator::make($data, $rules, $labels)->validated();
    }

    protected function flash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }

    /** Vuelve a la página anterior sólo si apunta a esta misma app. */
    protected function back(string $fallback = '/'): Response
    {
        $referer = (string) $this->request->header('referer', '');
        $parts = $referer !== '' ? (parse_url($referer) ?: []) : [];

        $path = isset($parts['path']) ? (string) $parts['path'] : '';
        $query = isset($parts['query']) ? '?' . (string) $parts['query'] : '';

        // Sólo se aceptan rutas relativas: nunca un destino externo
        if ($path !== '' && !str_contains($path, '://') && !str_starts_with($path, '//')) {
            $base = '/' . trim((string) (parse_url(\App\Core\Env::getString('APP_URL'), PHP_URL_PATH) ?? ''), '/');

            if ($base !== '/' && str_starts_with($path, $base)) {
                $path = substr($path, strlen($base));
            }

            $relative = trim($path, '/');
            if ($relative !== '') {
                return $this->redirect(url($relative . $query));
            }
        }

        return $this->redirect(url($fallback));
    }

    // -----------------------------------------------------------------
    //  Utilidades compartidas
    // -----------------------------------------------------------------

    /** Página actual del listado (nunca menor que 1). */
    protected function page(): int
    {
        $raw = (string) $this->request->get('pagina', '1');

        return ctype_digit($raw) ? max(1, (int) $raw) : 1;
    }

    /** Valor entero de un parámetro de consulta, con tope de seguridad. */
    protected function intQuery(string $key, int $default = 0, int $max = 100000): int
    {
        $raw = $this->request->get($key);

        if ($raw === null || $raw === '' || !is_scalar($raw) || !ctype_digit((string) $raw)) {
            return $default;
        }

        return min($max, (int) $raw);
    }

    /** @return array<int,string> Lista blanca de valores de un filtro */
    protected function listQuery(string $key, array $allowed): array
    {
        $raw = $this->request->get($key);
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $values = array_filter(array_map('trim', explode(',', $raw)));

        return array_values(array_intersect($values, $allowed));
    }

    /**
     * Lista de identificadores numéricos enviados como CSV.
     * Descarta lo que no sea un entero y limita el número de elementos
     * para que un listado gigante no pueda usarse como vector de ataque.
     *
     * @return array<int,int>
     */
    protected function intListQuery(string $key, int $maxItems = 30, int $maxValue = 1000000): array
    {
        $raw = $this->request->get($key);
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $out = [];
        foreach (explode(',', $raw) as $piece) {
            $piece = trim($piece);
            if ($piece === '' || !ctype_digit($piece)) {
                continue;
            }

            $out[] = min($maxValue, (int) $piece);

            if (count($out) >= $maxItems) {
                break;
            }
        }

        return array_values(array_unique($out));
    }
}
