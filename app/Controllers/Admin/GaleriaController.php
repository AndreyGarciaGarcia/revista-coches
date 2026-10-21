<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Exception\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AdminRepository;
use App\Repositories\GaleriaRepository;
use App\Services\Youtube;

/**
 * Galería fotográfica y material en vídeo de una ficha de vehículo.
 *
 * Reglas de la casa aplicadas aquí:
 *   · todo cambio deja rastro en `auditoria`;
 *   · sólo se admiten imágenes JPEG de hasta 6 MB (el servidor no
 *     dispone de GD, así que no se recorta ni se reescala);
 *   · las miniaturas de vídeo se guardan en local: la CSP no permite
 *     servir imágenes de terceros.
 */
final class GaleriaController extends Controller
{
    private const ROTULOS = [
        'exterior' => 'vista exterior',
        'interior' => 'interior',
        'motor'    => 'motor',
        'detalle'  => 'detalle',
    ];

    private const MAX_PESO_KB   = 6144;
    private const MAX_ANCHO_MIN = 400;
    private const MAX_FOTOS     = 20;
    private const MAX_VIDEOS    = 6;
    private const MAX_POR_ENVIO = 10;

    // -----------------------------------------------------------------
    //  Pantalla
    // -----------------------------------------------------------------

    public function index(Request $request): Response
    {
        $coche = $this->vehiculo($request);
        $repo = new GaleriaRepository();

        return $this->view('pages/admin/coches/galeria', [
            'title'      => 'Galería y vídeo — Panel',
            'coche'      => $coche,
            'fotos'      => $repo->fotos((int) $coche['id']),
            'videos'     => $repo->videos((int) $coche['id']),
            'categorias' => self::ROTULOS,
        ], 'layouts/admin');
    }

    // -----------------------------------------------------------------
    //  Fotografías
    // -----------------------------------------------------------------

    public function subirFotos(Request $request): Response
    {
        $id = (int) $request->intParam('id');
        $repo = new GaleriaRepository();
        $coche = $repo->vehiculo($id);

        if ($coche === null) {
            throw HttpException::notFound('El vehículo no existe.');
        }

        $categoria = (string) $request->input('categoria', 'exterior');
        $categoria = array_key_exists($categoria, self::ROTULOS) ? $categoria : 'exterior';
        $altSugerido = trim((string) $request->input('alt', ''));

        $archivos = $this->archivos($request);

        if ($archivos === []) {
            $this->flash('error', 'No se ha elegido ningún archivo.');

            return $this->volver($id);
        }

        $ocupadas = count($repo->fotos($id));

        if ($ocupadas >= self::MAX_FOTOS) {
            $this->flash('error', 'Este vehículo ya tiene ' . self::MAX_FOTOS . ' fotografías: el máximo por ficha.');

            return $this->volver($id);
        }

        $dirRel = 'assets/img/' . $coche['carpeta'];
        $dirAbs = dirname(__DIR__, 3) . '/public/' . $dirRel;

        if (!is_dir($dirAbs) && !@mkdir($dirAbs, 0755, true) && !is_dir($dirAbs)) {
            $this->flash('error', 'No se pudo crear el directorio de imágenes.');

            return $this->volver($id);
        }

        $errores = [];
        $nuevas = 0;
        $enlazadas = 0;
        $yaEstaba = 0;
        $rotulo = self::ROTULOS[$categoria];

        foreach (array_slice($archivos, 0, self::MAX_POR_ENVIO) as $numero => $archivo) {
            $error = $this->validar($archivo);

            if ($error !== null) {
                $errores[] = ($numero + 1) . 'º: ' . $error;
                continue;
            }

            $origen = (string) ($archivo['tmp_name'] ?? '');
            $hash = sha1_file($origen);

            if ($hash === false) {
                $errores[] = ($numero + 1) . 'º: no se pudo leer el archivo.';
                continue;
            }

            $existente = $repo->imagenPorHash($hash);

            if ($existente !== null) {
                // El mismo bytes ya está en la biblioteca: se enlaza con
                // este vehículo en vez de guardar una segunda copia.
                if ($repo->agregarFoto($id, $existente['id'], $categoria)) {
                    $enlazadas++;
                } else {
                    $yaEstaba++;
                }
                continue;
            }

            $nombre = 'subida-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.jpg';
            $destino = $dirAbs . '/' . $nombre;

            if (!$this->mover($archivo, $destino)) {
                $errores[] = ($numero + 1) . 'º: no se pudo guardar en el servidor.';
                continue;
            }

            [$ancho, $alto] = @getimagesize($destino) ?: [0, 0];

            $alt = $altSugerido !== ''
                ? $altSugerido
                : $coche['etiqueta'] . ' — ' . $rotulo;

            $imagenId = $repo->registrarImagen(
                $dirRel . '/' . $nombre,
                $alt,
                $hash,
                $ancho,
                $alto,
                (int) round((int) filesize($destino) / 1024)
            );

            $repo->agregarFoto($id, $imagenId, $categoria);
            $nuevas++;
        }

        if ($nuevas + $enlazadas > 0) {
            (new AdminRepository())->auditar('galeria.foto.subir', 'coches', $id, [
                'nuevas' => $nuevas, 'enlazadas' => $enlazadas, 'categoria' => $categoria,
            ]);
        }

        $partes = [];

        if ($nuevas > 0) {
            $partes[] = $nuevas . ' fotografía' . ($nuevas === 1 ? '' : 's')
                . ' añadida' . ($nuevas === 1 ? '' : 's') . ' a la galería como «' . $rotulo . '».';
        }

        if ($enlazadas > 0) {
            $partes[] = $enlazadas . ' ya estaban en la biblioteca: se han enlazado sin duplicar el fichero.';
        }

        if ($yaEstaba > 0) {
            $partes[] = $yaEstaba . ' ya figuraban en la galería de este vehículo.';
        }

        if ($errores !== []) {
            $partes[] = 'No se subió todo: ' . implode(' · ', array_slice($errores, 0, 3)) . '.';
        }

        if ($partes === []) {
            $partes[] = 'No se ha podido añadir ninguna fotografía.';
        }

        $this->flash(
            $errores === [] && ($nuevas + $enlazadas) > 0 ? 'success' : 'error',
            implode(' ', $partes)
        );

        return $this->volver($id);
    }

