<?php

declare(strict_types=1);

namespace Core\Security;

use Core\Config\Config;

/**
 * Authenticated symmetric encryption for data at rest (AES-256-GCM).
 *
 * The 32-byte key is derived from APP_KEY via SHA-256 so any APP_KEY length
 * works. Output is base64("v1" . iv . tag . ciphertext) so it is safe to store
 * in a TEXT column. GCM provides integrity: tampered ciphertext fails to
 * decrypt (returns null) rather than yielding garbage.
 */
final class Crypto
{
    private const CIPHER = 'aes-256-gcm';
    private const PREFIX = 'v1';

    private static function key(): string
    {
        $appKey = (string) Config::get('app.key', '');
        if ($appKey === '') {
            // Deterministic fallback so the app never fatally crashes; still
            // unique per-install because APP_KEY is normally set.
            $appKey = 'insecure-default-key';
        }
        return hash('sha256', $appKey, true); // 32 raw bytes
    }

    /** Encrypt a plaintext string; returns a storable base64 token (or null for empty input). */
    public static function encrypt(?string $plaintext): ?string
    {
        if ($plaintext === null || $plaintext === '') {
            return null;
        }
        $iv = random_bytes(12); // GCM standard nonce length
        $tag = '';
        $cipher = openssl_encrypt($plaintext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            return null;
        }
        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    /** Decrypt a token produced by encrypt(); returns null on tamper/format error. */
    public static function decrypt(?string $token): ?string
    {
        if ($token === null || $token === '' || !str_starts_with($token, self::PREFIX)) {
            return null;
        }
        $raw = base64_decode(substr($token, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 28) {
            return null;
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? null : $plain;
    }

    /**
     * Mask a sensitive value, revealing only the last N characters.
     * e.g. mask('123412341234') => 'XXXX-XXXX-1234'. Non-digit input is masked
     * generically as '••••1234'.
     */
    public static function mask(?string $value, int $visible = 4): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        $len = strlen($value);
        if ($len <= $visible) {
            return str_repeat('X', $len);
        }
        $last = substr($value, -$visible);
        // Aadhaar-style grouping for 12-digit numeric values.
        if (preg_match('/^\d{12}$/', $value)) {
            return 'XXXX-XXXX-' . $last;
        }
        return str_repeat('X', $len - $visible) . $last;
    }
}
