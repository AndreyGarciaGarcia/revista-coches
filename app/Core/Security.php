<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Capa de seguridad transversal.
 *
 *  - Cabeceras defensivas en TODA respuesta (CSP, X-Frame-Options…)
 *  - Nonce por petición para los pocos scripts inline necesarios
 *  - Detección y registro de patrones de ataque en la entrada
 *  - Redirección a HTTPS cuando la app está configurada como segura
 */
final class Security
{
    private static string $nonce = '';

    /**
     * ¿La petición actual apunta al panel o a la puerta de acceso?
     * Se decide al enrutar y se usa al enviar las cabeceras, para que
     * las respuestas 401/403/404 del panel también salgan endurecidas.
     */
    private static bool $panel = false;

    /** Nonce de contenido para <script> inline (JSON-LD, etc.). */
    public static function nonce(): string
    {
        if (self::$nonce === '') {
            self::$nonce = bin2hex(random_bytes(16));
        }

        return self::$nonce;
    }

    /**
     * Se ejecuta antes de enrutar. Registra intentos de explotación
     * (SQLi, XSS, path traversal) sin bloquear peticiones legítimas.
     */
    public static function apply(Request $request): void
    {
        $ruta = $request->path();
        self::$panel = $ruta === 'admin'
            || str_starts_with($ruta, 'admin/')
            || str_starts_with($ruta, 'acceso');

        $probe = $ruta . ' ' . $request->query();

        $patterns = [
            'sql'     => '/(\bunion\b[\s\S]{0,30}\bselect\b|\bsleep\s*\(|\bbenchmark\s*\(|\bor\b\s+1\s*=\s*1|information_schema|load_file\s*\(|\bdrop\s+table\b|\binsert\s+into\b[\s\S]{0,20}\bvalues\b)/i',
            'xss'     => '/(<script\b|javascript\s*:|onerror\s*=|onload\s*=|onmouseover\s*=|document\.cookie|alert\s*\()/i',
            'traversal' => '/(\.\.\/|\.\.\\\\|%2e%2e%2f|\/etc\/passwd|boot\.ini|win\.ini)/i',
            'rce'     => '/(\bexec\s*\(|\beval\s*\(|passthru|shell_exec|system\s*\(|proc_open|php\s*:\s*\/\/)/i',
        ];

        foreach ($patterns as $type => $regex) {
            if (preg_match($regex, $probe)) {
                Logger::security('Patrón de ataque detectado', [
                    'tipo'   => $type,
                    'ip'     => $_SERVER['REMOTE_ADDR'] ?? '-',
                    'metodo' => $request->method(),
                    'ruta'   => substr($request->rawPath(), 0, 300),
                ]);
                break;
            }
        }

        // Límite de tamaño del cuerpo (evita DoS por POST gigantes)
        $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($length > 8 * 1024 * 1024) {
            throw new \App\Core\Exception\HttpException(413, 'El cuerpo de la petición es demasiado grande.');
        }

        // HTTPS obligatorio si la URL base es https
        if (str_starts_with(Env::getString('APP_URL'), 'https://')
            && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') !== 'https'
            && (($_SERVER['HTTPS'] ?? 'off') !== 'on')) {
            $target = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $request->rawPath();
            header('Location: ' . $target, true, 301);
            exit;
        }
    }

    /** Envía las cabeceras de seguridad. */
    public static function sendHeaders(?Response $response = null): void
    {
        if (headers_sent()) {
            return;
        }

        foreach ((self::config()['headers'] ?? []) as $name => $value) {
            if ($value !== null && $value !== '') {
                header($name . ': ' . $value);
            }
        }

        if (self::$panel) {
            // Panel y puerta de acceso: nada puede incrustarlas en un
            // iframe, no se cachean y no emiten referencias hacia fuera.
            // Se aplica aquí (y no en el middleware) para que las
            // respuestas 401/403/404 del panel salgan igual de duras.
            header('X-Frame-Options: DENY');
            header('Referrer-Policy: no-referrer');
            header('Cache-Control: no-store, private');
        }

        header('Content-Security-Policy: ' . (self::$panel ? self::cspPanel() : self::csp()));

        if ($response !== null) {
            $contentType = $response->headersList()['Content-Type'][0] ?? '';
            if (!str_contains($contentType, 'text/html')) {
                // El CSP sólo interesa en HTML: no estorbar a JSON/JS/CSS
                header_remove('Content-Security-Policy');
            }
        }
    }

    /** CSP de las respuestas normales del sitio. */
    public static function csp(): string
    {
        $csp = self::config()['csp'] ?? "default-src 'self'";

        // El nonce permite los pocos <script> inline del proyecto
        return str_replace("script-src 'self'", "script-src 'self' 'nonce-" . self::nonce() . "'", $csp);
    }

