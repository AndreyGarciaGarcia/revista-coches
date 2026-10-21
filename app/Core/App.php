<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\Controller;
use App\Core\Exception\CsrfException;
use App\Core\Exception\HttpException;
use App\Core\Exception\ValidationException;

/**
 * Núcleo de la aplicación: arranque, despacho y manejo de errores.
 *
 * Ciclo de vida:
 *   App::boot() -> App::run()
 *     1. Entorno, sesión, cabeceras de seguridad
 *     2. Enrutado de la petición
 *     3. CSRF en métodos no idempotentes
 *     4. Cadena de middleware
 *     5. Controlador -> Response
 *     6. Manejo de excepciones y página de error
 */
final class App
{
    private static ?App $instance = null;

    private Request $request;

    private Router $router;

    /** @var array<string,mixed> */
    private array $config = [];

    private function __construct()
    {
    }

    public static function boot(): App
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $app = new self();
        $app->initialize();

        return self::$instance = $app;
    }

    public static function instance(): App
    {
        return self::$instance ?? self::boot();
    }

    public function request(): Request
    {
        return $this->request;
    }

    public function router(): Router
    {
        return $this->router;
    }

    private function initialize(): void
    {
        $root = dirname(__DIR__, 2);

        require_once $root . '/app/helpers.php';

        Env::load($root . '/.env');

        $appConfig = is_file($root . '/config/app.php') ? require $root . '/config/app.php' : [];
        $this->config = $appConfig;

        date_default_timezone_set(Env::getString('APP_TIMEZONE', 'Europe/Madrid'));
        mb_internal_encoding('UTF-8');
        ini_set('display_errors', Env::getBool('APP_DEBUG') ? '1' : '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);

        Session::start();

        $this->request = Request::capture();
        $this->router = new Router();

        $routes = $root . '/config/routes.php';
        if (is_file($routes)) {
            $callback = require $routes;
            if (is_callable($callback)) {
                $callback($this->router, $root);
            }
        }

        Auditoria::contexto($this->request);

        $this->registerErrorHandlers();
    }

    /** @return array<string,mixed> */
    public function config(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        $value = $this->config;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    // -----------------------------------------------------------------
    //  Ejecución
    // -----------------------------------------------------------------

    public function run(): void
    {
        $response = $this->dispatch($this->request);
        $this->send($response);
    }

    public function dispatch(Request $request): Response
    {
        try {
            Security::apply($request);

            $path = $request->path();
            $match = $this->router->match($request->method(), $path);

            if ($match === null) {
                $allowed = $this->router->allowedMethods();
                if ($allowed !== []) {
                    throw HttpException::methodNotAllowed($allowed);
                }
                throw HttpException::notFound();
            }

            ['route' => $route, 'params' => $params] = $match;
            $request->setRouteParams($params);

            // ---------------------------------------------------------
            // CSRF: obligatorio en POST/PUT/DELETE (web y API)
            // ---------------------------------------------------------
            if (!in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
                $token = $request->input('_token')
                    ?? $request->header('x-csrf-token')
                    ?? ($request->json()['_token'] ?? null);

                Csrf::assertValid(is_string($token) ? $token : null);
            }

            $pipeline = $this->buildPipeline($route);

            return $pipeline($request);
        } catch (CsrfException $e) {
            // 403 y no 419: el código «419» no está registrado y PHP/Apache
            // lo reescribe como 500, ocultando el motivo real al visitante.
            return $this->errorResponse($request, 403, $e->getMessage(), 'La sesión ha caducado');
        } catch (ValidationException $e) {
            if ($request->wantsJson()) {
                return Response::json([
                    'ok'     => false,
                    'errors' => $e->errors(),
                ], 422);
            }

            // Reenvía el formulario con los valores y los errores
            Session::keepOld($request->payload());
            Session::flash('error', $e->first());

            return Response::redirect($this->refererOr($request));
        } catch (HttpException $e) {
            return $this->errorResponse($request, $e->status(), $e->getMessage(), null, $e->headers());
        } catch (\Throwable $e) {
            Logger::error('Error no controlado: ' . $e->getMessage(), [
                'file' => $e->getFile() . ':' . $e->getLine(),
                'path' => $request->path(),
            ]);

            $message = Env::getBool('APP_DEBUG')
                ? $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()
                : 'Se ha producido un error interno. El incidente ha quedado registrado.';

            return $this->errorResponse($request, 500, $message, 'Error interno');
        }
    }

    /**
     * Construye la cadena middleware -> controlador.
     *
     * @param array{middleware:string[],handler:array|string} $route
     */
    private function buildPipeline(array $route): callable
    {
        $handler = static function (Request $request) use ($route): Response {
            return self::invoke($route['handler'], $request);
        };

        $middleware = array_reverse($route['middleware']);

        foreach ($middleware as $class) {
            $next = $handler;
            $handler = static function (Request $request) use ($class, $next): Response {
                /** @var object{handle(Request,callable):Response} $instance */
                $instance = new $class();

                return $instance->handle($request, $next);
            };
        }

        return $handler;
    }

    private static function invoke(array|string $handler, Request $request): Response
    {
        if (is_string($handler)) {
            $handler = [$handler, '__invoke'];
        }

        [$class, $method] = $handler;

        if (!class_exists($class)) {
            throw HttpException::notFound('El recurso solicitado no existe.');
        }

        $controller = new $class();

        if (!$controller instanceof Controller) {
            throw new \RuntimeException("{$class} debe heredar de App\\Controllers\\Controller");
        }

        $controller->setRequest($request);

        $result = $controller->{$method}($request);

        if ($result instanceof Response) {
            return $result;
        }
        if (is_array($result)) {
            return Response::json($result);
        }
        if (is_string($result)) {
            return Response::html($result);
        }

        throw new \RuntimeException(
            "El controlador {$class}::{$method} debe devolver un Response, un string o un array."
        );
    }

    // -----------------------------------------------------------------
    //  Errores
    // -----------------------------------------------------------------

    private function errorResponse(
        Request $request,
        int $status,
        string $message = '',
        ?string $title = null,
        array $headers = []
    ): Response {
        if ($request->wantsJson()) {
            return Response::json([
                'ok'      => false,
                'error'   => $title ?? 'Error',
                'status'  => $status,
                'message' => $message,
            ], $status);
        }

        $html = View::error($status, $message);

        $response = Response::html($html, $status);
        foreach ($headers as $name => $value) {
            $response->header($name, $value);
        }

        if ($status === 429) {
            $response->header('Retry-After', '60');
        }

        return $response;
    }

    /** Devuelve una URL segura (misma base) hacia la que volver. */
    private function refererOr(Request $request): string
    {
        $referer = $request->header('referer');
        if ($referer === null || $referer === '') {
            return url('/');
        }

        $base = rtrim((string) (parse_url(Env::getString('APP_URL', ''), PHP_URL_PATH) ?? ''), '/');
        $parts = parse_url($referer);

        if ($parts === false || !isset($parts['path'])) {
            return url('/');
        }

        $path = (string) $parts['path'];
        $query = isset($parts['query']) ? '?' . (string) $parts['query'] : '';

        // Sólo se redirige a destinos que empiecen por la base de la app
        if ($base !== '' && str_starts_with($path, $base . '/')) {
            return url(ltrim(substr($path, strlen($base)), '/') . $query);
        }

        if ($base === '') {
            return url(ltrim($path, '/') . $query);
        }

        return url('/');
    }

    private function registerErrorHandlers(): void
    {
        $debug = Env::getBool('APP_DEBUG');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            Logger::warning("{$message} @ {$file}:{$line}");

            if ($severity & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR)) {
                throw new \ErrorException($message, 0, $severity, $file, $line);
            }

            return true;
        });

        set_exception_handler(static function (\Throwable $e): void {
            Logger::critical('Excepción no capturada: ' . $e->getMessage(), [
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);

            $message = Env::getBool('APP_DEBUG')
                ? $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()
                : 'Se ha producido un error interno. El incidente ha quedado registrado.';

            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/html; charset=utf-8');
                Security::sendHeaders();
            }

            echo View::error(500, $message);
        });

        register_shutdown_function(static function (): void {
            $error = error_get_last();
            if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }

            Logger::critical('Error fatal: ' . $error['message'], [
                'file' => $error['file'] . ':' . $error['line'],
            ]);

            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/html; charset=utf-8');
                echo View::error(500, Env::getBool('APP_DEBUG')
                    ? $error['message'] . ' @ ' . $error['file'] . ':' . $error['line']
                    : 'Se ha producido un error interno.');
            }
        });
    }

    private function send(Response $response): void
    {
        Security::sendHeaders($response);
        $response->send();
    }
}
