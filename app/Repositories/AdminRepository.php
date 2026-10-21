<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;

/**
 * Operaciones del panel de administración: métricas, usuarios,
 * moderación de comentarios, bandeja de mensajes y auditoría.
 *
 * Toda escritura que afecta a identidades o a la seguridad deja rastro
 * en la tabla `auditoria` (quién, qué, cuándo y desde qué IP).
 */
final class AdminRepository
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::get();
    }

    // -----------------------------------------------------------------
    //  Biblioteca de imágenes
    // -----------------------------------------------------------------

    /**
     * Imágenes disponibles para ilustrar un contenido, agrupadas por
     * sección (assets/img/news/, assets/img/cars/, …).
     *
     * @return array<int,array<string,mixed>>
     */
    public function imagenes(string $seccion = 'news', int $limite = 400): array
    {
        return $this->db->select(
            'SELECT id, ruta, alt, titulo, autor, licencia
               FROM imagenes
              WHERE ruta LIKE :p
              ORDER BY ruta
              LIMIT ' . (int) $limite,
            ['p' => 'assets/img/' . $seccion . '/%']
        );
    }

    /** Cambia la fotografía principal de un vehículo (idempotente). */
    /**
     * Fija la fotografía principal de un vehículo.
     *
     * La anterior deja de ser principal pero sigue en la galería: con
     * varias tomas por ficha, promover a una foto no debe borrar a las
     * demás (antes cada coche sólo tenía una y se eliminaba su fila).
     */
    public function asignarImagenCoche(int $cocheId, int $imagenId): bool
    {
        $this->db->execute(
            'UPDATE coches_imagenes SET es_principal = 0
              WHERE coche_id = :c AND es_principal = 1 AND imagen_id <> :i',
            ['c' => $cocheId, 'i' => $imagenId]
        );

        $existe = $this->db->first(
            'SELECT coche_id FROM coches_imagenes WHERE coche_id = :c AND imagen_id = :i',
            ['c' => $cocheId, 'i' => $imagenId]
        );

        if ($existe !== null) {
            $this->db->execute(
                'UPDATE coches_imagenes SET es_principal = 1, orden = 0 WHERE coche_id = :c AND imagen_id = :i',
                ['c' => $cocheId, 'i' => $imagenId]
            );

            return true;
        }

        $this->db->execute(
            'INSERT INTO coches_imagenes (coche_id, imagen_id, orden, es_principal) VALUES (:c, :i, 0, 1)',
            ['c' => $cocheId, 'i' => $imagenId]
        );

        return true;
    }

    // -----------------------------------------------------------------
    //  Métricas del panel
    // -----------------------------------------------------------------

    /** @return array<string,int> */
    public function metricas(): array
    {
        $c = static function (string $sql): int {
            try {
                return (int) Database::get()->scalar($sql);
            } catch (\Throwable) {
                return 0;
            }
        };

        return [
            'articulos'      => $c('SELECT COUNT(*) FROM noticias WHERE borrado_en IS NULL'),
            'borradores'     => $c('SELECT COUNT(*) FROM noticias WHERE estado = "borrador" AND borrado_en IS NULL'),
            'coches'         => $c('SELECT COUNT(*) FROM coches WHERE borrado_en IS NULL'),
            'comentarios'    => $c('SELECT COUNT(*) FROM comentarios'),
            'pendientes'     => $c('SELECT COUNT(*) FROM comentarios WHERE estado = "pendiente"'),
            'usuarios'       => $c('SELECT COUNT(*) FROM usuarios WHERE borrado_en IS NULL'),
            'suscriptores'   => $c('SELECT COUNT(*) FROM suscriptores WHERE estado = "activo"'),
            'mensajes'       => $c('SELECT COUNT(*) FROM mensajes_contacto WHERE estado = "nuevo"'),
            'vistas_hoy'     => $c('SELECT COALESCE(SUM(vistas), 0) FROM noticias WHERE publicado_at >= CURDATE()'),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function actividadReciente(int $limite = 8): array
    {
        try {
            return $this->db->select(
                'SELECT accion, entidad, actor_email, created_at
                   FROM auditoria
                  ORDER BY created_at DESC
                  LIMIT ' . (int) $limite
            );
        } catch (\Throwable) {
            return [];
        }
    }

    // -----------------------------------------------------------------
    //  Usuarios
    // -----------------------------------------------------------------

    /**
     * @return array{items:array<int,array<string,mixed>>,total:int,paginas:int,pagina:int}
     */
    public function usuarios(int $pagina = 1, int $porPagina = 20, string $q = ''): array
    {
        $donde = 'u.borrado_en IS NULL';
        $parametros = [];

        if (trim($q) !== '') {
            $donde .= ' AND (u.email LIKE :q1 OR u.nombre LIKE :q2 OR u.apellidos LIKE :q3)';
            $like = '%' . trim($q) . '%';
            $parametros = ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }

        $total = (int) $this->db->scalar("SELECT COUNT(*) FROM usuarios u WHERE {$donde}", $parametros);
        $pagina = max(1, $pagina);

        $items = $this->db->select(
            "SELECT u.id, u.uuid, u.email, u.nombre, u.apellidos, u.estado, u.ultimo_acceso,
                    u.creado_en, u.ultima_ip, u.rol_id, r.nombre AS rol, r.nivel, r.clave AS rol_clave
               FROM usuarios u
               JOIN roles r ON r.id = u.rol_id
              WHERE {$donde}
              ORDER BY u.creado_en DESC
              LIMIT " . (int) $porPagina . ' OFFSET ' . (int) (($pagina - 1) * $porPagina),
            $parametros
        );

        return [
            'items'   => $items,
            'total'   => $total,
            'paginas' => max(1, (int) ceil($total / $porPagina)),
            'pagina'  => $pagina,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function roles(): array
    {
        return $this->db->select('SELECT * FROM roles ORDER BY nivel DESC');
    }

    public function usuario(int $id): ?array
    {
        return $this->db->first(
            'SELECT u.*, r.nombre AS rol, r.nivel
               FROM usuarios u JOIN roles r ON r.id = u.rol_id
              WHERE u.id = :id AND u.borrado_en IS NULL',
            ['id' => $id]
        );
    }

    /** Cambia el rol o el estado de un usuario y lo audita. */
    public function actualizarUsuario(int $id, array $cambios, string $motivo = ''): bool
    {
        $antes = $this->usuario($id);
        if ($antes === null) {
            return false;
        }

        $permitido = array_intersect_key($cambios, array_flip(['rol_id', 'estado', 'nombre', 'apellidos', 'bio']));
        if ($permitido === []) {
            return false;
        }

        $this->db->update('usuarios', $permitido + ['actualizado_en' => date('Y-m-d H:i:s')], ['id' => $id]);

        $this->auditar('usuario.actualizar', 'usuarios', $id, [
            'antes'  => array_intersect_key($antes, $permitido),
            'despues' => $permitido,
            'motivo' => $motivo,
        ]);

        return true;
    }

    /** Baja lógica: el registro se conserva para auditoría. */
    public function borrarUsuario(int $id): bool
    {
        $usuario = $this->usuario($id);
        if ($usuario === null) {
            return false;
        }

        if ($id === Auth::id()) {
            return false;
        }

        $this->db->update('usuarios', [
            'borrado_en'   => date('Y-m-d H:i:s'),
            'estado'       => 'inactivo',
            'actualizado_en' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);

        $this->auditar('usuario.borrar', 'usuarios', $id, ['email' => $usuario['email']]);

        return true;
    }

    // -----------------------------------------------------------------
    //  Comentarios
    // -----------------------------------------------------------------

    /**
     * @return array{items:array<int,array<string,mixed>>,total:int,paginas:int,pagina:int}
     */
    public function comentarios(int $pagina = 1, int $porPagina = 20, string $estado = ''): array
    {
        $donde = '1 = 1';
        $parametros = [];

        if ($estado !== '' && in_array($estado, ['pendiente', 'aprobado', 'spam', 'rechazado'], true)) {
            $donde .= ' AND c.estado = :e';
            $parametros['e'] = $estado;
        }

        $total = (int) $this->db->scalar("SELECT COUNT(*) FROM comentarios c WHERE {$donde}", $parametros);
        $pagina = max(1, $pagina);

        $items = $this->db->select(
            "SELECT c.*, n.titulo AS noticia, n.slug AS noticia_slug, u.nombre AS usuario
               FROM comentarios c
               JOIN noticias n ON n.id = c.noticia_id
               LEFT JOIN usuarios u ON u.id = c.usuario_id
              WHERE {$donde}
              ORDER BY FIELD(c.estado, 'pendiente', 'aprobado', 'rechazado', 'spam'), c.creado_en DESC
              LIMIT " . (int) $porPagina . ' OFFSET ' . (int) (($pagina - 1) * $porPagina),
            $parametros
        );

        return [
            'items'   => $items,
            'total'   => $total,
            'paginas' => max(1, (int) ceil($total / $porPagina)),
            'pagina'  => $pagina,
        ];
    }

    public function moderarComentario(int $id, string $estado, ?string $motivo = null): bool
    {
        if (!in_array($estado, ['aprobado', 'pendiente', 'spam', 'rechazado'], true)) {
            return false;
        }

        $filas = $this->db->execute(
            'UPDATE comentarios SET estado = :e, motivo = :m, moderado_en = NOW() WHERE id = :id',
            ['e' => $estado, 'm' => $motivo, 'id' => $id]
        );

        if ($filas > 0) {
            $this->auditar('comentario.moderar', 'comentarios', $id, ['estado' => $estado]);
        }

        return $filas > 0;
    }

    // -----------------------------------------------------------------
    //  Mensajes de contacto
    // -----------------------------------------------------------------

    /**
     * @return array{items:array<int,array<string,mixed>>,total:int,paginas:int,pagina:int}
     */
    public function mensajes(int $pagina = 1, int $porPagina = 20, string $estado = ''): array
    {
        $donde = '1 = 1';
        $parametros = [];

        if ($estado !== '' && in_array($estado, ['nuevo', 'en_curso', 'resuelto', 'spam'], true)) {
            $donde .= ' AND m.estado = :e';
            $parametros['e'] = $estado;
        }

        $total = (int) $this->db->scalar("SELECT COUNT(*) FROM mensajes_contacto m WHERE {$donde}", $parametros);
        $pagina = max(1, $pagina);

        $items = $this->db->select(
            "SELECT m.*, u.nombre AS usuario
               FROM mensajes_contacto m
               LEFT JOIN usuarios u ON u.id = m.usuario_id
              WHERE {$donde}
              ORDER BY FIELD(m.estado, 'nuevo', 'en_curso', 'resuelto', 'spam'), m.creado_en DESC
              LIMIT " . (int) $porPagina . ' OFFSET ' . (int) (($pagina - 1) * $porPagina),
            $parametros
        );

        return [
            'items'   => $items,
            'total'   => $total,
            'paginas' => max(1, (int) ceil($total / $porPagina)),
            'pagina'  => $pagina,
        ];
    }

    public function estadoMensaje(int $id, string $estado): bool
    {
        if (!in_array($estado, ['nuevo', 'en_curso', 'resuelto', 'spam'], true)) {
            return false;
        }

        $filas = $this->db->execute(
            'UPDATE mensajes_contacto
                SET estado = :e,
                    gestionado_por = :u,
                    respondido_en = COALESCE(:r, respondido_en)
              WHERE id = :id',
            [
                'e'  => $estado,
                'u'  => Auth::id(),
                'r'  => $estado === 'resuelto' ? date('Y-m-d H:i:s') : null,
                'id' => $id,
            ]
        );

        if ($filas > 0) {
            $this->auditar('mensaje.estado', 'mensajes_contacto', $id, ['estado' => $estado]);
        }

        return $filas > 0;
    }

    // -----------------------------------------------------------------
    //  Auditoría
    // -----------------------------------------------------------------

    /**
     * @return array{items:array<int,array<string,mixed>>,total:int,paginas:int,pagina:int}
     */
    public function auditoria(int $pagina = 1, int $porPagina = 30, string $accion = '', string $q = ''): array
    {
        $donde = '1 = 1';
        $parametros = [];

        if ($accion !== '') {
            $donde .= ' AND a.accion LIKE :a';
            $parametros['a'] = $accion . '%';
        }

        if (trim($q) !== '') {
            $donde .= ' AND (a.actor_email LIKE :q1 OR a.entidad LIKE :q2 OR a.ip LIKE :q3)';
            $like = '%' . trim($q) . '%';
            $parametros['q1'] = $like;
            $parametros['q2'] = $like;
            $parametros['q3'] = $like;
        }

        $total = (int) $this->db->scalar("SELECT COUNT(*) FROM auditoria a WHERE {$donde}", $parametros);
        $pagina = max(1, $pagina);

        $items = $this->db->select(
            "SELECT a.* FROM auditoria a WHERE {$donde}
              ORDER BY a.created_at DESC
              LIMIT " . (int) $porPagina . ' OFFSET ' . (int) (($pagina - 1) * $porPagina),
            $parametros
        );

        return [
            'items'   => $items,
            'total'   => $total,
            'paginas' => max(1, (int) ceil($total / $porPagina)),
            'pagina'  => $pagina,
        ];
    }

    /** @return array<string,int> accion => total */
    public function accionesFrecuentes(): array
    {
        try {
            $filas = $this->db->select(
                'SELECT accion, COUNT(*) AS total FROM auditoria GROUP BY accion ORDER BY total DESC LIMIT 20'
            );
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach ($filas as $fila) {
            $out[(string) $fila['accion']] = (int) $fila['total'];
        }

        return $out;
    }

    // -----------------------------------------------------------------
    //  Auditoría (escritura)
    // -----------------------------------------------------------------

    /** @param array<string,mixed> $datos */
    public function auditar(string $accion, ?string $entidad = null, ?int $entId = null, array $datos = []): void
    {
        try {
            $usuario = Auth::user();

            $this->db->insert('auditoria', [
                'actor_id'    => $usuario['id'] ?? null,
                'actor_email' => $usuario['email'] ?? 'sistema',
                'accion'      => mb_substr($accion, 0, 60),
                'entidad'     => $entidad !== null ? mb_substr($entidad, 0, 60) : null,
                'entidad_id'  => $entId,
                'datos'       => $datos === [] ? null : json_encode($datos, JSON_UNESCAPED_UNICODE),
                'ip'          => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent'  => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
            ]);
        } catch (\Throwable $e) {
            Logger::warning('No se pudo auditar «' . $accion . '»: ' . $e->getMessage());
        }
    }
}
