<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Segundo factor TOTP (RFC 6238) construido sobre HOTP (RFC 4226).
 *
 * Parámetros elegidos por ser los que soportan Google Authenticator,
 * Aegis, 1Password, Authy y compañía:
 *
 *   · algoritmo  HMAC-SHA1
 *   · dígitos    6
 *   · periodo    30 segundos
 *   · secreto    160 bits (20 bytes aleatorios) codificados en Base32
 *
 * La verificación admite ±1 periodo de tolerancia para absorber el
 * desfase de reloj entre el servidor y el teléfono.
 */
final class Totp
{
    public const DIGITOS = 6;
    public const PASO = 30;

    private const ALFABETO = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    // -----------------------------------------------------------------
    //  Secreto
    // -----------------------------------------------------------------

    /** Secreto nuevo en Base32 (32 caracteres, sin relleno «=»). */
    public static function secreto(): string
    {
        return self::aBase32(random_bytes(20));
    }

    // -----------------------------------------------------------------
    //  Cálculo
    // -----------------------------------------------------------------

    /** Código vigente para un momento dado (por defecto, ahora). */
    public static function codigo(string $secreto, ?int $en = null): string
    {
        $contador = intdiv($en ?? time(), self::PASO);

        // Contador de 8 bytes en big-endian (HOTP §5.1)
        $mensaje = pack('NN', intdiv($contador, 4294967296), $contador % 4294967296);
        $hmac = hash_hmac('sha1', $mensaje, self::deBase32($secreto), true);

        // Truncación dinámica (HOTP §5.3)
        $offset = ord($hmac[19]) & 0x0f;
        $valor = ((ord($hmac[$offset]) & 0x7f) << 24)
            | (ord($hmac[$offset + 1]) << 16)
            | (ord($hmac[$offset + 2]) << 8)
            | ord($hmac[$offset + 3]);

        return str_pad(
            (string) ($valor % (10 ** self::DIGITOS)),
            self::DIGITOS,
            '0',
            STR_PAD_LEFT
        );
    }

    /**
     * Comprueba un código introducido por el usuario.
     *
     * Se acepta el código inmediatamente anterior y el siguiente para
     * tolerar diferencia de reloj; la comparación es en tiempo constante.
     */
    public static function verificar(string $secreto, string $codigo, int $ventana = 1): bool
    {
        $codigo = preg_replace('/\s+/', '', $codigo) ?? '';

        if (!preg_match('/^\d{' . self::DIGITOS . '}$/', $codigo) || $secreto === '') {
            return false;
        }

        $ahora = time();

        for ($i = -$ventana; $i <= $ventana; $i++) {
            if (hash_equals(self::codigo($secreto, $ahora + $i * self::PASO), $codigo)) {
                return true;
            }
        }

        Logger::security('Código TOTP incorrecto', ['intento' => $codigo]);

        return false;
    }

    // -----------------------------------------------------------------
    //  Emparejamiento con la aplicación del móvil
    // -----------------------------------------------------------------

    /** URI «otpauth://» que escanean los programas de autenticación. */
    public static function uri(string $secreto, string $email, string $emisor = 'MotorPress'): string
    {
        return 'otpauth://totp/' . rawurlencode($emisor) . ':' . rawurlencode($email)
            . '?secret=' . rawurlencode($secreto)
            . '&issuer=' . rawurlencode($emisor)
            . '&algorithm=SHA1'
            . '&digits=' . self::DIGITOS
            . '&period=' . self::PASO;
    }

    // -----------------------------------------------------------------
    //  Base32 (RFC 4648, sin relleno)
    // -----------------------------------------------------------------

    public static function aBase32(string $datos): string
    {
        $binario = '';

        foreach (str_split($datos) as $byte) {
            $binario .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        // Relleno a un múltiplo de 5 bits (cada grupo codifica un carácter)
        $binario = str_pad($binario, (intdiv(strlen($binario) - 1, 5) + 1) * 5, '0', STR_PAD_RIGHT);

        $salida = '';

        for ($i = 0; $i < strlen($binario); $i += 5) {
            $salida .= self::ALFABETO[bindec(substr($binario, $i, 5))];
        }

        return $salida;
    }

    public static function deBase32(string $texto): string
    {
        $texto = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $texto) ?? '');

        if ($texto === '') {
            return '';
        }

        $binario = '';

        for ($i = 0; $i < strlen($texto); $i++) {
            $pos = strpos(self::ALFABETO, $texto[$i]);
            if ($pos === false) {
                continue;
            }
            $binario .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $binario = substr($binario, 0, intdiv(strlen($binario), 8) * 8);
        $salida = '';

        for ($i = 0; $i < strlen($binario); $i += 8) {
            $salida .= chr(bindec(substr($binario, $i, 8)));
        }

        return $salida;
    }
}
