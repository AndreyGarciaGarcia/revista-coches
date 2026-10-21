<?php

declare(strict_types=1);

namespace App\Console;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Str;

/**
 * Carga de datos iniciales.
 *
 * Es IDEMPOTENTE: se puede ejecutar cuantas veces haga falta sin
 * duplicar registros (usa INSERT IGNORE / comprobaciones previas).
 *
 *   php scripts/seed.php
 */
final class Seeder
{
    private Database $db;

    /** @var callable(string):void|null */
    private $out;

    private int $creados = 0;

    /**
     * @param callable(string):void|null $out
     */
    public function __construct(?callable $out = null)
    {
        $this->db = Database::get();
        $this->out = $out;
    }

    public function run(): array
    {
        $this->log('— Roles y permisos');
        $this->rolesYPermisos();

        $this->log('— Usuarios');
        $this->usuarios();

        $this->log('— Categorías y etiquetas');
        $this->categorias();

        $this->log('— Marcas');
        $marcas = $this->marcas();

        // La biblioteca de imágenes se carga ANTES del contenido para que
        // SeedCoches y SeedNoticias puedan enlazar cada fotografía con su
        // ficha al crearla.
        $this->log('— Imágenes de la biblioteca');
        $imagenes = $this->imagenes();

        $this->log('— Vehículos');
        $cochesCreados = (new SeedCoches($this->out))->run($marcas);

        $this->log('— Noticias');
        $noticiasCreadas = (new SeedNoticias($this->out))->run();

        // Si el contenido ya existía de una ejecución anterior (sembrador
        // idempotente), las fotos llegan "sueltas": se vinculan ahora.
        $this->log('— Vinculación de fotografías');
        $cochesImagen = (new SeedCoches())->vincularImagenes();
        $noticiasImagen = (new SeedNoticias())->vincularImagenes();
        if ($this->out) {
            ($this->out)("   {$cochesImagen} vehículos · {$noticiasImagen} artículos");
        }

        // Galería ampliada (exterior, interior, motor y detalle) y vídeo:
        // material descargado por scripts/descargar-galeria.php y
        // scripts/descargar-videos.php, ya presente en disco.
        $this->log('— Galerías y vídeos de vehículos');
        $galeria = new SeedGaleria($this->out);
        $fotosGaleria = $galeria->registrar();
        $vinculosGaleria = $galeria->vincular();
        $videosCoches = $galeria->videos();
        if ($this->out) {
            ($this->out)(
                "   {$fotosGaleria} fotografías · {$vinculosGaleria} vínculos · {$videosCoches} vídeos"
            );
        }

        $this->log('— Comentarios y suscriptores');
        $this->comentarios();
        $this->suscriptores();

        return [
            'roles'       => 5,
            'usuarios'    => 3,
            'categorias'  => 10,
            'marcas'      => count($marcas),
            'coches'      => $cochesCreados,
            'noticias'    => $noticiasCreadas,
            'imagenes'    => $imagenes,
            'fotos'       => $fotosGaleria,
            'videos'      => $videosCoches,
            'comentarios' => $this->creados,
        ];
    }

    private function log(string $message): void
    {
        if ($this->out) {
            ($this->out)($message);
        }
    }

    // =================================================================
    //  SEGURIDAD
    // =================================================================

    private function rolesYPermisos(): void
    {
        $roles = [
            ['lector',      'Lector',        'Cuenta básica de lector sin acceso de escritura', 0,  1],
            ['autor',       'Autor',         'Escribe y edita sus propias noticias',           30, 1],
            ['editor',      'Editor',        'Edita y publica noticias y fichas de vehículos', 50, 1],
            ['admin',       'Administrador', 'Gestiona contenidos, usuarios y ajustes',        90, 1],
            ['root',        'Superusuario',  'Control total, incluida la configuración crítica', 100, 1],
        ];

        foreach ($roles as [$clave, $nombre, $descripcion, $nivel, $sistema]) {
            $this->db->execute(
                'INSERT INTO roles (clave, nombre, descripcion, nivel, es_sistema)
                 VALUES (:clave, :nombre, :desc, :nivel, :sistema)
                 ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), descripcion = VALUES(descripcion),
                                         nivel = VALUES(nivel)',
                [
                    'clave' => $clave, 'nombre' => $nombre, 'desc' => $descripcion,
                    'nivel' => $nivel, 'sistema' => $sistema,
                ]
            );
        }

