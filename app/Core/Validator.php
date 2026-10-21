<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exception\ValidationException;

/**
 * Validador declarativo de datos de entrada.
 *
 *   $data = Validator::make($_POST, [
 *       'email'    => 'required|email|max:190',
 *       'password' => 'required|min:8|max:72',
 *       'edad'     => 'sometimes|integer|min:18',
 *   ])->validated();          // lanza ValidationException si falla
 *
 * Reglas disponibles:
 *   required, sometimes, nullable, string, integer, numeric, boolean,
 *   min:n, max:n, between:a:b, email, url, slug, date, in:a,b,c,
 *   regex:…, confirmed, unique:tabla.columna, exists:tabla.columna,
 *   image, file, alpha, alnum, json
 */
final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    /** @var array<string,mixed> */
    private array $clean = [];

    /**
     * @param array<string,mixed> $data
     * @param array<string,string> $rules
     * @param array<string,string> $labels  Nombres legibles para los mensajes
     */
    private function __construct(
        private readonly array $data,
        private readonly array $rules,
        private readonly array $labels = []
    ) {
        $this->run();
    }

    /** @param array<string,mixed> $data @param array<string,string> $rules */
    public static function make(array $data, array $rules, array $labels = []): self
    {
        return new self($data, $rules, $labels);
    }

    /** Valida y devuelve sólo los campos permitidos (whitelist implícito). */
    public function validated(): array
    {
        if ($this->errors !== []) {
            throw new ValidationException($this->errors);
        }

        return $this->clean;
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function validatedData(): array
    {
        return $this->clean;
    }

    private function label(string $field): string
    {
        return $this->labels[$field] ?? $field;
    }

    private function fail(string $field, string $message): void
    {
        $this->errors[$field] ??= sprintf($message, $this->label($field));
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = array_filter(explode('|', $ruleString));
            $exists = array_key_exists($field, $this->data);
            $value = $this->data[$field] ?? null;

            $isPresent = $exists && (!is_string($value) || trim($value) !== '');

            if (in_array('sometimes', $rules, true) && !$exists) {
                continue;
            }

            if (!$isPresent) {
                if (in_array('required', $rules, true)) {
                    $this->fail($field, 'El campo %s es obligatorio.');
                } elseif (in_array('nullable', $rules, true) || $exists) {
                    $this->clean[$field] = null;
                }
                continue;
            }

            // Normalización básica de strings
            if (is_string($value)) {
                $value = trim(preg_replace('/\r\n|\r|\n/u', "\n", $value) ?? $value);
                // Neutraliza intentos de inyección de cabeceras (CRLF)
                $value = str_replace(["\0", "\x1A"], '', $value);
            }

            foreach ($rules as $rule) {
                [$name, $param] = $this->split($rule);

                if (!$this->check($name, $param, $field, $value)) {
                    break;   // un fallo por campo es suficiente
                }
            }

            $this->clean[$field] = $this->coerce($rules, $value);
        }
    }

    /** @return array{0:string,1:?string} */
    private function split(string $rule): array
    {
        $pos = strpos($rule, ':');
        if ($pos === false) {
            return [$rule, null];
        }

        return [substr($rule, 0, $pos), substr($rule, $pos + 1)];
    }

    private function check(string $name, ?string $param, string $field, mixed $value): bool
    {
        $isString = is_string($value);

        switch ($name) {
            case 'required':
            case 'sometimes':
            case 'nullable':
                return true;

            case 'string':
                if (!$isString && !is_numeric($value)) {
                    $this->fail($field, 'El campo %s debe ser texto.');
                    return false;
                }
                return true;

            case 'integer':
                if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->fail($field, 'El campo %s debe ser un número entero.');
                    return false;
                }
                return true;

            case 'numeric':
                if (!is_numeric($value)) {
                    $this->fail($field, 'El campo %s debe ser numérico.');
                    return false;
                }
                return true;

            case 'boolean':
                if (!is_bool($value) && !in_array($value, ['0', '1', 0, 1, true, false, 'true', 'false', 'on', 'off'], true)) {
                    $this->fail($field, 'El campo %s debe ser verdadero o falso.');
                    return false;
                }
                return true;

            case 'email':
                $ok = is_string($value)
                    && strlen($value) <= 190
                    && filter_var($value, FILTER_VALIDATE_EMAIL) !== false
                    && !preg_match('/[\r\n]/', $value);
                if (!$ok) {
                    $this->fail($field, 'El campo %s debe ser una dirección de correo válida.');
                    return false;
                }
                return true;

            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->fail($field, 'El campo %s debe ser una URL válida (https://…).');
                    return false;
                }
                return true;

            case 'slug':
                if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $value)) {
                    $this->fail($field, 'El campo %s sólo puede contener letras, números y guiones.');
                    return false;
                }
                return true;

            case 'alpha':
                if (!preg_match('/^[\pL\s]+$/u', (string) $value)) {
                    $this->fail($field, 'El campo %s sólo puede contener letras.');
                    return false;
                }
                return true;

            case 'alnum':
                if (!preg_match('/^[a-zA-Z0-9]+$/', (string) $value)) {
                    $this->fail($field, 'El campo %s sólo puede contener letras y números.');
                    return false;
                }
                return true;

            case 'date':
                if (!preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $value) || !strtotime((string) $value)) {
                    $this->fail($field, 'El campo %s debe ser una fecha válida.');
                    return false;
                }
                return true;

            case 'json':
                if (json_decode((string) $value, true) === null && json_last_error() !== JSON_ERROR_NONE) {
                    $this->fail($field, 'El campo %s debe contener JSON válido.');
                    return false;
                }
                return true;

            case 'min':
                $len = $this->size($value);
                if ($len < (int) $param) {
                    $this->fail($field, sprintf('El campo %%s debe tener al menos %s caracteres.', $param));
                    return false;
                }
                return true;

            case 'max':
                $len = $this->size($value);
                if ($len > (int) $param) {
                    $this->fail($field, sprintf('El campo %%s no puede superar %s caracteres.', $param));
                    return false;
                }
                return true;

            case 'between':
                [$min, $max] = array_pad(explode(',', (string) $param), 2, '0');
                $len = $this->size($value);
                if ($len < (int) $min || $len > (int) $max) {
                    $this->fail($field, sprintf('El campo %%s debe estar entre %s y %s.', $min, $max));
                    return false;
                }
                return true;

            case 'in':
                $allowed = explode(',', (string) $param);
                if (!in_array((string) $value, $allowed, true)) {
                    $this->fail($field, sprintf('El campo %%s debe ser uno de: %s.', $param));
                    return false;
                }
                return true;

            case 'not_in':
                $banned = explode(',', (string) $param);
                if (in_array((string) $value, $banned, true)) {
                    $this->fail($field, 'El campo %s contiene un valor no permitido.');
                    return false;
                }
                return true;

            case 'regex':
                if (!preg_match('/' . str_replace('/', '\/', (string) $param) . '/u', (string) $value)) {
                    $this->fail($field, 'El formato del campo %s no es válido.');
                    return false;
                }
                return true;

            case 'confirmed':
                $other = $this->data[$field . '_confirmation'] ?? null;
                if ($other === null || (string) $other !== (string) $value) {
                    $this->fail($field, 'La confirmación de %s no coincide.');
                    return false;
                }
                return true;

            case 'exists':
                return $this->checkExists($param, $field, (string) $value);

            case 'unique':
                return $this->checkUnique($param, $field, (string) $value);

            case 'image':
                if (!is_string($value) || !preg_match('/\.(jpe?g|png|webp|gif|avif)$/i', $value)) {
                    $this->fail($field, 'El campo %s debe ser una imagen (JPG, PNG, WebP o AVIF).');
                    return false;
                }
                return true;

            case 'file':
                return true;   // la comprobación real se hace en ImageService

            case 'no_html':
                if (preg_match('/<(script|iframe|object|embed|form)\b/i', (string) $value)) {
                    $this->fail($field, 'El campo %s no puede contener código HTML.');
                    return false;
                }
                return true;

            default:
                return true;
        }
    }

    private function size(mixed $value): int
    {
        if (is_array($value)) {
            return count($value);
        }
        if (is_numeric($value)) {
            return (int) $value;      // comparación numérica para min:/max:
        }

        return mb_strlen((string) $value);
    }

    private function coerce(array $rules, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (in_array('integer', $rules, true)) {
            return (int) $value;
        }
        if (in_array('numeric', $rules, true)) {
            return (float) $value;
        }
        if (in_array('boolean', $rules, true)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }
        if (in_array('json', $rules, true)) {
            return json_decode((string) $value, true);
        }

        return $value;
    }

    private function checkExists(?string $param, string $field, string $value): bool
    {
        [$table, $column] = array_pad(explode('.', (string) $param), 2, 'id');

        try {
            $found = Database::get()->count(
                'SELECT COUNT(*) FROM `' . preg_replace('/[^a-z0-9_]/i', '', $table) . '`
                  WHERE `' . preg_replace('/[^a-z0-9_]/i', '', $column) . '` = :v LIMIT 1',
                ['v' => $value]
            );
        } catch (\Throwable) {
            return true;   // si la tabla no existe aún, no bloqueamos
        }

        if ($found === 0) {
            $this->fail($field, 'El valor indicado en %s no existe.');
            return false;
        }

        return true;
    }

    private function checkUnique(?string $param, string $field, string $value): bool
    {
        // formato: tabla.columna,tabla.columna:id   (id = ignorar este registro)
        $parts = explode(',', (string) $param);
        [$table, $column] = array_pad(explode('.', $parts[0] ?? ''), 2, 'id');
        $ignoreId = isset($parts[1]) ? (int) $parts[1] : 0;

        $table = preg_replace('/[^a-z0-9_]/i', '', $table);
        $column = preg_replace('/[^a-z0-9_]/i', '', $column);

        try {
            $sql = 'SELECT COUNT(*) FROM `' . $table . '` WHERE `' . $column . '` = :v';
            $params = ['v' => $value];
            if ($ignoreId > 0) {
                $sql .= ' AND id <> :ignore';
                $params['ignore'] = $ignoreId;
            }
            $found = Database::get()->count($sql, $params);
        } catch (\Throwable) {
            return true;
        }

        if ($found > 0) {
            $this->fail($field, 'El valor de %s ya está en uso.');
            return false;
        }

        return true;
    }
}