    /**
     * CSP del panel de administración: idéntica a la del sitio salvo en
     * que nada, bajo ninguna condición, puede incrustar estas páginas
     * en un iframe (robo de credenciales por «clickjacking»).
     */
    public static function cspPanel(): string
    {
        $csp = self::csp();

        $csp = str_replace("frame-ancestors 'self'", "frame-ancestors 'none'", $csp);

        return $csp;
    }

    /** @return array<string,mixed> */
    private static function config(): array
    {
        $file = dirname(__DIR__, 2) . '/config/security.php';

        return is_file($file) ? (require $file) : [];
    }

    /**
     * Valida la fortaleza de una contraseña según la política del proyecto.
     *
     * @return array<int,string> Lista vacía si es válida
     */
    public static function passwordIssues(string $password): array
    {
        $policy = self::config()['password'] ?? [];
        $issues = [];

        $min = (int) ($policy['min'] ?? 10);
        if (mb_strlen($password) < $min) {
            $issues[] = "Debe tener al menos {$min} caracteres.";
        }
        $max = (int) ($policy['max'] ?? 200);
        if (mb_strlen($password) > $max) {
            $issues[] = "No puede superar {$max} caracteres.";
        }
        if (!empty($policy['require_upper']) && !preg_match('/[A-ZÁÉÍÓÚÜÑ]/u', $password)) {
            $issues[] = 'Debe contener al menos una mayúscula.';
        }
        if (!empty($policy['require_lower']) && !preg_match('/[a-záéíóúüñ]/u', $password)) {
            $issues[] = 'Debe contener al menos una minúscula.';
        }
        if (!empty($policy['require_digit']) && !preg_match('/\d/', $password)) {
            $issues[] = 'Debe contener al menos un número.';
        }
        if (!empty($policy['require_special']) && !preg_match('/[^\pL\s\d]/u', $password)) {
            $issues[] = 'Debe contener al menos un símbolo (!@#$%…).';
        }

        $lower = mb_strtolower($password);
        foreach (($policy['common'] ?? []) as $banned) {
            if (str_contains($lower, $banned)) {
                $issues[] = 'Es demasiado común; elige otra contraseña.';
                break;
            }
        }

        return $issues;
    }

    // -----------------------------------------------------------------
    //  Higiene de HTML generado por el usuario
    // -----------------------------------------------------------------

    /**
     * Lista blanca de etiquetas admitidas en el cuerpo de un artículo.
     *
     * @return array<int,string>
     */
    public static function etiquetasPermitidas(): array
    {
        return [
            'p', 'h2', 'h3', 'h4',
            'strong', 'b', 'em', 'i', 'u', 's', 'small', 'mark',
            'ul', 'ol', 'li',
            'blockquote', 'hr', 'br',
            'figure', 'figcaption', 'img',
            'a', 'code', 'pre',
        ];
    }

