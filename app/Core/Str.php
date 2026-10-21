<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Utilidades de texto (sin dependencias de la extensión intl,
 * que no siempre está disponible en XAMPP).
 */
final class Str
{
    /**
     * Genera un slug URL-friendly y en minúsculas.
     * Soporta acentos en español (á é í ó ú ñ ü …).
     */
    public static function slug(string $value, int $max = 160): string
    {
        $map = [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u', 'ų' => 'u',
            'ñ' => 'n', 'ç' => 'c', 'ß' => 's', 'æ' => 'ae', 'œ' => 'oe',
            'ð' => 'd', 'þ' => 'th', 'ł' => 'l', 'ř' => 'r', 'š' => 's', 'ž' => 'z',
            'č' => 'c', 'ć' => 'c', 'đ' => 'd', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
        ];

        $lower = mb_strtolower($value, 'UTF-8');
        $lower = strtr($lower, $map);

        // Quitar todo lo que no sea letra, número o separador
        $lower = preg_replace('/[^a-z0-9]+/', '-', $lower) ?? '';
        $lower = trim($lower, '-');

        if (mb_strlen($lower) > $max) {
            $cut = mb_substr($lower, 0, $max);
            $cut = rtrim($cut, '-');
            $lower = $cut;
        }

        return $lower === '' ? 'sin-titulo' : $lower;
    }

    /** Recorta un texto respetando la palabra completa. */
    public static function excerpt(string $text, int $length = 160): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        $cut = mb_substr($text, 0, $length + 1);
        $pos = mb_strrpos($cut, ' ');
        if ($pos !== false && $pos > $length * 0.5) {
            $cut = mb_substr($cut, 0, $pos);
        } else {
            $cut = mb_substr($text, 0, $length);
        }

        return rtrim($cut, ' ,;:.') . '…';
    }

    /** Formato compacto de números en español (12,4 mil / 1,2 M). */
    public static function compact(int|float $n): string
    {
        if ($n >= 1_000_000) {
            return number_format($n / 1_000_000, 1, ',', '.') . ' M';
        }
        if ($n >= 1_000) {
            return number_format($n / 1_000, 1, ',', '.') . ' mil';
        }

        return (string) $n;
    }

    /** Token aleatorio seguro (CSRF, claves de sesión, API keys). */
    public static function token(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    /** Formato de euros: 45900 -> "45.900 €" */
    public static function euros(int|float $n): string
    {
        return number_format($n, 0, ',', '.') . ' €';
    }

    /** Formato español genérico: 4.9 -> "4,9" */
    public static function num(int|float $n, int $decimals = 1): string
    {
        return number_format($n, $decimals, ',', '.');
    }
}