    public function categoriaFoto(Request $request): Response
    {
        $id = (int) $request->intParam('id');
        $imagenId = (int) $request->intParam('imagenId');
        $categoria = (string) $request->input('categoria', 'exterior');

        $this->vehiculo($request);

        if (array_key_exists($categoria, self::ROTULOS)
            && (new GaleriaRepository())->categoria($id, $imagenId, $categoria)) {
            (new AdminRepository())->auditar('galeria.foto.categoria', 'coches_imagenes', $imagenId, [
                'coche_id' => $id, 'categoria' => $categoria,
            ]);
            $this->flash('success', 'Categoría actualizada a «' . self::ROTULOS[$categoria] . '».');
        } else {
            $this->flash('error', 'No se pudo cambiar la categoría.');
        }

        return $this->volver($id);
    }

    public function principalFoto(Request $request): Response
    {
        $id = (int) $request->intParam('id');
        $imagenId = (int) $request->intParam('imagenId');
        $this->vehiculo($request);

        $repo = new GaleriaRepository();

        // La imagen tiene que pertenecer a este vehículo: si no, el
        // método enlazaría una fotografía ajena a la ficha.
        $enCarpeta = array_filter($repo->fotos($id), static fn (array $f): bool => (int) $f['id'] === $imagenId);

        if ($enCarpeta === []) {
            $this->flash('error', 'Esa fotografía no pertenece a este vehículo.');

            return $this->volver($id);
        }

        (new AdminRepository())->asignarImagenCoche($id, $imagenId);
        $repo->normalizarOrden($id);
        (new AdminRepository())->auditar('galeria.foto.principal', 'coches', $id, ['imagen_id' => $imagenId]);
        $this->flash('success', 'Fotografía principal cambiada. La anterior pasa al resto de la galería.');

        return $this->volver($id);
    }

    public function ordenFoto(Request $request): Response
    {
        $id = (int) $request->intParam('id');
        $imagenId = (int) $request->intParam('imagenId');
        $this->vehiculo($request);

        $delta = (string) $request->input('delta', 'abajo') === 'arriba' ? -1 : 1;

        if ((new GaleriaRepository())->reordenarFotos($id, $imagenId, $delta)) {
            (new AdminRepository())->auditar('galeria.foto.orden', 'coches_imagenes', $imagenId, [
                'coche_id' => $id, 'delta' => $delta,
            ]);
            $this->flash('success', 'Orden actualizado.');
        } else {
            $this->flash('error', 'Esa fotografía ya está en el extremo de la lista.');
        }

        return $this->volver($id);
    }

    public function borrarFoto(Request $request): Response
    {
        $id = (int) $request->intParam('id');
        $imagenId = (int) $request->intParam('imagenId');
        $this->vehiculo($request);

        $resultado = (new GaleriaRepository())->borrarFoto($id, $imagenId);

        if (!$resultado['ok']) {
            $this->flash('error', (string) ($resultado['error'] ?? 'No se pudo eliminar la fotografía.'));

            return $this->volver($id);
        }

        if ($resultado['ruta'] !== null) {
            $this->borrarFichero((string) $resultado['ruta'], 'assets/img/cars/');
        }

        $repo = new GaleriaRepository();
        $repo->normalizarOrden($id);

        (new AdminRepository())->auditar('galeria.foto.borrar', 'coches', $id, [
            'imagen_id' => $imagenId, 'ruta' => $resultado['ruta'],
        ]);
        $this->flash('success', 'Fotografía eliminada de la galería.');

        return $this->volver($id);
    }

