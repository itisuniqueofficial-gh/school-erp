<?php

declare(strict_types=1);

namespace Core\Security;

use Core\Config\Config;

/**
 * CSRF token generation and verification using constant-time comparison.
 */
final class Csrf
{
    /**
     * Get (or lazily create) the CSRF token for the current session.
     */
    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    /**
     * Verify a supplied token against the session token.
     */
    public static function verify(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }
        $stored = $_SESSION['_csrf_token'] ?? '';
        if ($stored === '') {
            return false;
        }
        return hash_equals($stored, $token);
    }

    public static function fieldName(): string
    {
        return (string) Config::get('security.csrf_token_name', '_token');
    }
}
