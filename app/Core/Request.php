<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Representación de la petición HTTP entrante.
 * Inmutable: no se modifica tras construirse.
 */
final class Request
{
    private string $method;
    private string $path;          // ruta normalizada, sin barra inicial/final
    private string $rawPath;
    private string $query;

    /** @var array<string,mixed> */
    private array $body;

    /** @var array<string,mixed> copia normalizada de $_GET */
    private array $getDatos = [];

    /** @var array<string,string> */
    private array $headers;

    /** @var array<string,mixed> */
    private array $files;

    /** @var array<string,string> */
    private array $routeParams = [];

    private ?array $json = null;

    /**
     * @param array<string,mixed> $server
     * @param array<string,mixed> $get
     * @param array<string,mixed> $post
     * @param array<string,mixed> $files
     */
    public function __construct(
        array $server = [],
        array $get = [],
        array $post = [],
        array $files = []
    ) {
        $this->method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        $this->rawPath = (string) ($server['REQUEST_URI'] ?? '/');

        $parsed = parse_url($this->rawPath);
        $this->path = (string) ($parsed['path'] ?? '/');
        $this->query = (string) ($parsed['query'] ?? '');

        // Cabeceras normalizadas a minúsculas
        $this->headers = [];

        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $this->headers[strtolower($name)] = (string) $value;
            }
        }
        if (isset($server['CONTENT_TYPE'])) {
            $this->headers['content-type'] = (string) $server['CONTENT_TYPE'];
        }
        if (isset($server['CONTENT_LENGTH'])) {
            $this->headers['content-length'] = (string) $server['CONTENT_LENGTH'];
        }

        $this->body = self::utf8($post);
        $this->getDatos = self::utf8($get);
        $this->files = $files;
    }

    public static function capture(): self
    {
        return new self($_SERVER, $_GET, $_POST, $_FILES);
    }

    /**
     * Devuelve la ruta limpia (sin parámetros de query, sin `../`),
     * ya descontada la base de la aplicación (APP_URL).
     */
    public function path(): string
    {
        $path = rawurldecode($this->path);
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        $path = trim($path, '/');

        $base = $this->basePath();
        if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
            $path = ltrim(substr($path, strlen($base)), '/');
        }

        return $path;
    }

    /** Ruta relativa a la raíz del proyecto (para redirecciones 301). */
    public function basePath(): string
    {
        $path = parse_url(Env::getString('APP_URL', ''), PHP_URL_PATH);

        return trim((string) $path, '/');
    }

    public function rawPath(): string
    {
        return $this->path;
    }

    public function query(): string
    {
        return $this->query;
    }

    public function method(): string
    {
        return $this->method;
    }

    /** ¿Es una petición idempotente? (lo usan las rutas API) */
    public function isRead(): bool
    {
        return $this->method === 'GET' || $this->method === 'HEAD';
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    /** @return array<string,string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->get($key) ?? $default;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->getDatos[$key] ?? $default;
    }

    /**
     * Garantiza que los datos del cliente son UTF-8 válido.
     *
     * El navegador siempre envía UTF-8, pero un cliente roto (o una
     * petición automatizada con otra codificación) podría mandar bytes
     * inválidos que MySQL rechazaría con «Incorrect string value».
     * Aquí se sustituyen por «?» antes de que lleguen a la base de datos.
     *
     * @param array<string,mixed> $datos
     * @return array<string,mixed>
     */
    private static function utf8(array $datos): array
    {
        foreach ($datos as $clave => $valor) {
            if (is_string($valor)) {
                $datos[$clave] = mb_convert_encoding($valor, 'UTF-8', 'UTF-8');
            } elseif (is_array($valor)) {
                $datos[$clave] = self::utf8($valor);
            }
        }

        return $datos;
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return $this->body;
    }

    /** @return array<string,mixed> */
    public function files(): array
    {
        return $this->files;
    }

    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return $file;
    }

    /** Cuerpo decodificado si la petición es application/json. */
    public function json(): array
    {
        if ($this->json !== null) {
            return $this->json;
        }

        if (!$this->isJson()) {
            return $this->json = [];
        }

        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);

        return $this->json = is_array($decoded) ? self::utf8($decoded) : [];
    }

    public function isJson(): bool
    {
        return str_contains((string) $this->header('content-type', ''), 'application/json')
            || str_contains((string) $this->header('accept', ''), 'application/json');
    }

    /** Datos combinados: JSON (si lo hay) + formulario. */
    public function payload(): array
    {
        return $this->isJson() ? ($this->json() ?: $this->body) : $this->body;
    }

    public function wantsJson(): bool
    {
        return str_starts_with($this->path(), 'api/')
            || str_contains((string) $this->header('accept', ''), 'application/json')
            || $this->header('x-requested-with') === 'XMLHttpRequest';
    }

    /** Parámetros de ruta capturados por el router. */
    public function param(string $key, ?string $default = null): ?string
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function intParam(string $key, int $default = 0): int
    {
        $value = $this->param($key);
        return $value === null || !ctype_digit($value) ? $default : (int) $value;
    }

    /** @param array<string,string> $params */
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    /**
     * IP real del cliente respetando proxy de confianza (X-Forwarded-For
     * sólo si la conexión viene de un rango privado).
     */
    public function ip(): string
    {
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        if (filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            && $proxy = $this->header('x-forwarded-for')) {
            $first = trim(explode(',', $proxy)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP) !== false) {
                return $first;
            }
        }

        return $remote;
    }

    public function userAgent(): string
    {
        return mb_substr((string) $this->header('user-agent', ''), 0, 512);
    }

    /** Identificador de sesión del navegador (para límite de peticiones). */
    public function clientKey(): string
    {
        return $this->ip() . '|' . sha1($this->userAgent());
    }
}