        $permisos = [
            ['noticias.ver',       'Ver noticias',            'noticias'],
            ['noticias.crear',     'Crear noticias',          'noticias'],
            ['noticias.editar',    'Editar cualquier noticia','noticias'],
            ['noticias.publicar',  'Publicar noticias',       'noticias'],
            ['noticias.borrar',    'Borrar noticias',         'noticias'],
            ['coches.ver',         'Ver el catálogo',         'catalogo'],
            ['coches.crear',       'Añadir vehículos',        'catalogo'],
            ['coches.editar',      'Editar vehículos',        'catalogo'],
            ['coches.borrar',      'Borrar vehículos',        'catalogo'],
            ['comentarios.moderar','Moderar comentarios',     'comunidad'],
            ['usuarios.gestionar', 'Gestionar usuarios',      'usuarios'],
            ['config.gestionar',   'Cambiar la configuración','sistema'],
            ['medios.gestionar',   'Gestionar imágenes',      'sistema'],
            ['auditoria.ver',      'Consultar la auditoría',  'sistema'],
        ];

        foreach ($permisos as [$clave, $nombre, $grupo]) {
            $this->db->execute(
                'INSERT IGNORE INTO permisos (clave, nombre, grupo) VALUES (:clave, :nombre, :grupo)',
                ['clave' => $clave, 'nombre' => $nombre, 'grupo' => $grupo]
            );
        }

        $porRol = [
            'lector' => ['noticias.ver', 'coches.ver'],
            'autor'  => ['noticias.ver', 'coches.ver', 'noticias.crear', 'noticias.editar', 'medios.gestionar'],
            'editor' => [
                'noticias.ver', 'coches.ver', 'noticias.crear', 'noticias.editar',
                'noticias.publicar', 'coches.crear', 'coches.editar',
                'comentarios.moderar', 'medios.gestionar',
            ],
            'admin'  => [
                'noticias.ver', 'noticias.crear', 'noticias.editar', 'noticias.publicar', 'noticias.borrar',
                'coches.ver', 'coches.crear', 'coches.editar', 'coches.borrar',
                'comentarios.moderar', 'usuarios.gestionar', 'config.gestionar',
                'medios.gestionar', 'auditoria.ver',
            ],
        ];

        foreach ($porRol as $rolClave => $permisosClave) {
            $rol = $this->db->first('SELECT id FROM roles WHERE clave = :c', ['c' => $rolClave]);
            if ($rol === null) {
                continue;
            }

            foreach ($permisosClave as $permisoClave) {
                $permiso = $this->db->first('SELECT id FROM permisos WHERE clave = :c', ['c' => $permisoClave]);
                if ($permiso === null) {
                    continue;
                }

                $this->db->execute(
                    'INSERT IGNORE INTO rol_permiso (rol_id, permiso_id) VALUES (:r, :p)',
                    ['r' => (int) $rol['id'], 'p' => (int) $permiso['id']]
                );
            }
        }

