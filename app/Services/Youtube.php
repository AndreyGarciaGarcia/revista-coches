<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;

/**
 * Integración mínima con YouTube para el panel de administración.
 *
 * Sólo tres cosas: validar que un enlace corresponde a un vídeo público,
 * leer sus metadatos (oEmbed) y descargar su miniatura al servidor.
 *
 * La miniatura se guarda en local porque la CSP del sitio sólo admite
 * imágenes propias (`img-src 'self'`): así la ficha pública no pide
 * nada a YouTube hasta que el visitante pulsa reproducir.
 */
final class Youtube
{
    private const AGENTE = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
        . '(KHTML, like Gecko) Chrome/124.0 Safari/537.36';

    /** Tamaño mínimo razonable de una miniatura descargada. */
    private const MIN_BYTES = 5000;

    /**
     * Extrae el identificador de cualquier formato habitual de enlace
     * (youtu.be/…, watch?v=…, /embed/…, /shorts/…, /live/… o el id suelto).
     */
    public function codigo(string $entrada): ?string
    {
        $entrada = trim($entrada);

        if ($entrada === '') {
            return null;
        }

        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $entrada) === 1) {
            return $entrada;
        }

        // El delimitador «~» evita que el «#» de «[^#]*» cierre el patrón
        $patron = '~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:[^#]*&)?v=|embed/|shorts/|live/|v/))([A-Za-z0-9_-]{11})~i';

        if (preg_match($patron, $entrada, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /**
     * Metadatos públicos del vídeo vía oEmbed.
     *
     * @return array{titulo:string,canal:string}|null null si no existe,
     *                                                está oculto o YouTube no responde
     */
    public function meta(string $codigo): ?array
    {
        $json = $this->http(
            'https://www.youtube.com/oembed?' . http_build_query([
                'url'    => 'https://www.youtube.com/watch?v=' . $codigo,
                'format' => 'json',
            ]),
            12
        );

        if ($json === false) {
            return null;
        }

        $datos = json_decode($json, true);

        if (!is_array($datos) || empty($datos['title'])) {
            return null;
        }

        return [
            'titulo' => mb_substr((string) $datos['title'], 0, 200),
            'canal'  => mb_substr((string) ($datos['author_name'] ?? 'YouTube'), 0, 120),
        ];
    }

    /**
     * Duración del vídeo («12:07») leída de su página pública.
     *
     * Es una lectura en modo testimonial: si YouTube cambia el marcado
     * la ficha queda sin duración, nunca con un valor falso.
     */
    public function duracion(string $codigo): ?string
    {
        $html = $this->http('https://www.youtube.com/watch?v=' . $codigo, 12);

        if ($html === false) {
            return null;
        }

        $inicio = strpos($html, '"lengthText":');

        if ($inicio === false) {
            return null;
        }

        // Dentro de lengthText, «simpleText» es la duración («4:32»);
        // el nodo de accesibilidad que lo precede usa «label».
        $zona = substr($html, $inicio, 320);

        if (preg_match('/"simpleText":"([^"]{1,9})"/', $zona, $m) !== 1) {
            return null;
        }

        $valor = $m[1];

        return preg_match('/^\d{1,3}(?::\d{2}){1,2}$/', $valor) === 1 ? $valor : null;
    }

    /** Bytes JPEG de la miniatura (maxresdefault y, en su defecto, hqdefault). */
    public function miniatura(string $codigo): ?string
    {
        foreach (['maxresdefault.jpg', 'hqdefault.jpg'] as $archivo) {
            $bytes = $this->http('https://i.ytimg.com/vi/' . $codigo . '/' . $archivo, 15);

            // Un 404 de ytimg devuelve la imagen de «no disponible»;
            // se descarta por tamaño y por no ser JPEG.
            if ($bytes !== false && strlen($bytes) >= self::MIN_BYTES && str_starts_with($bytes, "\xFF\xD8")) {
                return $bytes;
            }
        }

        return null;
    }

    /** @return string|false cuerpo de la respuesta, o false si falla */
    private function http(string $url, int $timeout = 12): string|false
    {
        if (!function_exists('curl_init')) {
            return false;
        }

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 4,
            CURLOPT_ENCODING       => '',
            CURLOPT_USERAGENT      => self::AGENTE,
            CURLOPT_HTTPHEADER     => ['Accept: text/html,application/json;q=0.9,*/*;q=0.8'],
        ]);

        $cuerpo = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($cuerpo === false || $codigo >= 400) {
            Logger::info('YouTube ' . $codigo . ' en ' . substr($url, 0, 90) . ($error !== '' ? ' — ' . $error : ''));

            return false;
        }

        return (string) $cuerpo;
    }
}
