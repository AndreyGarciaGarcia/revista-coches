<?php

/**
 * =====================================================================
 *  MotorPress · Elevación de privilegios desde consola
 * =====================================================================
 *
 *   php scripts/make-admin.php correo@ejemplo.com
 *   php scripts/make-admin.php correo@ejemplo.com --rol=root
 *   php scripts/make-admin.php --nuevo=correo@ejemplo.com --pass='Frase'
 *   php scripts/make-admin.php --listar
 *
 * Herramienta de emergencia para cuando no se puede entrar al panel
 * (contraseña olvidada, primera instalación). Toda operación deja trazo
 * en la tabla `auditoria` con actor «consola».
 * =====================================================================
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;

$opciones = cli_args($argv, ['rol::', 'nuevo::', 'pass::', 'listar', 'help']);

if (isset($opciones['help'])) {
    echo <<<TXT
MotorPress · Cuentas de administración

  php scripts/make-admin.php --listar                      lista roles y usuarios
  php scripts/make-admin.php correo@ej.com                 eleva a administrador
  php scripts/make-admin.php correo@ej.com --rol=root      elige el rol exacto
  php scripts/make-admin.php --nuevo=correo@ej.com         crea la cuenta y la eleva
                                --pass='Frase larga 2026!'

TXT;
    exit(0);
}

$db = Database::get();

$auditar = static function (string $accion, array $datos = []) use ($db): void {
    try {
        $db->insert('auditoria', [
            'actor_id'    => null,
            'actor_email' => 'consola',
            'accion'      => mb_substr($accion, 0, 60),
            'entidad'     => 'usuarios',
            'entidad_id'  => $datos['id'] ?? null,
            'datos'       => json_encode($datos, JSON_UNESCAPED_UNICODE),
            'ip'          => '127.0.0.1',
            'user_agent'  => 'scripts/make-admin.php',
        ]);
    } catch (Throwable $e) {
        fwrite(STDERR, 'Aviso: no se pudo registrar la auditoría: ' . $e->getMessage() . "\n");
    }
};

// ---------------------------------------------------------------------
//  Listado
// ---------------------------------------------------------------------

if (array_key_exists('listar', $opciones)) {
    $roles = $db->select('SELECT id, clave, nombre, nivel FROM roles ORDER BY nivel DESC');

    echo "\nRoles disponibles:\n";
    foreach ($roles as $r) {
        printf("  %-10s %-18s nivel %d\n", $r['clave'], $r['nombre'], (int) $r['nivel']);
    }

    $usuarios = $db->select(
        'SELECT u.email, u.nombre, u.estado, r.clave AS rol, r.nivel
           FROM usuarios u JOIN roles r ON r.id = u.rol_id
          WHERE u.borrado_en IS NULL
          ORDER BY r.nivel DESC, u.email'
    );

    echo "\nCuentas (" . count($usuarios) . "):\n";
    foreach ($usuarios as $u) {
        printf(
            "  %-36s %-16s %-11s %s\n",
            $u['email'],
            $u['rol'],
            $u['estado'],
            $u['nombre']
        );
    }

    echo "\n";
    exit(0);
}

// ---------------------------------------------------------------------
//  Rol objetivo
// ---------------------------------------------------------------------

$rolClave = (string) ($opciones['rol'] ?? 'admin');

$rol = $db->first('SELECT id, clave, nombre, nivel FROM roles WHERE clave = :c', ['c' => $rolClave]);

if ($rol === null) {
    fwrite(STDERR, "Rol desconocido: {$rolClave}\n");
    fwrite(STDERR, "Usa uno de: lector, autor, editor, admin, root\n");
    exit(1);
}

// ---------------------------------------------------------------------
//  Alta opcional
// ---------------------------------------------------------------------

$contrasena = (string) ($opciones['pass'] ?? '');
$nuevoEmail = trim((string) ($opciones['nuevo'] ?? ''));

if ($nuevoEmail !== '') {
    $existe = $db->first('SELECT id FROM usuarios WHERE email = :e', ['e' => $nuevoEmail]);

    if ($existe !== null) {
        echo "La cuenta ya existía: {$nuevoEmail}\n";
    } else {
        if (strlen($contrasena) < 10) {
            fwrite(STDERR, "La contraseña debe tener al menos 10 caracteres.\n");
            fwrite(STDERR, "Repítela con --pass='Frase larga y segura 2026!'\n");
            exit(1);
        }

        $email = filter_var($nuevoEmail, FILTER_VALIDATE_EMAIL);

        if ($email === false) {
            fwrite(STDERR, "Correo no válido: {$nuevoEmail}\n");
            exit(1);
        }

        $nuevoId = $db->insert('usuarios', [
            'uuid'           => sprintf(
                '%s-%s-%s-%s-%s',
                bin2hex(random_bytes(4)),
                bin2hex(random_bytes(2)),
                bin2hex(random_bytes(2)),
                bin2hex(random_bytes(2)),
                bin2hex(random_bytes(6))
            ),
            'email'          => $email,
            'password_hash'  => Auth::hash($contrasena),
            'nombre'         => 'Administrador',
            'apellidos'      => 'de consola',
            'estado'         => 'activo',
            'rol_id'         => (int) $rol['id'],
            'email_verificado_at' => date('Y-m-d H:i:s'),
            'ultimo_acceso'  => date('Y-m-d H:i:s'),
        ]);

        $auditar('usuario.crear', ['id' => $nuevoId, 'email' => $email, 'rol' => $rolClave, 'origen' => 'consola']);

        echo "\nCuenta creada: {$email}\n";
        echo "  Rol      : {$rol['nombre']} (nivel " . (int) $rol['nivel'] . ")\n";
        echo "  Contraseña: la que indicaste en --pass\n\n";
        echo "Anótala ahora: no se muestra de nuevo.\n\n";
        exit(0);
    }
}

// ---------------------------------------------------------------------
//  Elevación de una cuenta existente
// ---------------------------------------------------------------------

$email = (string) ($opciones[0] ?? '');

if ($email === '' && isset($opciones[1])) {
    $email = (string) $opciones[1];
}

if ($email === '') {
    fwrite(STDERR, "Indica el correo de la cuenta: php scripts/make-admin.php correo@ej.com\n");
    fwrite(STDERR, "O lista las cuentas con:       php scripts/make-admin.php --listar\n");
    exit(1);
}

$usuario = $db->first(
    'SELECT u.id, u.email, u.nombre, u.rol_id, r.clave AS rol
       FROM usuarios u JOIN roles r ON r.id = u.rol_id
      WHERE u.email = :e AND u.borrado_en IS NULL',
    ['e' => $email]
);

if ($usuario === null) {
    fwrite(STDERR, "No existe ninguna cuenta con ese correo: {$email}\n");
    fwrite(STDERR, "Créala con: php scripts/make-admin.php --nuevo={$email} --pass='…'\n");
    exit(1);
}

if ((int) $usuario['rol_id'] === (int) $rol['id']) {
    echo "La cuenta {$email} ya es «{$rol['nombre']}». Nada que hacer.\n";
    exit(0);
}

$db->update('usuarios', [
    'rol_id'         => (int) $rol['id'],
    'estado'         => 'activo',
    'actualizado_en' => date('Y-m-d H:i:s'),
], ['id' => (int) $usuario['id']]);

$auditar('usuario.eleva', [
    'id'      => (int) $usuario['id'],
    'email'   => $email,
    'antes'   => $usuario['rol'],
    'despues' => $rolClave,
    'origen'  => 'consola',
]);

echo "\n  Cuenta : {$email}\n";
echo "  Rol    : {$usuario['rol']} → {$rol['nombre']} (nivel " . (int) $rol['nivel'] . ")\n";
echo "  Estado : activo\n\n";
echo "La acción queda registrada en la auditoría con actor «consola».\n\n";
