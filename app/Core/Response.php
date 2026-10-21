<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Respuesta HTTP. Se construye devolviéndola desde los controladores
 * y se envía al final del ciclo (App::run()).
 */
final class Response
{
    private int $status = 200;

    /** @var array<string,string[]> */
    private array $headers = [];

    private string $body = '';

    private function __construct()
    {
    }

    public static function html(string $body, int $status = 200): self
    {
        $r = new self();
        $r->body = $body;
        $r->status = $status;
        $r->headers['Content-Type'] = ['text/html; charset=utf-8'];

        return $r;
    }

    public static function json(mixed $data, int $status = 200): self
    {
        $r = new self();
        $r->status = $status;
        $r->headers['Content-Type'] = ['application/json; charset=utf-8'];

        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if (Env::getBool('APP_DEBUG')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $r->body = json_encode($data, $flags) ?: '{}';

        return $r;
    }

    public static function redirect(string $to, int $status = 302): self
    {
        $r = new self();
        $r->status = $status;
        $r->headers['Location'] = [$to];
        $r->body = '<!doctype html><meta charset="utf-8"><meta http-equiv="refresh" content="0;url='
            . htmlspecialchars($to, ENT_QUOTES) . '"><a href="' . htmlspecialchars($to, ENT_QUOTES)
            . '">Continuar</a>';

        return $r;
    }

    public static function noContent(int $status = 204): self
    {
        $r = new self();
        $r->status = $status;
        return $r;
    }

    public function status(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = [$value];
        return $this;
    }

    /** Cabecera multi-valor (p. ej. Link, Set-Cookie). */
    public function addHeader(string $name, string $value): self
    {
        $this->headers[$name][] = $value;
        return $this;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    /** @return array<string,string[]> */
    public function headersList(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            foreach ($this->headers as $name => $values) {
                foreach (array_values($values) as $index => $value) {
                    // Primera cabecera sustituye; las siguientes se añaden
                    header($name . ': ' . $value, $index === 0);
                }
            }
        }

        echo $this->body;
    }
}
