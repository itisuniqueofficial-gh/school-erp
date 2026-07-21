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

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = rtrim((string) config('app.url'), '/');
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        $base = rtrim((string) config('app.url'), '/');
        return $base . '/' . ltrim($path, '/');
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
        ];
        $title = $messages[$code] ?? 'Error';
        $message = $message ?: $title;

        // Try to render a friendly error page; fall back to plain text.
        $genericView = config('paths.views') . '/errors/generic.php';
        if (is_file($genericView)) {
            try {
                echo view('errors.generic', [
                    'code'    => $code,
                    'title'   => $title,
                    'message' => $message,
                ]);
                exit;
            } catch (\Throwable) {
                // fall through
            }
        }

        header('Content-Type: text/plain; charset=UTF-8');
        echo "{$code} {$title}\n{$message}";
        exit;
    }
}

if (!function_exists('logger')) {
    function logger(): \Core\Support\Logger
    {
        return app(\Core\Support\Logger::class);
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
