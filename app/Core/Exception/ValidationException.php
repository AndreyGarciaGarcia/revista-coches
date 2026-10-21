<?php

declare(strict_types=1);

namespace App\Core\Exception;

/**
 * Fallos de validación de formulario o de API.
 * Cada entrada es campo => mensaje legible para el usuario.
 */
final class ValidationException extends \RuntimeException
{
    /** @var array<string,string> */
    private array $errors;

    /** @param array<string,string> $errors */
    public function __construct(array $errors, string $message = '')
    {
        parent::__construct($message !== '' ? $message : 'Revisa los campos marcados.');
        $this->errors = $errors;
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function first(): string
    {
        return $this->errors === [] ? $this->getMessage() : (string) reset($this->errors);
    }
}
