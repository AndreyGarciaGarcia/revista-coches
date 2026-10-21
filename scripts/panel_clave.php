<?php

/**
 * =====================================================================
 *  MotorPress · Clave maestra del panel
 * =====================================================================
 *
 *   php scripts/panel_clave.php                        genera una clave nueva
 *   php scripts/panel_clave.php --verifica "TEXTO"     comprueba una clave
 *   php scripts/panel_clave.php --sin-imprimir         la guarda sin mostrarla
 *   php scripts/panel_clave.php --desactiva-totp       borra el 2FA (móvil perdido)
 *
 * La clave maestra es el segundo factor «algo que sabes»: se pide junto
 * con la contraseña al entrar en /acceso/verificacion y, si está activo,
 * junto con el código TOTP del móvil.
 *
 * En el .env NO se guarda la clave, sino su hash Argon2id
 * (PANEL_CLAVE_HASH). Por eso:
 *
 *   · perder el .env no deja el panel abierto;
 *   · filtrar el .env no revela la clave (habría que romper el hash);
 *   · si pierdes la clave, sólo puedes generar otra con este script.
 *
 * Guárdala en un gestor de contraseñas: se imprime UNA sola vez.
 * =====================================================================
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\Env;

$opciones = cli_args($argv, ['verifica', 'sin-imprimir', 'desactiva-totp', 'help']);

if (isset($opciones['help'])) {
    echo <<<'TXT'
MotorPress · Clave maestra del panel

  php scripts/panel_clave.php                          genera una clave nueva
  php scripts/panel_clave.php --verifica "TEXTO"       comprueba si es la correcta
  php scripts/panel_clave.php --verifica TEXTO         (misma cosa, sin comillas)
  php scripts/panel_clave.php --sin-imprimir           la genera sin mostrarla
  php scripts/panel_clave.php --desactiva-totp         borra el 2FA de ADMIN_EMAIL
                                                       (móvil perdido o robado)

TXT;
    exit(0);
}

$raiz = dirname(__DIR__);
$env = $raiz . '/.env';

if (!is_file($env)) {
    fwrite(STDERR, "  ✗ No existe .env en la raíz del proyecto.\n");
    exit(1);
}

// ---------------------------------------------------------------------
//  Verificación de una clave conocida
// ---------------------------------------------------------------------
if (isset($opciones['verifica'])) {
    // Se admite --verifica=TEXTO y también --verifica TEXTO
    $texto = is_string($opciones['verifica']) ? $opciones['verifica'] : '';

    if ($texto === '' && isset($opciones[0]) && is_string($opciones[0])) {
        $texto = $opciones[0];
    }

    $hash = Env::getString('PANEL_CLAVE_HASH', '');

    if ($hash === '') {
        echo "  ✗ No hay PANEL_CLAVE_HASH configurado en .env.\n";
        exit(1);
    }

    if ($texto === '') {
        echo "  ✗ Indica la clave: php scripts/panel_clave.php --verifica \"TEXTO\"\n";
        exit(1);
    }

    if (password_verify($texto, $hash)) {
        echo "  ✔ La clave es correcta.\n";
        exit(0);
    }

    echo "  ✗ La clave no coincide con PANEL_CLAVE_HASH.\n";
    exit(1);
}

// ---------------------------------------------------------------------
//  Recuperación: móvil perdido o robado → se borra el secreto TOTP
//  Vuelta al estado inicial: contraseña + clave maestra y, a continuación,
//  se vuelve a emparejar el móvil desde /admin/seguridad.
// ---------------------------------------------------------------------
if (isset($opciones['desactiva-totp'])) {
    $correo = is_string($opciones['desactiva-totp']) && $opciones['desactiva-totp'] !== ''
        ? $opciones['desactiva-totp']
        : Env::getString('ADMIN_EMAIL', '');

    if ($correo === '') {
        fwrite(STDERR, "  ✗ Indica la cuenta: --desactiva-totp=correo@dominio.com\n");
        exit(1);
    }

    $db = Database::get();
    $usuario = $db->first('SELECT id, email FROM usuarios WHERE email = ?', [$correo]);

    if ($usuario === null) {
        fwrite(STDERR, "  ✗ No existe ninguna cuenta con ese correo.\n");
        exit(1);
    }

    $n = $db->execute(
        'UPDATE usuarios SET totp_secreto = NULL, totp_activado_en = NULL WHERE id = ?',
        [(int) $usuario['id']]
    );

    echo "┌──────────────────────────────────────────────┐\n";
    echo "│  MotorPress · Segundo factor restablecido    │\n";
    echo "└──────────────────────────────────────────────┘\n\n";
    echo $n > 0
        ? "  ✔ Segundo factor desactivado para {$usuario['email']}.\n\n"
        : "  · Esa cuenta ya tenía el segundo factor desactivado.\n\n";
    echo "  Ahora se entra con contraseña + clave maestra; después ve a\n";
    echo "  /admin/seguridad para emparejar el móvil con el nuevo QR.\n\n";
    exit(0);
}

// ---------------------------------------------------------------------
//  Generación de una clave nueva
// ---------------------------------------------------------------------

// Alfabeto sin caracteres ambiguos (sin I, l, O, 0, 1)
$alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
$longitud = 24;

$clave = '';
for ($i = 0; $i < $longitud; $i++) {
    $clave .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
}

// En grupos de 6 para que sea cómodo de leer y de introducir
$clave = implode('-', str_split($clave, 6));

$hash = Auth::hash($clave);

$contenido = (string) file_get_contents($env);
$linea = 'PANEL_CLAVE_HASH=' . $hash;

if (preg_match('/^PANEL_CLAVE_HASH=.*$/m', $contenido)) {
    $contenido = preg_replace('/^PANEL_CLAVE_HASH=.*$/m', $linea, $contenido, 1);
} else {
    $contenido = rtrim($contenido) . "\n\n"
        . "# Hash Argon2id de la clave maestra del panel (generado por scripts/panel_clave.php)\n"
        . $linea . "\n";
}

if (file_put_contents($env, $contenido, LOCK_EX) === false) {
    fwrite(STDERR, "  ✗ No se pudo escribir .env. Comprueba los permisos.\n");
    exit(1);
}

echo "┌──────────────────────────────────────────────┐\n";
echo "│  MotorPress · Clave maestra del panel        │\n";
echo "└──────────────────────────────────────────────┘\n\n";

if (isset($opciones['sin-imprimir'])) {
    echo "  ✔ Nueva clave guardada en .env (no se muestra por pantalla).\n";
} else {
    echo "  CLAVE MAESTRA:\n\n";
    echo "      \033[1m{$clave}\033[0m\n\n";
    echo "  Guárdala en tu gestor de contraseñas. NO se puede recuperar:\n";
    echo "  en .env sólo se ha escrito su hash (Argon2id).\n\n";
}

echo "  · Se pide al entrar en /acceso/verificacion (junto con la contraseña\n";
echo "    y, si está activo, el código TOTP del móvil).\n";
echo "  · Para rotarla vuelve a ejecutar este script.\n\n";
