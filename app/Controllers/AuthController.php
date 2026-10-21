<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Cifrado;
use App\Core\Database;
use App\Core\Env;
use App\Core\Exception\HttpException;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Security;
use App\Core\Session;
use App\Core\Totp;

/**
 * Acceso, registro y cierre de sesión.
 *
 * Medidas: límite de intentos por IP y por cuenta, contraseña con
 * política verificada, hash Argon2id, regeneración de identificador de
 * sesión al autenticarse y comprobación de usuario desactivado.
 *
 * Las cuentas privilegiadas (la del propietario del sitio) no terminan
 * el login con la contraseña: pasan antes por la verificación en dos
 * pasos —clave maestra del .env + código TOTP de la app del móvil—.
 */
final class AuthController extends Controller
{
    // -----------------------------------------------------------------
    //  Acceso
    // -----------------------------------------------------------------

    public function mostrarAcceso(Request $request): Response
    {
        if (Auth::check()) {
            return $this->redirect(url('cuenta'));
        }

        if (Auth::pendiente() !== null) {
            return $this->redirect(url('acceso/verificacion'));
        }

        return $this->view('pages/auth/acceso', [
            'title'       => 'Iniciar sesión — MotorPress',
            'description' => 'Accede a tu cuenta de MotorPress.',
            'canonical'   => url('acceso'),
            'registro'    => Env::getBool('REGISTRO_ABIERTO', false),
        ]);
    }

    public function acceder(Request $request): Response
    {
        $ip = $request->ip();

        $datos = $this->validate(
            $request->payload(),
            [
                'email'    => 'required|email|max:190',
                'password' => 'required|string|min:1|max:200',
            ],
            ['email' => 'El correo electrónico', 'password' => 'La contraseña']
        );

        $email = mb_strtolower(trim((string) $datos['email']));

        // Límite por IP: frena la enumeración y la fuerza bruta distribuida
        $porIp = 'login|' . $ip;
        if (!RateLimiter::attempt($porIp, 10, 300)) {
            $this->flash('error', 'Demasiados intentos desde esta red. Espera unos minutos.');

            return $this->redirect(url('acceso'));
        }

        // Límite por cuenta: frena la fuerza bruta contra un usuario concreto
        $porCuenta = 'login|' . $email;
        if (!RateLimiter::attempt($porCuenta, 8, 300)) {
            $this->flash('error', 'Esta cuenta está bloqueada temporalmente por seguridad.');

            return $this->redirect(url('acceso'));
        }

        $resultado = Auth::attempt($email, (string) $datos['password'], $ip, true);

        if (!$resultado['ok']) {
            $mensaje = match ($resultado['reason'] ?? 'credentials') {
                'disabled'  => 'Esta cuenta está desactivada. Contacta con el administrador.',
                'locked'    => 'Cuenta bloqueada por intentos fallidos. Prueba más tarde.',
                default     => 'El correo o la contraseña no son correctos.',
            };

            $this->flash('error', $mensaje);

            return $this->redirect(url('acceso'));
        }

        RateLimiter::clear($porIp);
        RateLimiter::clear($porCuenta);
        Session::clearOld();

        // Cuenta privilegiada: falta la verificación en dos pasos
        if (!empty($resultado['verificacion'])) {
            return $this->redirect(url('acceso/verificacion'));
        }

        return $this->redirect($this->destinoIntencion());
    }

    // -----------------------------------------------------------------
    //  Verificación en dos pasos (clave maestra + TOTP)
    // -----------------------------------------------------------------

    public function mostrarVerificacion(Request $request): Response
    {
        if (Auth::check()) {
            return $this->redirect(url('admin'));
        }

        $pendiente = Auth::pendiente();

        if ($pendiente === null) {
            $this->flash('error', 'La verificación ha caducado. Vuelve a identificarte.');

            return $this->redirect(url('acceso'));
        }

        $usuario = Database::get()->first(
            'SELECT u.email, u.totp_activado_en
               FROM usuarios u
              WHERE u.id = :id AND u.borrado_en IS NULL AND u.estado = \'activo\'',
            ['id' => (int) $pendiente['uid']]
        );

        if ($usuario === null) {
            Auth::cancelarPendiente();

            return $this->redirect(url('acceso'));
        }

        return $this->view('pages/auth/verificacion', [
            'title'       => 'Verificación en dos pasos — MotorPress',
            'description' => 'Confirma tu identidad para entrar en el panel.',
            'canonical'   => url('acceso/verificacion'),
            'email'       => (string) $usuario['email'],
            'totp'        => $usuario['totp_activado_en'] !== null,
        ]);
    }

