<?php

declare(strict_types=1);

namespace App\Core\Exception;

/**
 * Error HTTP controlado: el front controller lo captura y pinta la
 * página de error correspondiente (o un JSON si la petición es API).
 */
final class HttpException extends \RuntimeException
{
    /** @var array<string,string> */
    private array $headers;

    /**
     * @param array<string,string> $headers
     */
    public function __construct(
        private readonly int $httpStatus,
        string $message = '',
        array $headers = []
    ) {
        parent::__construct($message !== '' ? $message : self::defaultMessage($httpStatus), $httpStatus);
        $this->headers = $headers;
    }

    public function status(): int
    {
        return $this->httpStatus;
    }

    /** @return array<string,string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public static function notFound(string $message = ''): self
    {
        return new self(404, $message);
    }

    public static function forbidden(string $message = ''): self
    {
        return new self(403, $message);
    }

    public static function unauthorized(string $message = ''): self
    {
        return new self(401, $message);
    }

    public static function methodNotAllowed(array $allowed): self
    {
        return new self(405, '', ['Allow' => implode(', ', $allowed)]);
    }

    public static function badRequest(string $message = ''): self
    {
        return new self(400, $message);
    }

    public static function tooManyRequests(string $message = ''): self
    {
        return new self(429, $message);
    }

    public static function conflict(string $message = ''): self
    {
        return new self(409, $message);
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'La petición no es válida.',
            401 => 'Debes identificarte para continuar.',
            403 => 'No tienes permisos para acceder a este recurso.',
            404 => 'La página que buscas no existe o ha sido movida.',
            405 => 'Método no permitido.',
            409 => 'La operación entra en conflicto con el estado actual.',
            429 => 'Demasiadas peticiones. Espera unos segundos.',
            default => 'Se ha producido un error.',
        };
    }
}