    /**
     * Devuelve HTML saneado: sólo etiquetas y atributos de la lista blanca,
     * sin scripts, sin manejadores de eventos y sin URL «javascript:».
     *
     * Se usa al guardar el cuerpo de un artículo y vuelve a aplicarse en el
     * momento de mostrarlo (defensa en profundidad).
     */
    public static function htmlSeguro(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $permitidas = self::etiquetasPermitidas();

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $previo = libxml_use_internal_errors(true);

        $doc->loadHTML(
            '<meta charset="utf-8"><div id="mp-raiz">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        $raiz = $doc->getElementById('mp-raiz');

        if ($raiz === null) {
            return '';
        }

        self::recorrer($raiz, $permitidas);

        $salida = '';
        foreach ($raiz->childNodes as $hijo) {
            $salida .= (string) $doc->saveHTML($hijo);
        }

        return trim($salida);
    }

    /** Versión en texto plano: sin ninguna etiqueta. */
    public static function textoSeguro(?string $texto): string
    {
        return trim(strip_tags(html_entity_decode((string) $texto, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    /** @param array<int,string> $permitidas */
    private static function recorrer(\DOMNode $nodo, array $permitidas): void
    {
        $hijos = [];

        foreach ($nodo->childNodes as $hijo) {
            $hijos[] = $hijo;
        }

        $bloqueados = [
            'script', 'style', 'iframe', 'frame', 'object', 'embed', 'applet',
            'form', 'input', 'button', 'select', 'textarea', 'option',
            'link', 'meta', 'base', 'title', 'head',
            'svg', 'math', 'video', 'audio', 'source', 'track', 'canvas',
            'noscript', 'template', 'dialog',
        ];

        foreach ($hijos as $hijo) {
            if ($hijo->nodeType === XML_COMMENT_NODE
                || $hijo->nodeType === XML_PI_NODE
                || $hijo->nodeType === XML_CDATA_SECTION_NODE
                || $hijo->nodeType === XML_DTD_NODE) {
                $nodo->removeChild($hijo);
                continue;
            }

            if ($hijo->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }

            /** @var \DOMElement $hijo */
            $etiqueta = strtolower($hijo->nodeName);

            // Elementos que se eliminan por completo junto con su contenido
            if (in_array($etiqueta, $bloqueados, true)) {
                $nodo->removeChild($hijo);
                continue;
            }

            // Cualquier otra etiqueta fuera de la lista se desempaqueta:
            // se conserva el texto, se descarta la envoltura.
            if (!in_array($etiqueta, $permitidas, true)) {
                self::recorrer($hijo, $permitidas);

                while ($hijo->firstChild !== null) {
                    $nodo->insertBefore($hijo->firstChild, $hijo);
                }

                $nodo->removeChild($hijo);
                continue;
            }

            self::limpiarAtributos($hijo, $etiqueta);
            self::recorrer($hijo, $permitidas);
        }
    }

    private static function limpiarAtributos(\DOMElement $el, string $etiqueta): void
    {
        $permitidos = ['id'];

        if ($etiqueta === 'a') {
            $permitidos = array_merge($permitidos, ['href', 'title', 'rel', 'target']);
        } elseif ($etiqueta === 'img') {
            $permitidos = array_merge($permitidos, ['src', 'alt', 'title', 'width', 'height', 'loading']);
        }

        $existentes = [];
        foreach ($el->attributes as $attr) {
            $existentes[] = $attr->nodeName;
        }

        foreach ($existentes as $nombre) {
            if (!in_array(strtolower($nombre), $permitidos, true)) {
                $el->removeAttribute($nombre);
            }
        }

        // Identificador de anclajeválido
        if ($el->hasAttribute('id')
            && !preg_match('/^[A-Za-z][\w:.\-]{0,60}$/', (string) $el->getAttribute('id'))) {
            $el->removeAttribute('id');
        }

        if ($el->hasAttribute('width')) {
            $el->setAttribute('width', (string) (int) $el->getAttribute('width'));
        }
        if ($el->hasAttribute('height')) {
            $el->setAttribute('height', (string) (int) $el->getAttribute('height'));
        }

        if ($etiqueta === 'a' && $el->hasAttribute('href')) {
            $href = self::urlSegura($el->getAttribute('href'), ['http', 'https', 'mailto', 'tel']);

            if ($href === null) {
                $el->removeAttribute('href');
            } else {
                $el->setAttribute('href', $href);

                // Los enlaces a otros dominios nunca manipulan la pestaña madre
                if (preg_match('#^https?://#i', $href)
                    && !str_starts_with($href, (string) \App\Core\Env::getString('APP_URL', ''))) {
                    $el->setAttribute('target', '_blank');
                    $el->setAttribute('rel', 'noopener noreferrer nofollow');
                } else {
                    $el->removeAttribute('target');
                    if (strtolower((string) $el->getAttribute('rel')) === 'noopener noreferrer nofollow') {
                        $el->removeAttribute('rel');
                    }
                }
            }
        }

        if ($etiqueta === 'img') {
            $src = self::urlSegura($el->getAttribute('src'), ['http', 'https']);

            // Se admite además la ruta relativa a la biblioteca de imágenes
            $relativo = (string) $el->getAttribute('src');
            $validaRelativa = preg_match('#^(assets/img/|images/|\./)[\w\-./%]+$#', $relativo) === 1;

            if ($src === null && !$validaRelativa) {
                $el->parentNode?->removeChild($el);
                return;
            }

            if ($src !== null) {
                $el->setAttribute('src', $src);
            }

            if (!$el->hasAttribute('alt')) {
                $el->setAttribute('alt', '');
            }
        }
    }

    /**
     * Devuelve la URL si es admisible; `null` en caso contrario.
     *
     * @param array<int,string> $esquemas
     */
    private static function urlSegura(string $url, array $esquemas): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        // Codificación alternativa de «javascript:»
        $plana = strtolower(preg_replace('/[\x00-\x20]+/', '', $url) ?? $url);
        $plana = html_entity_decode($plana, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plana = rawurldecode($plana);

        if (str_contains($plana, 'javascript:') || str_contains($plana, 'vbscript:')
            || str_contains($plana, 'data:text/html')) {
            return null;
        }

        // Enlaces internos relativos
        if (str_starts_with($url, '/') || str_starts_with($url, '#') || str_starts_with($url, '?')) {
            return $url;
        }

        $partes = parse_url($url);

        if ($partes === false || !isset($partes['scheme'])) {
            // Ruta relativa sin esquema (../ no se admite)
            return str_contains($url, '..') ? null : $url;
        }

        return in_array(strtolower((string) $partes['scheme']), $esquemas, true) ? $url : null;
    }
}