    public function verificar(Request $request): Response
    {
        $pendiente = Auth::pendiente();

        if ($pendiente === null) {
            $this->flash('error', 'La verificación ha caducado. Vuelve a identificarte.');

            return $this->redirect(url('acceso'));
        }

        $ip = $request->ip();

        if (!RateLimiter::attempt('verificacion|' . $ip, 8, 300)) {
            Auth::cancelarPendiente();
            $this->flash('error', 'Demasiados intentos desde esta red. Espera unos minutos.');

            return $this->redirect(url('acceso'));
        }

        $clave = (string) ($request->input('clave') ?? '');
        $codigo = trim((string) ($request->input('codigo') ?? ''));

        $usuario = Database::get()->first(
            'SELECT u.id, u.email, u.totp_secreto, u.totp_activado_en
               FROM usuarios u
              WHERE u.id = :id AND u.borrado_en IS NULL AND u.estado = \'activo\'',
            ['id' => (int) $pendiente['uid']]
        );

        if ($usuario === null) {
            Auth::cancelarPendiente();

            return $this->redirect(url('acceso'));
        }

        // -------------------------------------------------------------
        // 1. Clave maestra (segundo factor «algo que sabes», en .env)
        // -------------------------------------------------------------
        $hashMaestro = Env::getString('PANEL_CLAVE_HASH', '');

        $claveOk = $hashMaestro !== ''
            && $clave !== ''
            && password_verify($clave, $hashMaestro);

        // -------------------------------------------------------------
        // 2. Código TOTP de la app del móvil («algo que tienes»)
        // -------------------------------------------------------------
        $conTotp = $usuario['totp_activado_en'] !== null;
        $codigoOk = !$conTotp
            || Totp::verificar((string) Cifrado::secretoDe($usuario['totp_secreto']), $codigo);

        if (!$claveOk || !$codigoOk) {
            $intentos = Auth::falloVerificacion();

            Logger::security('Verificación en dos pasos fallida', [
                'email'    => $usuario['email'],
                'ip'       => $ip,
                'clave'    => $claveOk ? 'ok' : 'mal',
                'codigo'   => $codigoOk ? 'ok' : 'mal',
                'intentos' => $intentos,
            ]);

            $this->flash(
                'error',
                $intentos >= 5
                    ? 'Demasiados intentos fallidos. Vuelve a empezar el acceso.'
                    : 'La clave maestra o el código no son correctos.'
            );

            return $this->redirect(url('acceso/verificacion'));
        }

        RateLimiter::clear('verificacion|' . $ip);
        Auth::confirmarPendiente();

        \App\Core\Auditoria::registrar('acceso.verificado', 'usuarios', (int) $usuario['id'], [
            'ip' => $ip,
        ]);
        Logger::security('Verificación en dos pasos superada', [
            'email' => $usuario['email'],
            'ip'    => $ip,
        ]);

        return $this->redirect($this->destinoIntencion());
    }

    // -----------------------------------------------------------------
    //  Registro
    // -----------------------------------------------------------------

    public function mostrarRegistro(Request $request): Response
    {
        $this->registroAbierto();

        if (Auth::check()) {
            return $this->redirect(url('cuenta'));
        }

        return $this->view('pages/auth/registro', [
            'title'       => 'Crear cuenta — MotorPress',
            'description' => 'Regístrate para comentar artículos y guardar tus vehículos favoritos.',
            'canonical'   => url('registro'),
        ]);
    }

