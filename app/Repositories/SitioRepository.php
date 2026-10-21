<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Str;

/**
 * Datos transversales del sitio: configuración, suscriptores,
 * mensajes de contacto y métricas de portada.
 */
final class SitioRepository
{
    /** @var array<string,string>|null */
    private static ?array $config = null;

    private Database $db;

    public function __construct()
    {
        $this->db = Database::get();
    }

    // -----------------------------------------------------------------
    //  Configuración (tabla `configuracion`)
    // -----------------------------------------------------------------

    /** @return array<string,array<string,mixed>> */
    public function configuracion(): array
    {
        if (self::$config === null) {
            try {
                $filas = $this->db->select('SELECT * FROM configuracion');
            } catch (\Throwable $e) {
                Logger::warning('configuracion no disponible: ' . $e->getMessage());
                $filas = [];
            }

            self::$config = [];
            foreach ($filas as $fila) {
                self::$config[(string) $fila['clave']] = $fila;
            }
        }

        return self::$config;
    }

    public function valor(string $clave, string $defecto = ''): string
    {
        $fila = $this->configuracion()[$clave] ?? null;

        return $fila === null ? $defecto : (string) ($fila['valor'] ?? $defecto);
    }

    public function guardarValor(string $clave, string $valor): void
    {
        $this->db->execute(
            'UPDATE configuracion SET valor = :v WHERE clave = :c AND editable = 1',
            ['v' => $valor, 'c' => $clave]
        );
        self::$config = null;
    }

    // -----------------------------------------------------------------
    //  Newsletter
    // -----------------------------------------------------------------

    /**
     * Alta de suscriptor. Devuelve [ok, mensaje] sin filtrar información
     * sobre si el correo ya existía (evita la enumeración de cuentas).
     *
     * @return array{0:bool,1:string}
     */
    public function suscribir(string $email, ?string $nombre, string $ip): array
    {
        $email = mb_strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [false, 'Introduce una dirección de correo válida.'];
        }

        $existe = $this->db->first('SELECT id, estado FROM suscriptores WHERE email = :e', ['e' => $email]);

        if ($existe !== null) {
            if ($existe['estado'] === 'activo') {
                return [true, 'Ya estabas suscrito. Te mantendremos al día.'];
            }

            $this->db->execute(
                'UPDATE suscriptores
                    SET estado = "activo", nombre = COALESCE(:n, nombre),
                        consentimiento_at = NOW(), consentimiento_ip = :ip,
                        confirmado_at = NOW()
                  WHERE id = :id',
                ['n' => $nombre, 'ip' => $ip, 'id' => (int) $existe['id']]
            );

            return [true, '¡Listo! Hemos reactivado tu suscripción.'];
        }

        $this->db->insert('suscriptores', [
            'email'             => $email,
            'nombre'            => $nombre !== null ? mb_substr($nombre, 0, 80) : null,
            'token_baja'        => sha1(Str::token(40)),
            'consentimiento_at' => date('Y-m-d H:i:s'),
            'consentimiento_ip' => $ip,
            'estado'            => 'activo',
            'confirmado_at'     => date('Y-m-d H:i:s'),
        ]);

        return [true, '¡Gracias! Te enviaremos lo mejor de la semana.'];
    }

    /** Baja con token (desde el correo). */
    public function darDeBaja(string $token): bool
    {
        return $this->db->execute(
            'UPDATE suscriptores SET estado = "baja" WHERE token_baja = :t AND estado <> "baja"',
            ['t' => $token]
        ) > 0;
    }

    public function totalSuscriptores(): int
    {
        return (int) $this->db->scalar(
            'SELECT COUNT(*) FROM suscriptores WHERE estado = "activo"'
        );
    }

    // -----------------------------------------------------------------
    //  Contacto
    // -----------------------------------------------------------------

    /** @param array<string,mixed> $datos @return array{0:bool,1:string} */
    public function guardarMensaje(array $datos): array
    {
        try {
            $this->db->insert('mensajes_contacto', [
                'uuid'       => '',
                'nombre'     => mb_substr((string) $datos['nombre'], 0, 120),
                'email'      => mb_substr((string) $datos['email'], 0, 190),
                'asunto'     => mb_substr((string) $datos['asunto'], 0, 160),
                'mensaje'    => (string) $datos['mensaje'],
                'ip'         => $datos['ip'] ?? null,
                'user_agent' => $datos['user_agent'] ?? null,
                'usuario_id' => $datos['usuario_id'] ?? null,
                'estado'     => 'nuevo',
            ]);

            $this->registrarEnvio('contacto', 'Contacto: ' . $datos['asunto'], 'enviado', 'Alta en mensajes');

            return [true, 'Mensaje enviado. Te responderemos lo antes posible.'];
        } catch (\Throwable $e) {
            Logger::error('No se pudo guardar el mensaje: ' . $e->getMessage());

            return [false, 'No se pudo enviar el mensaje. Inténtalo de nuevo en unos minutos.'];
        }
    }

    /** @return array{0:bool,1:string} */
    public function registrarEnvio(string $tipo, string $asunto, string $estado, string $detalle = ''): array
    {
        try {
            $this->db->insert('envios', [
                'tipo'          => $tipo,
                'asunto'        => mb_substr($asunto, 0, 200),
                'destinatarios' => 1,
                'estado'        => $estado,
                'detalle'       => mb_substr($detalle, 0, 400),
            ]);

            return [true, 'ok'];
        } catch (\Throwable $e) {
            Logger::warning('No se pudo registrar el envío: ' . $e->getMessage());

            return [false, $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------
    //  Métricas de portada
    // -----------------------------------------------------------------

    /** @return array<string,int> */
    public function metricas(): array
    {
        $consulta = static function (string $sql): int {
            try {
                return (int) Database::get()->scalar($sql);
            } catch (\Throwable) {
                return 0;
            }
        };

        return [
            'modelos'      => $consulta('SELECT COUNT(*) FROM v_coches_catalogo'),
            'marcas'       => $consulta('SELECT COUNT(*) FROM v_marcas_conteo WHERE num_modelos > 0'),
            'articulos'    => $consulta('SELECT COUNT(*) FROM v_noticias_publicadas'),
            'suscriptores' => $consulta('SELECT COUNT(*) FROM suscriptores WHERE estado = "activo"'),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function enviosRecientes(int $limite = 20): array
    {
        return $this->db->select(
            'SELECT * FROM envios ORDER BY creado_en DESC LIMIT ' . (int) $limite
        );
    }
}
