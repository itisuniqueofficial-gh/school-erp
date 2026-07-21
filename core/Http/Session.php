<?php

declare(strict_types=1);

namespace Core\Http;

use Core\Config\Config;

/**
 * Session manager with secure cookie configuration, flash data, and periodic
 * session-id regeneration to mitigate fixation attacks.
 */
final class Session
{
    private bool $started = false;

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            $this->rotateFlash();
            $this->maybeRegenerate();
            return;
        }

        $path = (string) Config::get('session.path');
        if ($path && !is_dir($path)) {
            @mkdir($path, 0770, true);
        }
        if ($path && is_dir($path)) {
            session_save_path($path);
        }

        session_name((string) Config::get('session.cookie_name', 'school_erp_session'));

        session_set_cookie_params([
            'lifetime' => (int) Config::get('session.lifetime', 120) * 60,
            'path'     => '/',
            'domain'   => '',
            'secure'   => (bool) Config::get('session.secure', false),
            'httponly' => (bool) Config::get('session.http_only', true),
            'samesite' => (string) Config::get('session.same_site', 'Lax'),
        ]);

        session_start();
        $this->started = true;

        $this->rotateFlash();
        $this->maybeRegenerate();
    }

    /**
     * Regenerate the session id periodically to reduce fixation risk.
     */
    private function maybeRegenerate(): void
    {
        $interval = (int) Config::get('session.regenerate', 30) * 60;
        $last = $_SESSION['_last_regenerated'] ?? null;
        if ($last === null) {
            $_SESSION['_last_regenerated'] = time();
            return;
        }
        if (time() - (int) $last > $interval) {
            $this->regenerate();
        }
    }

    public function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION['_last_regenerated'] = time();
        }
    }

    /**
     * Age flash data: current-request flashes move out, next-request flashes become current.
     */
    private function rotateFlash(): void
    {
        $_SESSION['_flash_now'] = $_SESSION['_flash_next'] ?? [];
        $_SESSION['_flash_next'] = [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);
        $this->forget($key);
        return $value;
    }

    public function all(): array
    {
        return $_SESSION ?? [];
    }

    /**
     * Flash a value available on the next request.
     */
    public function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash_next'][$key] = $value;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_flash_now'][$key] ?? $default;
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'],
                    'domain'   => $params['domain'],
                    'secure'   => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function token(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }
}
