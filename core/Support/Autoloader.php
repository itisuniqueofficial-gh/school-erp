<?php

declare(strict_types=1);

namespace Core\Support;

/**
 * PSR-4 compliant autoloader used when Composer's autoloader is unavailable.
 * Allows the application to run on shared hosting without `composer install`.
 */
final class Autoloader
{
    /** @var array<string, string> namespace prefix => base directory */
    private array $prefixes = [];

    /** @var string[] files to require eagerly */
    private array $files = [];

    public function register(): void
    {
        spl_autoload_register([$this, 'loadClass']);

        foreach ($this->files as $file) {
            if (is_file($file)) {
                require_once $file;
            }
        }
    }

    /**
     * Map a namespace prefix to a base directory.
     */
    public function addNamespace(string $prefix, string $baseDir): void
    {
        $prefix = trim($prefix, '\\') . '\\';
        $baseDir = rtrim($baseDir, '/') . '/';
        $this->prefixes[$prefix] = $baseDir;
    }

    /**
     * Register a file to be required eagerly (e.g. helper functions).
     */
    public function addFile(string $file): void
    {
        $this->files[] = $file;
    }

    /**
     * Attempt to load the class file for a fully-qualified class name.
     */
    public function loadClass(string $class): bool
    {
        foreach ($this->prefixes as $prefix => $baseDir) {
            if (str_starts_with($class, $prefix)) {
                $relative = substr($class, strlen($prefix));
                $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
                if (is_file($file)) {
                    require_once $file;
                    return true;
                }
            }
        }

        return false;
    }
}
