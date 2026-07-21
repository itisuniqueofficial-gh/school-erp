<?php

declare(strict_types=1);

namespace Core\Security;

use Core\Config\Config;

/**
 * Password hashing wrapper using PHP's native bcrypt implementation.
 * Never uses MD5 or SHA1 for passwords.
 */
final class Hash
{
    public static function make(string $value): string
    {
        return password_hash($value, PASSWORD_BCRYPT, [
            'cost' => (int) Config::get('security.bcrypt_cost', 12),
        ]);
    }

    public static function verify(string $value, string $hash): bool
    {
        if ($hash === '') {
            return false;
        }
        return password_verify($value, $hash);
    }

    /**
     * Determine whether a hash needs rehashing (e.g. cost changed).
     */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, [
            'cost' => (int) Config::get('security.bcrypt_cost', 12),
        ]);
    }

    /**
     * Generic constant-time token hashing (for API/remember tokens, not passwords).
     */
    public static function token(string $value): string
    {
        return hash('sha256', $value);
    }
}
