<?php

/**
 * Funciones globales de ayuda para las plantillas y los controladores.
 * Se incluye una sola vez desde public/index.php.
 */

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\Session;
use App\Core\Str;

if (!function_exists('e')) {
    /** Escapa un valor para imprimirlo con seguridad en HTML. */
    function e(mixed $value): string
    {
        if ($value === null || $value === false) {
            return '';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    /** URL absoluta de un recurso relativo a la raíz de la aplicación. */
    function url(string $path = ''): string
    {
        $base = rtrim(Env::getString('APP_URL'), '/');

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    /**
     * URL de un fichero estático con "cache busting" por versión.
     *   asset('css/app.css')  ->  .../assets/css/app.css?v=1a2b3c
     */
    function asset(string $path, bool $version = true): string
    {
        $url = url('assets/' . ltrim($path, '/'));

        if (!$version) {
            return $url;
        }

        static $hashes = [];
        if (!isset($hashes[$path])) {
            $file = dirname(__DIR__) . '/public/assets/' . ltrim($path, '/');
            $hashes[$path] = is_file($file)
                ? substr(md5_file($file) ?: '0', 0, 8)
                : substr(Env::getString('APP_KEY', 'v'), 0, 8);
        }

        return $url . '?v=' . $hashes[$path];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_meta')) {
    function csrf_meta(): string
    {
        return '<meta name="csrf-token" content="' . e(Csrf::token()) . '">';
    }
}

if (!function_exists('old')) {
    /** Valor previo del formulario (para repoblar tras un error). */
    function old(string $key, mixed $default = ''): mixed
    {
        $value = Session::old($key, $default);
        return $value === null ? $default : $value;
    }
}

if (!function_exists('flash_messages')) {
    /** @return array<int,array{type:string,message:string}> */
    function flash_messages(): array
    {
        return Session::pullFlash();
    }
}

if (!function_exists('auth_user')) {
    function auth_user(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('auth_check')) {
    function auth_check(): bool
    {
        return Auth::check();
    }
}

if (!function_exists('auth_can')) {
    function auth_can(int $level): bool
    {
        return Auth::can($level);
    }
}

if (!function_exists('euros')) {
    function euros(int|float $n): string
    {
        return Str::euros($n);
    }
}

if (!function_exists('num')) {
    function num(int|float $n, int $decimals = 1): string
    {
        return Str::num($n, $decimals);
    }
}

if (!function_exists('excerpt')) {
    function excerpt(string $text, int $length = 160): string
    {
        return Str::excerpt($text, $length);
    }
}

if (!function_exists('slugify')) {
    function slugify(string $text): string
    {
        return Str::slug($text);
    }
}

if (!function_exists('img')) {
    /**
     * <img> con lazy loading, alt obligatorio y dimensiones explícitas
     * (evita el desplazamiento de layout y satisface las Core Web Vitals).
     */
    function img(
        string $src,
        string $alt,
        array $attrs = []
    ): string {
        $full = preg_match('#^(https?:)?//#i', $src) ? $src : url('assets/img/' . ltrim($src, '/'));

        $width = isset($attrs['width']) ? (int) $attrs['width'] : null;
        $height = isset($attrs['height']) ? (int) $attrs['height'] : null;

        $html = '<img src="' . e($full) . '"';
        $html .= ' alt="' . e($alt) . '"';
        $html .= ' loading="' . e($attrs['loading'] ?? 'lazy') . '"';
        $html .= ' decoding="async"';
        if ($width) {
            $html .= ' width="' . $width . '"';
        }
        if ($height) {
            $html .= ' height="' . $height . '"';
        }
        if (!empty($attrs['class'])) {
            $html .= ' class="' . e($attrs['class']) . '"';
        }
        if (!empty($attrs['sizes'])) {
            $html .= ' sizes="' . e($attrs['sizes']) . '"';
        }
        $html .= '>';

        return $html;
    }
}

if (!function_exists('placeholder_img')) {
    /**
     * Imagen de reserva en SVG inline mientras carga o si el fichero
     * no existe: evita enlaces rotos visibles.
     */
    function placeholder_img(string $alt, string $class = ''): string
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 500">'
            . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
            . '<stop offset="0" stop-color="#1c1c22"/><stop offset="1" stop-color="#2b2b33"/>'
            . '</linearGradient></defs>'
            . '<rect width="800" height="500" fill="url(#g)"/>'
            . '<path d="M180 320h440M230 320c0-40 30-70 70-70h200c40 0 70 30 70 70" fill="none" stroke="#4a4a55" stroke-width="8" stroke-linecap="round"/>'
            . '<circle cx="290" cy="340" r="26" fill="none" stroke="#4a4a55" stroke-width="8"/>'
            . '<circle cx="510" cy="340" r="26" fill="none" stroke="#4a4a55" stroke-width="8"/>'
            . '</svg>';

        return '<div class="img-placeholder ' . e($class) . '" role="img" aria-label="' . e($alt) . '">'
            . $svg . '</div>';
    }
}

if (!function_exists('readable_time')) {
    /** Fecha larga en español: 12 de marzo de 2026 */
    function readable_time(?string $date, bool $withWeekday = false): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        $ts = strtotime($date);
        if ($ts === false) {
            return $date;
        }

        $months = [
            1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
            'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
        ];
        $weekdays = [
            'Sunday' => 'domingo', 'Monday' => 'lunes', 'Tuesday' => 'martes',
            'Wednesday' => 'miércoles', 'Thursday' => 'jueves',
            'Friday' => 'viernes', 'Saturday' => 'sábado',
        ];

        $prefix = '';
        if ($withWeekday) {
            $en = date('l', $ts);
            $prefix = ucfirst($weekdays[$en] ?? '') . ', ';
        }

        return $prefix . (int) date('j', $ts) . ' de '
            . ($months[(int) date('n', $ts)] ?? '') . ' de ' . date('Y', $ts);
    }
}

if (!function_exists('time_ago')) {
    /** "hace 3 días" — útil para la portada y los comentarios. */
    function time_ago(?string $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        $diff = time() - (int) strtotime($date);
        if ($diff < 0) {
            return 'ahora mismo';
        }

        $units = [
            31536000 => 'año', 2592000 => 'mes', 604800 => 'semana',
            86400 => 'día', 3600 => 'hora', 60 => 'minuto',
        ];

        foreach ($units as $seconds => $label) {
            if ($diff >= $seconds) {
                $n = (int) floor($diff / $seconds);
                return 'hace ' . $n . ' ' . $label . ($n > 1 ? 's' : '');
            }
        }

        return 'hace unos segundos';
    }
}

if (!function_exists('pagination')) {
    /**
     * HTML de paginación estilo coches.net.
     *
     * @param array<string,mixed> $query Parámetros extra a conservar
     */
    function pagination(int $page, int $pages, string $basePath, array $query = [], int $window = 2): string
    {
        if ($pages <= 1) {
            return '';
        }

        $link = static function (int $p) use ($basePath, $query): string {
            $query['pagina'] = $p;
            return e(url($basePath) . '?' . http_build_query($query));
        };

        $html = '<nav class="pagination" aria-label="Paginación">';
        $html .= $page > 1
            ? '<a class="page-btn prev" href="' . $link($page - 1) . '" rel="prev">&larr; Anterior</a>'
            : '<span class="page-btn prev is-disabled">&larr; Anterior</span>';

        $start = max(1, $page - $window);
        $end = min($pages, $page + $window);

        if ($start > 1) {
            $html .= '<a class="page-num" href="' . $link(1) . '">1</a>';
            if ($start > 2) {
                $html .= '<span class="page-gap">&hellip;</span>';
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            $html .= $i === $page
                ? '<span class="page-num is-current" aria-current="page">' . $i . '</span>'
                : '<a class="page-num" href="' . $link($i) . '">' . $i . '</a>';
        }

        if ($end < $pages) {
            if ($end < $pages - 1) {
                $html .= '<span class="page-gap">&hellip;</span>';
            }
            $html .= '<a class="page-num" href="' . $link($pages) . '">' . $pages . '</a>';
        }

        $html .= $page < $pages
            ? '<a class="page-btn next" href="' . $link($page + 1) . '" rel="next">Siguiente &rarr;</a>'
            : '<span class="page-btn next is-disabled">Siguiente &rarr;</span>';

        return $html . '</nav>';
    }
}

if (!function_exists('stars')) {
    /** Valoraciones como estrellas (accesible con aria-label). */
    function stars(float $rating, int $max = 5): string
    {
        $full = (int) round($rating);
        $html = '<span class="stars" role="img" aria-label="Valoración de ' . num($rating, 1) . ' sobre ' . $max . '">';
        for ($i = 1; $i <= $max; $i++) {
            $html .= $i <= $full ? '★' : '☆';
        }

        return $html . '</span>';
    }
}

if (!function_exists('img_src')) {
    /**
     * Normaliza una ruta guardada en base de datos («assets/img/…»)
     * al formato que espera el generador de <img>.
     */
    function img_src(?string $ruta): string
    {
        $ruta = trim((string) $ruta);

        if ($ruta === '') {
            return '';
        }

        if (preg_match('#^(https?:)?//#i', $ruta)) {
            return $ruta;
        }

        return ltrim(preg_replace('#^assets/img/#', '', $ruta) ?? $ruta, '/');
    }
}

if (!function_exists('asset_url')) {
    /**
     * URL pública de una imagen guardada en base de datos.
     *
     * `img_src()` devuelve la ruta «relativa a assets/img/» que necesita
     * el generador de <img>; para los atributos `src`/`href` literales
     * (visor ampliado, enlaces de créditos, JSON‑LD) hace falta además
     * el prefijo del directorio raíz de la web.
     */
    function asset_url(?string $ruta): string
    {
        $rel = img_src($ruta);

        if ($rel === '') {
            return '';
        }

        if (preg_match('#^(https?:)?//#i', $rel)) {
            return $rel;
        }

        return url('assets/img/' . ltrim($rel, '/'));
    }
}

if (!function_exists('imagen')) {
    /**
     * Imagen de contenido con red de seguridad: si el fichero no existe
     * (biblioteca incompleta, baja manual…) se dibuja el placeholder en
     * vez de dejar un hueco roto en la página.
     *
     * @param array<string,mixed> $attrs
     */
    function imagen(?string $ruta, string $alt, array $attrs = []): string
    {
        $rel = img_src($ruta);

        if ($rel === '') {
            return placeholder_img($alt, (string) ($attrs['class'] ?? ''));
        }

        if (!preg_match('#^(https?:)?//#i', $rel)) {
            $absoluto = dirname(__DIR__) . '/public/assets/img/' . $rel;

            if (!is_file($absoluto)) {
                return placeholder_img($alt, (string) ($attrs['class'] ?? ''));
            }
        }

        return img($rel, $alt, $attrs);
    }
}

if (!function_exists('categoria_chip')) {
    /** Categoría como etiqueta con el color que define la base de datos. */
    function categoria_chip(?string $nombre, ?string $color = null, ?string $slug = null): string
    {
        if ($nombre === null || $nombre === '') {
            return '';
        }

        $estilo = $color !== null && preg_match('/^#[0-9a-fA-F]{6}$/', $color)
            ? ' style="--chip:' . $color . '"'
            : '';

        $enlace = $slug !== null
            ? '<a href="' . e(url('categoria/' . $slug)) . '"' . $estilo . '>' . e($nombre) . '</a>'
            : '<span' . $estilo . '>' . e($nombre) . '</span>';

        return '<span class="chip">' . $enlace . '</span>';
    }
}

if (!function_exists('letra_marca')) {
    /**
     * Logotipo tipográfico de marca.
     *
     * Se descartan deliberadamente los emblemas de los fabricantes: son
     * marcas registradas y la letra comunica lo mismo sin apropiarse de
     * un signo ajeno.
     */
    function letra_marca(string $nombre, string $clase = ''): string
    {
        $inicial = mb_strtoupper(mb_substr(trim($nombre), 0, 1));
        $semilla = 0;
        $longitud = strlen($nombre);

        for ($i = 0; $i < $longitud; $i++) {
            $semilla = ($semilla * 31 + ord($nombre[$i])) % 360;
        }

        return '<span class="lettermark ' . e($clase) . '"'
            . ' style="--h:' . $semilla . '"'
            . ' aria-hidden="true">' . e($inicial) . '</span>';
    }
}

if (!function_exists('etiquetaCombustible')) {
    /** Etiqueta legible para el enum `combustible`. */
    function etiquetaCombustible(string $valor): string
    {
        return match ($valor) {
            'gasolina'          => 'Gasolina',
            'diesel'            => 'Diésel',
            'hibrido'           => 'Híbrido',
            'hibrido_enchufable'=> 'Híbrido enchufable',
            'electrico'         => 'Eléctrico',
            'gas_natural'       => 'Gas natural',
            default             => ucfirst($valor),
        };
    }
}

if (!function_exists('etiquetaTransmision')) {
    function etiquetaTransmision(string $valor): string
    {
        return match ($valor) {
            'manual'     => 'Manual',
            'automatica' => 'Automática',
            'dct'        => 'Doble embrague',
            'cvt'        => 'CVT',
            default      => ucfirst($valor),
        };
    }
}

if (!function_exists('etiquetaCategoria')) {
    function etiquetaCategoria(string $valor): string
    {
        return match ($valor) {
            'turismo'      => 'Turismo',
            'berlina'      => 'Berlina',
            'suv'          => 'SUV',
            'utilitario'   => 'Utilitario',
            'deportivo'    => 'Deportivo',
            'monovolumen'  => 'Monovolumen',
            'pickup'       => 'Pickup',
            'furgoneta'    => 'Furgoneta',
            'coupé'        => 'Coupé',
            'descapotable' => 'Descapotable',
            default        => ucfirst($valor),
        };
    }
}

if (!function_exists('etiquetaTraccion')) {
    function etiquetaTraccion(string $valor): string
    {
        return match ($valor) {
            'delantera'     => 'Delantera',
            'trasera'       => 'Trasera',
            'cuatro_ruedas' => '4×4',
            default         => ucfirst($valor),
        };
    }
}

if (!function_exists('registro_abierto')) {
    /**
     * ¿Está abierto el registro público de cuentas?
     *
     * Controlado por REGISTRO_ABIERTO en .env. Con el registro cerrado
     * (por defecto) sólo el propietario da de alta cuentas desde el
     * panel: nadie más puede crear identidades en la plataforma.
     */
    function registro_abierto(): bool
    {
        return \App\Core\Env::getBool('REGISTRO_ABIERTO', false);
    }
}