        // El rol root lo tiene literalmente todo (se resuelve por nivel)
        $root = $this->db->first('SELECT id FROM roles WHERE clave = :c', ['c' => 'root']);
        if ($root !== null) {
            foreach ($permisos as [$clave]) {
                $permiso = $this->db->first('SELECT id FROM permisos WHERE clave = :c', ['c' => $clave]);
                if ($permiso !== null) {
                    $this->db->execute(
                        'INSERT IGNORE INTO rol_permiso (rol_id, permiso_id) VALUES (:r, :p)',
                        ['r' => (int) $root['id'], 'p' => (int) $permiso['id']]
                    );
                }
            }
        }
    }

    private function usuarios(): void
    {
        $usuarios = [
            [
                'email'     => Env::getString('ADMIN_EMAIL', 'admin@motorpress.dev'),
                'password'  => Env::getString('ADMIN_PASSWORD', 'Admin2026!'),
                'nombre'    => 'Elena',
                'apellidos' => 'Vargas Ruiz',
                'rol'       => 'admin',
                'bio'       => 'Directora de MotorPress. Veinte años cubriendo salones y pruebas de conducción.',
            ],
            [
                'email'     => 'redaccion@motorpress.dev',
                'password'  => 'Redaccion2026!',
                'nombre'    => 'Marcos',
                'apellidos' => 'Ibáñez Soto',
                'rol'       => 'editor',
                'bio'       => 'Editor jefe. Especialista en tecnología y electrificación.',
            ],
            [
                'email'     => 'autor@motorpress.dev',
                'password'  => 'Autor2026!',
                'nombre'    => 'Lucía',
                'apellidos' => 'Ferrán Ortiz',
                'rol'       => 'autor',
                'bio'       => 'Pruebas de conducción, clásicos y mercado de ocasión.',
            ],
        ];

        foreach ($usuarios as $u) {
            $rol = $this->db->first('SELECT id FROM roles WHERE clave = :c', ['c' => $u['rol']]);
            if ($rol === null) {
                continue;
            }

            // El disparador tr_noticias_bi/c... genera el uuid cuando llega vacío
            $this->db->execute(
                'INSERT INTO usuarios (uuid, email, password_hash, nombre, apellidos, bio, rol_id,
                                       estado, email_verificado_at)
                 VALUES (:uuid, :email, :hash, :nombre, :apellidos, :bio, :rol, "activo", NOW())
                 ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), apellidos = VALUES(apellidos),
                                         bio = VALUES(bio), rol_id = VALUES(rol_id)',
                [
                    'uuid'      => '',
                    'email'     => $u['email'],
                    'hash'      => Auth::hash($u['password']),
                    'nombre'    => $u['nombre'],
                    'apellidos' => $u['apellidos'],
                    'bio'       => $u['bio'],
                    'rol'       => (int) $rol['id'],
                ]
            );
        }
    }

    // =================================================================
    //  CONTENIDO EDITORIAL
    // =================================================================

    private function categorias(): void
    {
        $categorias = [
            ['Actualidad',    'actualidad',   'Lo que ocurre hoy en el motor y la industria del automóvil.', '#0a84ff', 'newspaper', null],
            ['Pruebas',       'pruebas',      'Probamos a fondo los modelos más relevantes del mercado.',     '#ff9f0a', 'steering',  null],
            ['Eléctricos',    'electricos',   'Movilidad eléctrica, autonomía, recarga y vehículos a batería.', '#30d158', 'bolt',    null],
            ['Deportes',      'deportes',     'Competiciones, circuitos y tecnología de competición.',        '#ff375f', 'flag',      null],
            ['Clásicos',      'clasicos',     'Vehículos de colección, subastas y patrimonio sobre ruedas.',  '#ffd60a', 'clock',     null],
            ['Guía de compra','guia-compra',  'Cómo elegir, financiar y mantener el coche adecuado.',         '#bf5af2', 'guide',     null],
            ['Tecnología',    'tecnologia',   'Asistencias, conectividad, infotainment y conducción autónoma.', '#64d2ff','cpu',      null],
            ['Mercado',       'mercado',      'Precios de mercado, ocasion y análisis del sector.',           '#ff9f0a', 'chart',     null],
            ['Sostenibilidad','sostenibilidad','Emisiones, eficiencia y alternativas al combustible fósil.',   '#30d158', 'leaf',      null],
            ['Motor',         'motor',        'Motores, mecánica, talleres y mantenimiento.',                 '#8e8e93', 'gear',      null],
        ];

        foreach ($categorias as $i => [$nombre, $slug, $desc, $color, $icono, $padre]) {
            $this->db->execute(
                'INSERT IGNORE INTO categorias (nombre, slug, descripcion, color, icono, orden)
                 VALUES (:nombre, :slug, :desc, :color, :icono, :orden)',
                [
                    'nombre' => $nombre, 'slug' => $slug, 'desc' => $desc,
                    'color' => $color, 'icono' => $icono, 'orden' => $i,
                ]
            );
        }

        $etiquetas = [
            'eléctrico', 'SUV', 'berlina', 'prueba', 'análisis', 'consejos', 'IA',
            'autonomía', 'ocasión', 'Fórmula 1', 'GT', 'hidrógeno', 'híbrido',
            'seguridad', 'usado', 'financiación', 'comparativa', 'deportivo',
            'familiar', 'ciudad', 'carga rápida', 'software', 'clásico', 'subasta',
        ];

        foreach ($etiquetas as $nombre) {
            $this->db->execute(
                'INSERT IGNORE INTO etiquetas (nombre, slug) VALUES (:n, :s)',
                ['n' => $nombre, 's' => Str::slug($nombre)]
            );
        }
    }

    /** @return array<string,int> slug => id */
    private function marcas(): array
    {
        $marcas = [
            ['BMW',            'Alemania', 1916, 'https://www.bmw.es'],
            ['Mercedes-Benz',  'Alemania', 1926, 'https://www.mercedes-benz.es'],
            ['Audi',           'Alemania', 1909, 'https://www.audi.es'],
            ['Volkswagen',     'Alemania', 1937, 'https://www.volkswagen.es'],
            ['Toyota',         'Japón',    1937, 'https://www.toyota.es'],
            ['Tesla',          'EE. UU.',  2003, 'https://www.tesla.com'],
            ['SEAT',           'España',   1950, 'https://www.seat.es'],
            ['Cupra',          'España',   2018, 'https://www.cupraofficial.es'],
            ['Renault',        'Francia',  1899, 'https://www.renault.es'],
            ['Peugeot',        'Francia',  1810, 'https://www.peugeot.es'],
            ['Ford',           'EE. UU.',  1903, 'https://www.ford.es'],
            ['Hyundai',        'Corea',    1967, 'https://www.hyundai.com'],
            ['Kia',            'Corea',    1944, 'https://www.kia.com'],
            ['Volvo',          'Suecia',   1927, 'https://www.volvocars.com'],
            ['Porsche',        'Alemania', 1931, 'https://www.porsche.com'],
            ['Skoda',          'Rep. Checa',1895, 'https://www.skoda-auto.es'],
            ['Mazda',          'Japón',    1920, 'https://www.mazda.es'],
            ['Nissan',         'Japón',    1933, 'https://www.nissan.es'],
            ['Dacia',          'Rumanía',  1966, 'https://www.dacia.es'],
        ];

        $slugs = [];

        foreach ($marcas as $i => [$nombre, $pais, $anio, $web]) {
            $slug = Str::slug($nombre);
            $slugs[$slug] = 0;

            $this->db->execute(
                'INSERT IGNORE INTO marcas (nombre, slug, pais, fundacion_anio, web, orden)
                 VALUES (:nombre, :slug, :pais, :anio, :web, :orden)',
                ['nombre' => $nombre, 'slug' => $slug, 'pais' => $pais, 'anio' => $anio, 'web' => $web, 'orden' => $i]
            );

            $row = $this->db->first('SELECT id FROM marcas WHERE slug = :s', ['s' => $slug]);
            $slugs[$slug] = (int) ($row['id'] ?? 0);
        }

        return $slugs;
    }

    // =================================================================
    //  COMUNIDAD
    // =================================================================

    private function comentarios(): void
    {
        $noticias = $this->db->select(
            'SELECT id FROM noticias WHERE estado = "publicado" ORDER BY id LIMIT 8'
        );

        $autores = [
            ['Javier',   'javier.m@example.com',   'Muy buen artículo. Me he pedido el test del Cupra y la autonomía real coincide con lo que contáis.'],
            ['Nuria',    'nuria.p@example.com',    'Llevo tres meses con uno y la app de recarga sigue siendo el punto débil. Espero que lo solucionen con la actualización.'],
            ['Álvaro',   'alvaro.t@example.com',   'Comparado con el alemán, el acabado es otro nivel de precio. Pero en aislamiento acústico ganan los de siempre.'],
            ['Marta',    'marta.g@example.com',    '¿Alguien sabe si esto llega con batería de 58 kWh o 77 kWh? En la ficha técnica no lo veo.'],
            ['Sergio',   'sergio.r@example.com',   'Buena prueba. Me habría gustado ver también el consumo en autovía a 140, que es donde de verdad se ven las diferencias.'],
            ['Elena',    'elena.b@example.com',    'Justo lo que necesitaba para decidir entre el híbrido enchufable y el diésel. Gracias por el desglose de gastos.'],
            ['Rubén',    'ruben.c@example.com',    'El diseño interior me parece un salto enorme respecto a la generación anterior.'],
            ['Paula',    'paula.s@example.com',    'Compré mi unidad de ocasión hace un año y el precio de reventa se ha mantenido muy bien.'],
        ];

        $i = 0;
        foreach ($noticias as $noticia) {
            $cantidad = 1 + ($i % 3);
            for ($k = 0; $k < $cantidad; $k++) {
                [$nombre, $email, $texto] = $autores[$i % count($autores)];

                // Idempotencia: el mismo comentario no se inserta dos veces
                $existe = $this->db->first(
                    'SELECT id FROM comentarios
                      WHERE noticia_id = :n AND autor = :a AND contenido = :c
                      LIMIT 1',
                    ['n' => (int) $noticia['id'], 'a' => $nombre, 'c' => $texto]
                );

                if ($existe === null) {
                    $this->db->execute(
                        'INSERT INTO comentarios (noticia_id, autor, email, contenido, estado, moderado_en, ip)
                         VALUES (:n, :a, :e, :c, "aprobado", NOW(), :ip)',
                        [
                            'n' => (int) $noticia['id'],
                            'a' => $nombre,
                            'e' => $email,
                            'c' => $texto,
                            'ip' => '127.0.0.' . (10 + $i),
                        ]
                    );
                    $this->creados++;
                }

                // El índice avanza siempre: la secuencia es estable
                $i++;
            }
        }

        // Deja un par de comentarios pendientes de moderación
        $pendientes = [
            ['Teresa', 'teresa.l@example.com', 'Excelente información, la comparto con mi club de clásicos.'],
            ['Iván',   'ivan.d@example.com',   '¿Cuándo probáis el modelo con el motor nuevo? Tengo muchas ganas de verlo.'],
        ];
        foreach ($pendientes as [$nombre, $email, $texto]) {
            $noticiaId = (int) ($noticias[0]['id'] ?? 0);
            if ($noticiaId === 0) {
                break;
            }

            $existe = $this->db->first(
                'SELECT id FROM comentarios WHERE noticia_id = :n AND autor = :a AND contenido = :c LIMIT 1',
                ['n' => $noticiaId, 'a' => $nombre, 'c' => $texto]
            );

            if ($existe !== null) {
                continue;
            }

            $this->db->execute(
                'INSERT INTO comentarios (noticia_id, autor, email, contenido, estado, ip)
                 VALUES (:n, :a, :e, :c, "pendiente", "127.0.0.1")',
                ['n' => $noticiaId, 'a' => $nombre, 'e' => $email, 'c' => $texto]
            );
            $this->creados++;
        }
    }

    private function suscriptores(): void
    {
        $suscriptores = [
            ['carlos.mente@example.com',  'Carlos',  'activo'],
            ['ana.vega@example.com',      'Ana',     'activo'],
            ['diego.rivas@example.com',   'Diego',   'activo'],
            ['sofia.nieto@example.com',   'Sofía',   'activo'],
            ['pablo.ruiz@example.com',    'Pablo',   'pendiente'],
            ['irene.sanz@example.com',    'Irene',   'activo'],
            ['hector.leon@example.com',   'Héctor',  'baja'],
        ];

        foreach ($suscriptores as [$email, $nombre, $estado]) {
            // Con consulta preparada nativa cada marcador debe ser único:
            // el estado se resuelve en PHP antes de enviar la sentencia.
            $this->db->execute(
                'INSERT IGNORE INTO suscriptores (email, nombre, token_baja, consentimiento_at,
                                                  consentimiento_ip, estado, confirmado_at)
                 VALUES (:email, :nombre, :token, NOW(), :ip, :estado, :confirmado)',
                [
                    'email'      => $email,
                    'nombre'     => $nombre,
                    'token'      => sha1(Str::token(32)),
                    'ip'         => '127.0.0.1',
                    'estado'     => $estado,
                    'confirmado' => $estado === 'activo' ? date('Y-m-d H:i:s') : null,
                ]
            );
        }
    }

    /**
     * Registra en la biblioteca las imágenes ya descargadas.
     * (Si aún no existen, se quedan en 0 y se repite después de la
     * descarga.)
     */
    private function imagenes(): int
    {
        $base = dirname(__DIR__, 2) . '/public/assets/img';
        $total = 0;

        foreach (['cars', 'news', 'brands'] as $carpeta) {
            $dir = $base . '/' . $carpeta;
            if (!is_dir($dir)) {
                continue;
            }

            foreach (glob($dir . '/*.{jpg,jpeg,png,webp,avif,svg}', GLOB_BRACE) ?: [] as $file) {
                $rel = 'assets/img/' . $carpeta . '/' . basename($file);
                $hash = sha1_file($file);
                if ($hash === false) {
                    continue;
                }

                [$w, $h] = @getimagesize($file) ?: [0, 0];

                $this->db->execute(
                    'INSERT IGNORE INTO imagenes (hash, ruta, titulo, alt, autor, licencia, fuente_url,
                                                  ancho, alto, peso_kb, tipo)
                     VALUES (:hash, :ruta, :titulo, :alt, :autor, :lic, :fuente, :ancho, :alto, :peso, :tipo)',
                    [
                        'hash'   => $hash,
                        'ruta'   => $rel,
                        'titulo' => str_replace(['-', '_'], ' ', pathinfo($file, PATHINFO_FILENAME)),
                        'alt'    => str_replace(['-', '_'], ' ', pathinfo($file, PATHINFO_FILENAME)),
                        'autor'  => 'Wikimedia Commons',
                        'lic'    => 'CC BY-SA / CC0',
                        'fuente' => 'https://commons.wikimedia.org/',
                        'ancho'  => $w,
                        'alto'   => $h,
                        'peso'   => (int) round((int) filesize($file) / 1024),
                        'tipo'   => strtolower(pathinfo($file, PATHINFO_EXTENSION)),
                    ]
                );
                $total++;
            }
        }

        return $total;
    }
}
