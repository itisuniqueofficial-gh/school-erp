<?php

declare(strict_types=1);

namespace Core\Config;

/**
 * Configuration repository. Loads PHP config files from the config directory
 * and provides dot-notation access to nested values.
 */
final class Config
{
    /** @var array<string, mixed> */
    private static array $items = [];

    private static bool $loaded = false;

    /**
     * Load all *.php config files from the given directory.
     * Each file becomes a top-level key matching its filename.
     */
    public static function load(string $configPath): void
    {
        if (self::$loaded) {
            return;
        }

        foreach (glob(rtrim($configPath, '/') . '/*.php') ?: [] as $file) {
            $key = basename($file, '.php');
            $data = require $file;
            if (is_array($data)) {
                self::$items[$key] = $data;
            }
        }

        self::$loaded = true;
    }

    /**
     * Get a config value using dot notation, e.g. Config::get('app.name').
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::$items;

        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }

        return $value;
    }

    /**
     * Set (override) a config value at runtime using dot notation.
     */
    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $ref = &self::$items;

        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $ref[$segment] = $value;
                return;
            }
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }
    }

    /**
     * Check whether a config key exists.
     */
    public static function has(string $key): bool
    {
        return self::get($key, '__MISSING__') !== '__MISSING__';
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return self::$items;
    }
}