    // -----------------------------------------------------------------
    //  Vídeos
    // -----------------------------------------------------------------

    public function agregarVideo(Request $request): Response
    {
        $id = (int) $request->intParam('id');
        $coche = $this->vehiculo($request);

        $repo = new GaleriaRepository();
        $youtube = new Youtube();
        $entrada = (string) $request->input('video', '');

        $fallo = function (string $mensaje) use ($id): Response {
            $this->flash('error', $mensaje);

            return $this->volver($id);
        };

        if (count($repo->videos($id)) >= self::MAX_VIDEOS) {
            return $fallo('Esta ficha ya tiene el máximo de ' . self::MAX_VIDEOS . ' vídeos.');
        }

        $codigo = $youtube->codigo($entrada);

        if ($codigo === null) {
            return $fallo('Pega un enlace de YouTube válido (youtu.be/…, youtube.com/watch?v=… o el identificador).');
        }

        $meta = $youtube->meta($codigo);

        if ($meta === null) {
            return $fallo('No se pudo validar el vídeo: comprueba que el enlace es correcto y que el vídeo es público.');
        }

        $bytes = $youtube->miniatura($codigo);

        if ($bytes === null) {
            return $fallo('No se pudo descargar la miniatura de YouTube; inténtalo de nuevo.');
        }

        $carpeta = (string) $coche['carpeta'];
        $dirAbs = dirname(__DIR__, 3) . '/public/assets/img/' . $carpeta;

        if (!is_dir($dirAbs) && !@mkdir($dirAbs, 0755, true) && !is_dir($dirAbs)) {
            return $fallo('No se pudo crear el directorio de miniaturas.');
        }

        $miniatura = $this->siguienteMiniatura($dirAbs);

        if (@file_put_contents($dirAbs . '/' . $miniatura, $bytes) === false) {
            return $fallo('No se pudo guardar la miniatura en el servidor.');
        }

        @chmod($dirAbs . '/' . $miniatura, 0644);

        $duracion = $youtube->duracion($codigo);

        $ok = $repo->agregarVideo(
            $id,
            $codigo,
            $meta['titulo'],
            $meta['canal'],
            $duracion,
            'assets/img/' . $carpeta . '/' . $miniatura
        );

        if (!$ok) {
            @unlink($dirAbs . '/' . $miniatura);

            return $fallo('Ese vídeo ya estaba en la ficha de este vehículo.');
        }

        (new AdminRepository())->auditar('galeria.video.alta', 'coches', $id, [
            'codigo' => $codigo, 'titulo' => $meta['titulo'],
        ]);
        $this->flash('success', 'Vídeo añadido: «' . excerpt($meta['titulo'], 70) . '»'
            . ($duracion !== null ? ' (' . $duracion . ')' : '') . '.');

        return $this->volver($id);
    }

    public function ordenVideo(Request $request): Response
    {
        $id = (int) $request->intParam('id');
        $videoId = (int) $request->intParam('videoId');
        $this->vehiculo($request);

        $delta = (string) $request->input('delta', 'abajo') === 'arriba' ? -1 : 1;

        if ((new GaleriaRepository())->reordenarVideos($id, $videoId, $delta)) {
            (new AdminRepository())->auditar('galeria.video.orden', 'coches_videos', $videoId, [
                'coche_id' => $id, 'delta' => $delta,
            ]);
            $this->flash('success', 'Orden de vídeos actualizado.');
        } else {
            $this->flash('error', 'Ese vídeo ya está en el extremo de la lista.');
        }

        return $this->volver($id);
    }

    public function borrarVideo(Request $request): Response
    {
        $id = (int) $request->intParam('id');
        $videoId = (int) $request->intParam('videoId');
        $this->vehiculo($request);

        $resultado = (new GaleriaRepository())->borrarVideo($id, $videoId);

        if (!$resultado['ok']) {
            $this->flash('error', (string) ($resultado['error'] ?? 'No se pudo eliminar el vídeo.'));

            return $this->volver($id);
        }

        if ($resultado['ruta'] !== null) {
            $this->borrarFichero((string) $resultado['ruta'], 'assets/img/cars/');
        }

        (new AdminRepository())->auditar('galeria.video.borrar', 'coches', $id, ['video_id' => $videoId]);
        $this->flash('success', 'Vídeo eliminado de la ficha.');

        return $this->volver($id);
    }

