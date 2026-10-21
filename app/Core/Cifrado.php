<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Cifrado simétrico autenticado (AES-256-GCM) para datos sensibles que
 * viven en base de datos: el secreto TOTP de cada usuario.
 *
 * La clave se deriva de APP_KEY con HKDF-SHA256 y un etiqueta propia de
 * este fin, de modo que:
 *
 *   · quien roba un volcado de MySQL NO puede leer el secreto si no
 *     tiene además el .env (y viceversa: la clave no sirve para tocar
 *     datos que no estén cifrados con esta etiqueta);
 *   · cada cifrado usa un IV aleatorio, así que dos mensajes iguales
 *     producen criptogramas distintos;
 *   · el etiquetado GCM detecta cualquier manipulación del texto.
 */
final class Cifrado
{
    private const CIFRADO = 'aes-256-gcm';
    private const IV = 12;
    private const TAG = 16;

    private static ?string $clave = null;

    /** Deriva la clave de trabajo a partir de APP_KEY. */
    private static function clave(): string
    {
        if (self::$clave === null) {
            $appKey = Env::getString('APP_KEY');

            if ($appKey === '') {
                throw new RuntimeException('Falta APP_KEY en el archivo .env.');
            }

            self::$clave = hash_hkdf('sha256', $appKey, 32, 'motorpress-totp');
        }

        return self::$clave;
    }

    /** Devuelve IV (12) + etiqueta (16) + texto cifrado, en binario. */
    public static function cifrar(string $texto): string
    {
        $iv = random_bytes(self::IV);
        $etiqueta = '';

        $cifrado = openssl_encrypt($texto, self::CIFRADO, self::clave(), OPENSSL_RAW_DATA, $iv, $etiqueta);

        if ($cifrado === false || $etiqueta === '') {
            throw new RuntimeException('No se pudo cifrar el contenido.');
        }

        return $iv . $etiqueta . $cifrado;
    }

    public static function descifrar(string $contenido): string
    {
        if (strlen($contenido) <= self::IV + self::TAG) {
            throw new RuntimeException('Contenido cifrado incompleto.');
        }

        $iv = substr($contenido, 0, self::IV);
        $etiqueta = substr($contenido, self::IV, self::TAG);
        $cifrado = substr($contenido, self::IV + self::TAG);

        $texto = openssl_decrypt($cifrado, self::CIFRADO, self::clave(), OPENSSL_RAW_DATA, $iv, $etiqueta);

        if ($texto === false) {
            throw new RuntimeException('No se pudo descifrar el contenido (¿APP_KEY distinta?).');
        }

        return $texto;
    }

    /** Secreto TOTP guardado en la fila de un usuario → texto plano. */
    public static function secretoDe(?string $cifrado): ?string
    {
        if ($cifrado === null || $cifrado === '') {
            return null;
        }

        try {
            return self::descifrar($cifrado);
        } catch (\Throwable) {
            Logger::error('No se pudo descifrar el secreto TOTP de un usuario.');

            return null;
        }
    }
}