    public function registrarse(Request $request): Response
    {
        $this->registroAbierto();

        $ip = $request->ip();

        if (!RateLimiter::attempt('registro|' . $ip, 3, 3600)) {
            $this->flash('error', 'Has creado varias cuentas seguidas. Inténtalo más tarde.');

            return $this->redirect(url('registro'));
        }

        $datos = $this->validate(
            $request->payload(),
            [
                'nombre'    => 'required|string|min:2|max:100',
                'apellidos' => 'sometimes|nullable|string|max:120',
                'email'     => 'required|email|max:190',
                'password'  => 'required|string|min:10|max:200',
                'acepta'    => 'required',
            ],
            [
                'nombre'    => 'El nombre',
                'apellidos' => 'Los apellidos',
                'password'  => 'La contraseña',
                'acepta'    => 'Los términos y la política de privacidad',
            ]
        );

        // Política de contraseñas (longitud, complejidad, listas negras)
        $problemas = Security::passwordIssues((string) $datos['password']);
        if ($problemas !== []) {
            $this->flash('error', implode(' ', $problemas));

            return $this->redirect(url('registro'));
        }

        $email = mb_strtolower(trim((string) $datos['email']));

        $existe = Database::get()->first(
            'SELECT id FROM usuarios WHERE email = :e AND borrado_en IS NULL',
            ['e' => $email]
        );

        if ($existe !== null) {
            $this->flash('error', 'Ya existe una cuenta con ese correo.');

            return $this->redirect(url('registro'));
        }

        $rol = Database::get()->first('SELECT id FROM roles WHERE clave = "lector"');
        $rolId = $rol !== null ? (int) $rol['id'] : 1;

        try {
            $id = Database::get()->insert('usuarios', [
                'uuid'             => '',
                'email'            => $email,
                'password_hash'    => Auth::hash((string) $datos['password']),
                'nombre'           => trim((string) $datos['nombre']),
                'apellidos'        => isset($datos['apellidos']) ? trim((string) $datos['apellidos']) : null,
                'rol_id'           => $rolId,
                'estado'           => 'activo',
                'email_verificado_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            $this->flash('error', 'No se pudo crear la cuenta. Inténtalo de nuevo.');

            return $this->redirect(url('registro'));
        }

        Auth::login($id);
        \App\Core\Auditoria::registrar('usuario.registrar', 'usuarios', $id, ['email' => $email]);

        $this->flash('success', '¡Bienvenido a MotorPress! Tu cuenta está lista.');

        return $this->redirect($this->destinoIntencion());
    }

    // -----------------------------------------------------------------
    //  Salida
    // -----------------------------------------------------------------

    public function salir(Request $request): Response
    {
        Auth::logout();

        return $this->redirect(url(''));
    }

    // -----------------------------------------------------------------
    //  Utilidades
    // -----------------------------------------------------------------

    /**
     * El registro público puede cerrarse desde .env (REGISTRO_ABIERTO).
     * Con él cerrado, sólo el propietario da de alta cuentas desde el
     * panel: nadie puede crear identidades propias en la plataforma.
     */
    private function registroAbierto(): void
    {
        if (!Env::getBool('REGISTRO_ABIERTO', false)) {
            Logger::security('Intento de acceso al registro cerrado', [
                'ip' => $this->request->ip(),
            ]);

            throw new HttpException(404, 'El registro de cuentas no está disponible.');
        }
    }

    /** Destino posterior al acceso, validado para no salir del sitio. */
    private function destinoIntencion(): string
    {
        $intencion = Session::get('intended');
        Session::forget('intended');

        if (is_string($intencion) && $intencion !== '') {
            if (!preg_match('#^(https?:)?//#i', $intencion) && !str_starts_with($intencion, '/')) {
                return url(trim($intencion, '/'));
            }

            $base = '/' . trim((string) (parse_url(\App\Core\Env::getString('APP_URL'), PHP_URL_PATH) ?? ''), '/');
            $ruta = parse_url($intencion, PHP_URL_PATH) ?: '';

            if ($base !== '/' && str_starts_with((string) $ruta, $base)) {
                return url(ltrim(substr((string) $ruta, strlen($base)), '/'));
            }

            if ($base === '/' && $ruta !== '') {
                return url(ltrim((string) $ruta, '/'));
            }
        }

        // Sin destino previo: el panel para quien administra, la cuenta
        // para el lector registrado y el inicio para el resto.
        if (Auth::check()) {
            $nivel = (int) (Auth::user()['rol_nivel'] ?? 0);

            return $nivel >= 30 ? url('admin') : url('cuenta');
        }

        return url('');
    }
}