    // -----------------------------------------------------------------
    //  Auxiliares
    // -----------------------------------------------------------------

    /** @return array<string,mixed> */
    private function vehiculo(Request $request): array
    {
        $coche = (new GaleriaRepository())->vehiculo((int) $request->intParam('id'));

        if ($coche === null) {
            throw HttpException::notFound('El vehículo no existe.');
        }

        return $coche;
    }

    private function volver(int $cocheId): Response
    {
        return $this->redirect(url('admin/coches/' . $cocheId . '/galeria'));
    }

    /**
     * Normaliza `$_FILES['fotos']` (un archivo o varios) a una lista.
     *
     * @return array<int,array<string,mixed>>
     */
    private function archivos(Request $request): array
    {
        $fichero = $request->file('fotos');

        if ($fichero === null) {
            return [];
        }

        if (!isset($fichero['name']) || !is_array($fichero['name'])) {
            return [$fichero];
        }

        $out = [];

        foreach ($fichero['name'] as $i => $nombre) {
            $out[] = [
                'name'      => (string) $nombre,
                'type'      => (string) ($fichero['type'][$i] ?? ''),
                'tmp_name'  => (string) ($fichero['tmp_name'][$i] ?? ''),
                'error'     => (int) ($fichero['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                'size'      => (int) ($fichero['size'][$i] ?? 0),
            ];
        }

        return array_values(array_filter(
            $out,
            static fn (array $f): bool => (int) $f['error'] !== UPLOAD_ERR_NO_FILE
        ));
    }

    /** @param array<string,mixed> $archivo @return string|null motivo del rechazo */
    private function validar(array $archivo): ?string
    {
        $error = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            return match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'supera el límite del servidor',
                UPLOAD_ERR_PARTIAL                       => 'se subió incompleto',
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'no se pudo escribir en el servidor',
                UPLOAD_ERR_EXTENSION                      => 'fue bloqueado por una extensión',
                default                                   => 'está vacío o no llegó completo',
            };
        }

        $peso = (int) ($archivo['size'] ?? 0);

        if ($peso <= 0) {
            return 'el archivo está vacío';
        }

        if ($peso > self::MAX_PESO_KB * 1024) {
            return 'supera el límite de 6 MB';
        }

        $ruta = (string) ($archivo['tmp_name'] ?? '');

        if ($ruta === '' || !is_file($ruta)) {
            return 'no llegó un archivo temporal';
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($ruta);

        if ($mime !== 'image/jpeg') {
            return 'sólo se admiten imágenes JPEG (tipo: ' . (string) $mime . ')';
        }

        $info = @getimagesize($ruta);

        if ($info === false || ($info['mime'] ?? '') !== 'image/jpeg') {
            return 'no es una imagen JPEG válida';
        }

        if ((int) $info[0] < self::MAX_ANCHO_MIN) {
            return 'es demasiado pequeña (mínimo ' . self::MAX_ANCHO_MIN . ' px de ancho)';
        }

        return null;
    }

    /** @param array<string,mixed> $archivo */
    private function mover(array $archivo, string $destino): bool
    {
        $origen = (string) ($archivo['tmp_name'] ?? '');

        if ($origen === '' || !is_file($origen)) {
            return false;
        }

        if (is_uploaded_file($origen) && move_uploaded_file($origen, $destino)) {
            @chmod($destino, 0644);

            return true;
        }

        // Red de seguridad si la petición no llega como subida «clásica»
        $guardado = @rename($origen, $destino) || @copy($origen, $destino);

        if ($guardado) {
            @chmod($destino, 0644);
        }

        return $guardado;
    }

    /** Próximo «video-NN.jpg» libre de la carpeta. */
    private function siguienteMiniatura(string $dirAbs): string
    {
        $max = 0;

        foreach (glob($dirAbs . '/video-*.jpg') ?: [] as $fichero) {
            if (preg_match('/^video-(\d+)\.jpg$/', basename($fichero), $m) === 1) {
                $max = max($max, (int) $m[1]);
            }
        }

        return sprintf('video-%02d.jpg', $max + 1);
    }

    /**
     * Borra un fichero de imagen sólo si cae dentro del árbol indicado.
     * `ruta` viene de la base de datos, nunca del usuario, pero se
     * comprueba igualmente contra cualquier salida del directorio.
     */
    private function borrarFichero(string $ruta, string $prefijo): void
    {
        $ruta = str_replace(['..', "\0"], '', $ruta);

        if (!str_starts_with($ruta, $prefijo)) {
            return;
        }

        $absoluto = dirname(__DIR__, 3) . '/public/' . $ruta;

        if (is_file($absoluto)) {
            @unlink($absoluto);
        }
    }
}
