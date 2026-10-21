<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Auditoria;
use App\Core\Auth;
use App\Core\Cifrado;
use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Totp;

/**
 * Seguridad del panel: segundo factor (TOTP) y estado de la defensa.
 *
 * Sólo el propietario del sitio llega aquí (lista blanca + nivel 90),
 * y para llegar ya ha pasado por contraseña, clave maestra y, si está
 * activo, el código de la app del móvil.
 *
 * El secreto TOTP se empareja en dos tiempos:
 *   1. GET  → se genera y se queda en la sesión, se muestra el QR;
 *   2. POST → se introduce el código que marca el móvil y, si coincide,
 *             el secreto se cifra con AES-256-GCM y se guarda.
 */
final class SeguridadController extends Controller
{
    public function index(Request $request): Response
    {
        $usuario = Auth::user() ?? [];
        $activo = !empty($usuario['totp_activado_en']);

        $pendiente = null;

        if (!$activo) {
            $pendiente = Session::get('totp_pendiente');
            $pendiente = is_string($pendiente) && $pendiente !== '' ? $pendiente : null;

            if ($pendiente === null) {
                $pendiente = Totp::secreto();
                Session::set('totp_pendiente', $pendiente);
            }
        }

        return $this->view('pages/admin/seguridad', [
            'title'          => 'Seguridad — Panel',
            'description'    => 'Segundo factor, cuentas con acceso y estado de la defensa del panel.',
            'totpActivo'     => $activo,
            'totpDesde'      => $usuario['totp_activado_en'] ?? null,
            'uri'            => $activo ? '' : Totp::uri((string) $pendiente, (string) ($usuario['email'] ?? '')),
            'secreto'        => $activo ? '' : (string) $pendiente,
            'claveMaestra'   => Env::getString('PANEL_CLAVE_HASH', '') !== '',
            'listaBlanca'    => Env::getString('ADMIN_EMAIL', ''),
            'registro'       => Env::getBool('REGISTRO_ABIERTO', false),
            'inactividad'    => intdiv(Env::getInt('PANEL_INACTIVIDAD', 900), 60),
            'cuentas'        => $this->cuentas(),
        ], 'layouts/admin');
    }

    /** Activa el segundo factor tras validar un código del móvil. */
    public function activar(Request $request): Response
    {
        $usuario = Auth::user() ?? [];
        $ip = $request->ip();

        if (!RateLimiter::attempt('totp|' . $ip, 10, 300)) {
            $this->flash('error', 'Demasiados intentos. Espera unos minutos.');

            return $this->redirect(url('admin/seguridad'));
        }

        $pendiente = Session::get('totp_pendiente');
        $pendiente = is_string($pendiente) && $pendiente !== '' ? $pendiente : null;

        if ($pendiente === null || !empty($usuario['totp_activado_en'])) {
            $this->flash('error', 'No hay ninguna configuración pendiente.');

            return $this->redirect(url('admin/seguridad'));
        }

        $codigo = trim((string) ($request->input('codigo') ?? ''));

        if (!Totp::verificar($pendiente, $codigo)) {
            $this->flash('error', 'El código no es correcto. Comprueba la hora del móvil e inténtalo de nuevo.');

            return $this->redirect(url('admin/seguridad'));
        }

        Database::get()->update('usuarios', [
            'totp_secreto'     => Cifrado::cifrar($pendiente),
            'totp_activado_en' => date('Y-m-d H:i:s'),
        ], ['id' => (int) $usuario['id']]);

        Session::forget('totp_pendiente');
        Auth::refresh();

        Auditoria::registrar('seguridad.totp.activar', 'usuarios', (int) $usuario['id'], ['ip' => $ip]);
        Logger::security('Segundo factor activado', ['email' => $usuario['email'] ?? '-', 'ip' => $ip]);

        $this->flash('success', 'Verificación en dos pasos activada. A partir de ahora el panel pide el código de tu móvil.');

        return $this->redirect(url('admin/seguridad'));
    }

    /** Desactiva el segundo factor (exige demostrar que lo tienes). */
    public function desactivar(Request $request): Response
    {
        $usuario = Auth::user() ?? [];
        $ip = $request->ip();

        if (!RateLimiter::attempt('totp|' . $ip, 10, 300)) {
            $this->flash('error', 'Demasiados intentos. Espera unos minutos.');

            return $this->redirect(url('admin/seguridad'));
        }

        if (empty($usuario['totp_activado_en'])) {
            $this->flash('error', 'El segundo factor no está activado.');

            return $this->redirect(url('admin/seguridad'));
        }

        $codigo = trim((string) ($request->input('codigo') ?? ''));
        $secreto = Cifrado::secretoDe($usuario['totp_secreto'] ?? null);

        if ($secreto === null || !Totp::verificar($secreto, $codigo)) {
            $this->flash('error', 'El código no es correcto. El segundo factor sigue activado.');

            return $this->redirect(url('admin/seguridad'));
        }

        Database::get()->update('usuarios', [
            'totp_secreto'     => null,
            'totp_activado_en' => null,
        ], ['id' => (int) $usuario['id']]);

        Auth::refresh();

        Auditoria::registrar('seguridad.totp.desactivar', 'usuarios', (int) $usuario['id'], ['ip' => $ip]);
        Logger::security('Segundo factor desactivado', ['email' => $usuario['email'] ?? '-', 'ip' => $ip]);

        $this->flash('success', 'Segundo factor desactivado. Se ha generado uno nuevo para volver a emparejar.');

        return $this->redirect(url('admin/seguridad'));
    }

    /**
     * Cuentas que existen en la plataforma y quién puede entrar.
     *
     * @return array<int,array<string,mixed>>
     */
    private function cuentas(): array
    {
        try {
            return Database::get()->select(
                'SELECT u.email, u.estado, u.totp_activado_en, u.ultimo_acceso,
                        r.nombre AS rol, r.nivel
                   FROM usuarios u
                   LEFT JOIN roles r ON r.id = u.rol_id
                  WHERE u.borrado_en IS NULL
                  ORDER BY r.nivel DESC, u.id
                  LIMIT 50'
            );
        } catch (\Throwable $e) {
            Logger::error('No se pudo listar las cuentas: ' . $e->getMessage());

            return [];
        }
    }
}
