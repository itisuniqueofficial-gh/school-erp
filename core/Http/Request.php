<?php

declare(strict_types=1);

namespace Core\Http;

/**
 * Encapsulates the incoming HTTP request: method, URI, query, body, files, headers.
 */
final class Request
{
    /** @var array<string, mixed> */
    private array $query;

    /** @var array<string, mixed> */
    private array $body;

    /** @var array<string, mixed> */
    private array $files;

    /** @var array<string, string> */
    private array $server;

    /** @var array<string, string> */
    private array $cookies;

    /** @var array<string, mixed>|null */
    private ?array $json = null;

    public function __construct()
    {
        $this->query   = $_GET;
        $this->body    = $_POST;
        $this->files   = $_FILES;
        $this->server  = $_SERVER;
        $this->cookies = $_COOKIE;
        $this->parseJson();
    }

    public static function capture(): self
    {
        return new self();
    }

    private function parseJson(): void
    {
        $contentType = $this->header('Content-Type', '');
        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input') ?: '';
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $this->json = $decoded;
                $this->body = array_merge($this->body, $decoded);
            }
        }
    }

    public function method(): string
    {
        $method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
        // Support method spoofing via _method field for HTML forms.
        if ($method === 'POST' && isset($this->body['_method'])) {
            $spoofed = strtoupper((string) $this->body['_method']);
            if (in_array($spoofed, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $spoofed;
            }
        }
        return $method;
    }

    public function uri(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $uri = parse_url($uri, PHP_URL_PATH) ?: '/';
        return '/' . trim($uri, '/');
    }

    public function path(): string
    {
        return $this->uri();
    }

    public function isMethod(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }

    public function ajax(): bool
    {
        return strtolower($this->header('X-Requested-With', '')) === 'xmlhttprequest';
    }

    public function expectsJson(): bool
    {
        $accept = $this->header('Accept', '');
        return $this->ajax()
            || str_contains($accept, 'application/json')
            || str_starts_with($this->uri(), '/api/');
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /**
     * @param string[] $keys
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->input($key);
        }
        return $result;
    }

    /**
     * @param string[] $except
     * @return array<string, mixed>
     */
    public function except(array $except): array
    {
        return array_diff_key($this->all(), array_flip($except));
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function has(string $key): bool
    {
        return isset($this->body[$key]) || isset($this->query[$key]);
    }

    public function filled(string $key): bool
    {
        $value = $this->input($key);
        return $value !== null && $value !== '';
    }

    public function boolean(string $key): bool
    {
        return filter_var($this->input($key), FILTER_VALIDATE_BOOLEAN);
    }

    public function integer(string $key, int $default = 0): int
    {
        $value = $this->input($key);
        return is_numeric($value) ? (int) $value : $default;
    }

    public function file(string $key): ?UploadedFile
    {
        if (!isset($this->files[$key]) || ($this->files[$key]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return new UploadedFile($this->files[$key]);
    }

    public function hasFile(string $key): bool
    {
        return $this->file($key) !== null;
    }

    public function header(string $key, string $default = ''): string
    {
        $normalized = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        if (isset($this->server[$normalized])) {
            return (string) $this->server[$normalized];
        }
        // Content-Type and Content-Length are not prefixed with HTTP_.
        $direct = strtoupper(str_replace('-', '_', $key));
        return (string) ($this->server[$direct] ?? $default);
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization', '');
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }
        return null;
    }

    public function cookie(string $key, ?string $default = null): ?string
    {
        return $this->cookies[$key] ?? $default;
    }

    public function ip(): string
    {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            if (!empty($this->server[$key])) {
                $ip = explode(',', (string) $this->server[$key])[0];
                return trim($ip);
            }
        }
        return '0.0.0.0';
    }

    public function userAgent(): string
    {
        return (string) ($this->server['HTTP_USER_AGENT'] ?? 'unknown');
    }

    public function isSecure(): bool
    {
        return (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off')
            || ($this->server['SERVER_PORT'] ?? null) == 443
            || strtolower($this->header('X-Forwarded-Proto', '')) === 'https';
    }
}
