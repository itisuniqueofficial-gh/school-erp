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
    /** Override or set a variable at runtime (used e.g. for test-database isolation). */
    public static function set(string $key, mixed $value): void
    {
        self::$variables[$key] = $value;
    }

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
        $doubleQuoted = false;

        // Remove surrounding quotes.
        if (strlen($value) > 1) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
                $doubleQuoted = $first === '"';
            }
        }

        // Interpolate ${VAR} references. Escaped \${...} is left literal and
        // unescaped below, allowing values (e.g. passwords) to contain "${".
        $value = preg_replace_callback('/(?<!\\\\)\$\{([A-Z0-9_]+)\}/', static function (array $matches): string {
            $ref = self::$variables[$matches[1]] ?? getenv($matches[1]);
            return is_string($ref) ? $ref : '';
        }, $value) ?? $value;

        if ($doubleQuoted) {
            $value = strtr($value, ['\\\\' => '\\', '\\"' => '"', '\\$' => '$']);
        }

        // Cast common literals.
        return match (strtolower($value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }

    /**
     * Parse DATABASE_URL if present and set individual DB env vars.
     */
    public static function parseDatabaseUrl(): void
    {
        $url = getenv('DATABASE_URL') ?: (self::$variables['DATABASE_URL'] ?? null);
        if (!is_string($url) || trim($url) === '') {
            return;
        }
        try {
            $values = \Core\Database\DatabaseConfig::fromUrl($url)->toEnv();
        } catch (\Throwable) {
            return; // invalid URL: ignored here; the installer / health check report it without echoing it
        }
        $values['DB_DRIVER'] = $values['DB_CONNECTION'];
        // The URL is consumed here. Leaving it set would let DatabaseConfig::fromEnv() re-read it and
        // bypass later adjustments of DB_DATABASE (notably the APP_TESTING "<db>_test" isolation).
        $values['DATABASE_URL'] = '';
        foreach ($values as $k => $v) {
            self::$variables[$k] = $v;
            $_ENV[$k] = $v;
            if ($k !== 'DATABASE_URL') {
                putenv($k . '=' . $v);   // leave the original DATABASE_URL in the process environment for child processes
            }
        }
    }

}
