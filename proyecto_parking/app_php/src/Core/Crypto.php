<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Xifratge de dades sensibles (telèfon, DNI, referència de PayPal/Bizum)
 * amb AES-256-GCM (openssl_encrypt). La clau "app_key" (32 bytes en base64)
 * és a config/config.php, fora de la BD: si algú roba la BD no pot llegir les dades.
 *
 * Format guardat: base64( IV[12] | TAG[16] | text xifrat )
 */
final class Crypto
{
    private const CIPHER = 'aes-256-gcm';

    private static function clau(): string
    {
        $clau = base64_decode((string) Config::get('app_key', ''), true);
        if ($clau === false || strlen($clau) !== 32) {
            throw new \RuntimeException('app_key no configurada o incorrecta a config.php');
        }
        return $clau;
    }

    public static function encrypt(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return null;
        }
        $iv = random_bytes(12);
        $tag = '';
        $xifrat = openssl_encrypt($text, self::CIPHER, self::clau(), OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $xifrat);
    }

    public static function decrypt(?string $dades): ?string
    {
        if (!$dades) {
            return null;
        }
        $raw = base64_decode($dades, true);
        if ($raw === false || strlen($raw) < 29) {
            return null;
        }
        $text = openssl_decrypt(substr($raw, 28), self::CIPHER, self::clau(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $text === false ? null : $text;
    }

    /** Hash amb clau (HMAC-SHA256) per comprovar duplicats sense desxifrar (p. ex. DNI). */
    public static function hash(string $text): string
    {
        return hash_hmac('sha256', $text, self::clau());
    }

    /** Mostra només els últims caràcters: *****678Z */
    public static function mask(?string $text, int $visibles = 4): string
    {
        if (!$text) {
            return '—';
        }
        $len = mb_strlen($text);
        return str_repeat('•', max(0, $len - $visibles)) . mb_substr($text, -$visibles);
    }
}
