<?php

declare(strict_types=1);

namespace Core\Support;

/**
 * Lightweight .env file parser and environment variable accessor.
 * Loads key=value pairs into an internal store and process environment.
 */
final class Env
{
    /** @var array<string, mixed> */
    private static array $variables = [];

    private static bool $loaded = false;

    /**
     * Load and parse a .env file.
     */
    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }

        if (!is_file($path) || !is_readable($path)) {
            self::$loaded = true;
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            self::$loaded = true;
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments and blank lines.
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = self::parseValue(trim($value));

            self::$variables[$name] = $value;

            // Also expose to getenv()/$_ENV for interoperability.
            if (getenv($name) === false) {
                putenv("{$name}={$value}");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }

        self::$loaded = true;
    }

    /**
     * Retrieve an environment value with optional default and type casting.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$variables[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        return $value;
    }

    /**
     * Parse a raw string value: strip quotes, resolve references, cast literals.
     */
    private static function parseValue(string $value): mixed
    {
        // Remove surrounding quotes.
        if (strlen($value) > 1) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        // Interpolate ${VAR} references.
        $value = preg_replace_callback('/\$\{([A-Z0-9_]+)\}/', static function (array $matches): string {
            $ref = self::$variables[$matches[1]] ?? getenv($matches[1]);
            return is_string($ref) ? $ref : '';
        }, $value) ?? $value;

        // Cast common literals.
        return match (strtolower($value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }
}
