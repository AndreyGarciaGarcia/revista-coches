<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Enrutador con rutas limpias (RESTful).
 *
 *   $router->get('noticias/{slug}', [NewsController::class, 'show']);
 *   $router->group('admin', fn (Router $r) => $r->get('...', ...), [Auth::class]);
 *
 * Soporta marcadores `{param}` y `{param:expresión_regular}`.
 */
final class Router
{
    /** @var array<int,array{method:string,pattern:string,compiled:string,handler:array|string,middleware:string[],name:?string}> */
    private array $routes = [];

    private string $prefix = '';

    /** @var string[] */
    private array $groupMiddleware = [];

    /** @var array<string,string> */
    private array $named = [];

    public function get(string $path, array|string $handler, array $middleware = [], ?string $name = null): self
    {
        return $this->add('GET', $path, $handler, $middleware, $name);
    }

    public function post(string $path, array|string $handler, array $middleware = [], ?string $name = null): self
    {
        return $this->add('POST', $path, $handler, $middleware, $name);
    }

    public function put(string $path, array|string $handler, array $middleware = [], ?string $name = null): self
    {
        return $this->add('PUT', $path, $handler, $middleware, $name);
    }

    public function delete(string $path, array|string $handler, array $middleware = [], ?string $name = null): self
    {
        return $this->add('DELETE', $path, $handler, $middleware, $name);
    }

    /** Para peticiones de formulario con _method=PUT/DELETE */
    public function any(string $path, array|string $handler, array $middleware = [], ?string $name = null): self
    {
        foreach (['GET', 'POST', 'PUT', 'DELETE'] as $method) {
            $this->add($method, $path, $handler, $middleware, $name);
        }
        return $this;
    }

    /**
     * Agrupación de rutas con prefijo y/o middleware compartido.
     *
     * @param callable(Router):void $callback
     * @param string[]              $middleware
     */
    public function group(string $prefix, callable $callback, array $middleware = []): self
    {
        $previousPrefix = $this->prefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->prefix = trim($previousPrefix . '/' . trim($prefix, '/'), '/');
        $this->groupMiddleware = array_values(array_unique([...$previousMiddleware, ...$middleware]));

        $callback($this);

        $this->prefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;

        return $this;
    }

    private function add(string $method, string $path, array|string $handler, array $middleware, ?string $name): self
    {
        $full = trim($this->prefix . '/' . trim($path, '/'), '/');
        $full = ltrim($full, '/');

        $this->routes[] = [
            'method'     => $method,
            'pattern'    => $full,
            'compiled'   => $this->compile($full),
            'handler'    => $handler,
            'middleware' => $this->groupMiddleware,
            'name'       => $name,
        ];

        if ($name !== null) {
            $this->named[$name] = $full;
        }

        return $this;
    }

    /** Convierte `coches/{marca}/{slug}` en una expresión regular con nombres. */
    private function compile(string $path): string
    {
        if ($path === '') {
            $path = '/';
        }

        $segments = explode('/', $path);
        $regex = '';

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            // La barra sólo se antepone si ya había segmentos: `match()`
            // recibe la ruta ya recortada, sin barra inicial.
            $sep = $regex === '' ? '' : '/';

            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)(?::(.+))?\}$/', $segment, $m)) {
                $name = $m[1];
                $inner = $m[2] ?? '[^/]+';
                // El patrón interno no debe contener barras no escapadas accidentales
                $regex .= $sep . '(?P<' . $name . '>' . $inner . ')';
            } else {
                $regex .= $sep . preg_quote($segment, '#');
            }
        }

        // Raíz: `''` (la ruta ya viene sin barras) ha de coincidir tanto
        // con '' como con '/'; por eso la barra inicial se hace opcional.
        return '#^' . $regex . '/?$#u';
    }

    /**
     * Busca la ruta que coincide con la petición.
     *
     * @return array{route:array, params:array<string,string>}|null
     */
    public function match(string $method, string $path): ?array
    {
        $path = trim($path, '/');
        $allowed = [];
        $this->lastAllowed = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['compiled'], $path, $matches)) {
                continue;
            }

            if ($route['method'] === $method) {
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = rawurldecode($value);
                    }
                }

                return ['route' => $route, 'params' => $params];
            }

            $allowed[] = $route['method'];
        }

        // Misma ruta, otro método -> 405 con cabecera Allow
        if ($allowed !== []) {
            $this->lastAllowed = array_values(array_unique($allowed));
        }

        return null;
    }

    /** @var string[] */
    private array $lastAllowed = [];

    /** @return string[] */
    public function allowedMethods(): array
    {
        return $this->lastAllowed;
    }

    public function resetAllowed(): void
    {
        $this->lastAllowed = [];
    }

    /** URL canónica de una ruta nombrada (usada en vistas y sitemap). */
    public function url(string $name, array $params = []): string
    {
        $path = $this->named[$name] ?? $name;

        foreach ($params as $key => $value) {
            $path = preg_replace('/\{' . preg_quote($key, '/') . '(?::[^}]+)?\}/', rawurlencode((string) $value), $path) ?? $path;
        }

        return $this->base() . '/' . trim($path, '/');
    }

    private function base(): string
    {
        return rtrim(Env::getString('APP_URL'), '/');
    }

    /** @return array<int,array{method:string,pattern:string}> */
    public function routes(): array
    {
        return array_map(
            static fn (array $r): array => ['method' => $r['method'], 'pattern' => $r['pattern']],
            $this->routes
        );
    }
}
