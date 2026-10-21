<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Motor de plantillas (PHP puro, sin plantillas externas).
 *
 *   View::render('pages/home', ['noticias' => $n]);
 *
 * Convienciones:
 *   templates/layouts/<archivo>.php   -> envoltorio (recibe $content)
 *   templates/pages/<ruta>/<archivo>.php -> contenido (recibe $data)
 *   templates/partials/<archivo>.php  -> trozos reutilizables
 *
 * Las plantillas sólo ven las variables que se les pasa explícitamente.
 */
final class View
{
    private const BASE = 'templates';

    /** @var array<string,mixed> */
    private static array $shared = [];

    /** Variables disponibles en TODAS las plantillas. */
    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    /** @param array<string,mixed> $data */
    public static function render(string $template, array $data = [], ?string $layout = 'layouts/site'): string
    {
        $content = self::partial($template, $data);

        if ($layout === null) {
            return $content;
        }

        return self::partial($layout, $data + ['content' => $content]);
    }

    /** @param array<string,mixed> $data */
    public static function partial(string $template, array $data = []): string
    {
        $file = dirname(__DIR__, 2) . '/' . self::BASE . '/' . $template . '.php';

        if (!is_file($file)) {
            throw new \RuntimeException("No existe la plantilla: {$template}", 404);
        }

        // Aislamiento: cada plantilla recibe sus propias variables
        $vars = self::$shared + $data;
        extract($vars, EXTR_SKIP);

        // La propia plantilla no debe escapar HTML de $content accidentalmente
        unset($vars);

        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }

    /** Renderiza una página de error con su plantilla propia. */
    public static function error(int $status, string $message = ''): string
    {
        $template = match ($status) {
            403     => 'pages/errors/403',
            404     => 'pages/errors/404',
            429     => 'pages/errors/429',
            500     => 'pages/errors/500',
            default => 'pages/errors/error',
        };

        try {
            $titulo = match ($status) {
                401    => 'Identifícate para continuar',
                403    => 'Acceso denegado',
                404    => 'Página no encontrada',
                429    => 'Demasiadas peticiones',
                500    => 'Error interno del servidor',
                503    => 'Servicio no disponible',
                default => 'Se ha producido un error',
            };

            $descripcion = match (true) {
                $status === 403 => 'No tienes permisos para acceder a esta zona de MotorPress.',
                $status === 404 => 'La página que buscas no existe o ha cambiado de dirección en MotorPress.',
                $status === 429 => 'Demasiadas peticiones: espera unos segundos y vuelve a intentarlo.',
                $status >= 500  => 'Se ha producido un error interno en MotorPress; el incidente ha quedado registrado.',
                default         => 'La solicitud no se ha podido completar en MotorPress.',
            };

            return self::render($template, [
                'status'      => $status,
                'message'     => $message,
                'title'       => $titulo,
                'description' => $descripcion,
                'robots'      => $status === 404 ? 'noindex, follow' : 'noindex, nofollow',
            ]);
        } catch (\Throwable) {
            return '<!doctype html><meta charset="utf-8"><title>' . $status
                . '</title><h1>' . $status . '</h1><p>' . htmlspecialchars($message) . '</p>';
        }
    }
}
