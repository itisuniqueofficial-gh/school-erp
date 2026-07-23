<?php

declare(strict_types=1);

use Core\Application;
use Core\Config\Config;
use Core\Support\Env;
use Core\Http\Request;
use Core\Http\Session;
use Core\Security\Csrf;
use Core\View\View;
use Core\Auth\Auth;

if (!function_exists('app')) {
    /**
     * Resolve a service from the application container, or return the app itself.
     */
    function app(?string $abstract = null): mixed
    {
        $instance = Application::getInstance();
        if ($abstract === null) {
            return $instance;
        }
        return $instance->make($abstract);
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return rtrim(config('paths.root'), '/') . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return rtrim(config('paths.storage'), '/') . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        return rtrim(config('paths.public'), '/') . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('e')) {
    /**
     * Escape HTML special characters to prevent XSS.
     */
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('request')) {
    function request(): Request
    {
        return app(Request::class);
    }
}

if (!function_exists('session')) {
    function session(): Session
    {
        return app(Session::class);
    }
}

if (!function_exists('auth')) {
    function auth(): Auth
    {
        return app(Auth::class);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        $name = config('security.csrf_token_name', '_token');
        return '<input type="hidden" name="' . e($name) . '" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('method_field')) {
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (!function_exists('view')) {
    /**
     * Render a view template into a string.
     *
     * @param array<string, mixed> $data
     */
    function view(string $template, array $data = []): string
    {
        return app(View::class)->render($template, $data);
    }
}

if (!function_exists('base_url')) {
    /**
     * The application's base URL. Uses APP_URL when explicitly configured;
     * otherwise auto-detects from the current request so the app is portable
     * across any domain, subdirectory, HTTP/HTTPS, and reverse proxies
     * (Cloudflare / load balancers via X-Forwarded-* headers) with no code or
     * config changes. Falls back to http://localhost in CLI contexts.
     */
    function base_url(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $configured = trim((string) config('app.url', ''));
        return $cached = detect_base_url($_SERVER, $configured);
    }
}

if (!function_exists('detect_base_url')) {
    /**
     * Pure base-URL resolver (no globals) — unit-testable.
     *
     * @param array<string,mixed> $s  A $_SERVER-like array.
     * @param string $configured      Explicit APP_URL (wins when non-empty).
     */
    function detect_base_url(array $s, string $configured = ''): string
    {
        if ($configured !== '') {
            return rtrim($configured, '/');
        }
        if (empty($s['HTTP_HOST']) && empty($s['SERVER_NAME']) && empty($s['HTTP_X_FORWARDED_HOST'])) {
            return 'http://localhost'; // CLI / no request context
        }

        $https = (!empty($s['HTTPS']) && strtolower((string) $s['HTTPS']) !== 'off')
            || strtolower((string) ($s['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
            || strtolower((string) ($s['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on'
            || (int) ($s['SERVER_PORT'] ?? 0) === 443;
        $scheme = $https ? 'https' : 'http';

        // Proxies may send a comma-separated list; take the first host.
        $host = (string) ($s['HTTP_X_FORWARDED_HOST'] ?? $s['HTTP_HOST'] ?? $s['SERVER_NAME'] ?? 'localhost');
        $host = trim(explode(',', $host)[0]);

        // Subdirectory install support (e.g. example.com/erp).
        $dir = rtrim(str_replace('\\', '/', dirname((string) ($s['SCRIPT_NAME'] ?? ''))), '/');
        if ($dir === '.' || $dir === '/') {
            $dir = '';
        }
        if (str_ends_with($dir, '/public')) {
            $dir = substr($dir, 0, -7);
        }

        return $scheme . '://' . $host . $dir;
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        return base_url() . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return base_url() . '/' . ltrim($path, '/');
    }
}

if (!function_exists('redirect')) {
    /**
     * Issue an HTTP redirect and terminate the request.
     */
    function redirect(string $path, int $status = 302): never
    {
        $location = str_starts_with($path, 'http') ? $path : url($path);
        header('Location: ' . $location, true, $status);
        exit;
    }
}

if (!function_exists('back')) {
    function back(): never
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        redirect($referer);
    }
}

if (!function_exists('old')) {
    /**
     * Retrieve previously-flashed old input value.
     */
    function old(string $key, mixed $default = ''): mixed
    {
        $old = session()->getFlash('_old_input', []);
        return $old[$key] ?? $default;
    }
}

if (!function_exists('errors')) {
    /**
     * Retrieve flashed validation errors.
     *
     * @return array<string, string[]>
     */
    function errors(): array
    {
        return session()->getFlash('_errors', []);
    }
}

if (!function_exists('error')) {
    function error(string $key): ?string
    {
        $errors = errors();
        return $errors[$key][0] ?? null;
    }
}

if (!function_exists('flash')) {
    function flash(string $key, mixed $default = null): mixed
    {
        return session()->getFlash($key, $default);
    }
}

if (!function_exists('str_random')) {
    function str_random(int $length = 32): string
    {
        return bin2hex(random_bytes((int) ceil($length / 2)));
    }
}

if (!function_exists('now')) {
    function now(string $format = 'Y-m-d H:i:s'): string
    {
        return date($format);
    }
}

if (!function_exists('abort')) {
    /**
     * Abort the request with an HTTP error status.
     */
    function abort(int $code, string $message = ''): never
    {
        http_response_code($code);
        $messages = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            419 => 'Page Expired',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            503 => 'Service Unavailable',
        ];
        $title = $messages[$code] ?? 'Error';
        $isServer = $code >= 500;

        // Correlate the friendly page with the private log entry.
        try {
            $reference = 'ERR-' . strtoupper(bin2hex(random_bytes(4)));
        } catch (\Throwable) {
            $reference = 'ERR-' . strtoupper(substr(md5((string) microtime(true)), 0, 8));
        }

        // Server errors: log the technical detail privately; never expose it in
        // production. Client errors: the supplied message is safe to show.
        $debug = (bool) config('app.debug');
        if ($isServer) {
            try {
                logger()->error('[' . $reference . '] ' . $code . ' ' . $title . ($message !== '' ? ' — ' . $message : ''));
            } catch (\Throwable) {
            }
            $shown = $debug && $message !== '' ? $message : 'An unexpected error occurred on our side. Please try again, or contact your administrator with the reference below.';
        } else {
            $shown = $message !== '' ? $message : $title;
        }

        // Try to render a friendly error page; fall back to plain text.
        $genericView = config('paths.views') . '/errors/generic.php';
        if (is_file($genericView)) {
            try {
                echo view('errors.generic', [
                    'code'      => $code,
                    'title'     => $title,
                    'message'   => $shown,
                    'reference' => $isServer ? $reference : null,
                ]);
                exit;
            } catch (\Throwable) {
                // fall through
            }
        }

        header('Content-Type: text/plain; charset=UTF-8');
        echo "{$code} {$title}\n{$shown}" . ($isServer ? "\nReference: {$reference}" : '');
        exit;
    }
}

if (!function_exists('logger')) {
    function logger(): \Core\Support\Logger
    {
        return app(\Core\Support\Logger::class);
    }
}

if (!function_exists('brand_logo_url')) {
    /**
     * Public URL of the configured school logo (also used as the favicon),
     * cache-busted so a replaced logo is picked up. Null when none is set.
     */
    function brand_logo_url(): ?string
    {
        static $cached = false;
        if ($cached !== false) {
            return $cached;
        }
        try {
            $file = \Core\Database\QueryBuilder::table('settings')->where('key', 'school_logo')->value('value');
            $file = is_string($file) ? trim($file) : '';
            if ($file === '') {
                return $cached = null;
            }
            $hex = pathinfo($file, PATHINFO_FILENAME);
            return $cached = url('/media/avatar/' . $hex) . '?v=' . substr(md5($file), 0, 8);
        } catch (\Throwable) {
            return $cached = null;
        }
    }
}

if (!function_exists('slugify')) {
    function slugify(string $text): string
    {
        $text = preg_replace('/[^\pL\d]+/u', '-', $text) ?? $text;
        $text = trim($text, '-');
        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text) ?: $text;
        $text = strtolower($text);
        $text = preg_replace('/[^-\w]+/', '', $text) ?? $text;
        return $text ?: 'n-a';
    }
}

if (!function_exists('money')) {
    function money(float|int|string $amount, string $currency = '₹'): string
    {
        return $currency . number_format((float) $amount, 2);
    }
}

if (!function_exists('dd')) {
    /**
     * Dump and die - debugging helper.
     */
    function dd(mixed ...$vars): never
    {
        header('Content-Type: text/plain; charset=UTF-8');
        foreach ($vars as $var) {
            var_dump($var);
        }
        exit;
    }
}
