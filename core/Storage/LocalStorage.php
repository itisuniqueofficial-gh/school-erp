<?php

declare(strict_types=1);

namespace Core\Storage;

/** Files on the local filesystem under one root (ideally outside the web root; see STORAGE_PATH). */
final class LocalStorage implements StorageDriver
{
    private string $root;

    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/\\');
    }

    public function name(): string
    {
        return 'local';
    }

    public function put(string $key, string $contents, ?string $mime = null): void
    {
        $path = $this->path($key);
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Could not create the storage directory.');
        }
        $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));
        if (file_put_contents($tmp, $contents, LOCK_EX) === false || !@rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException('Could not write the file.');
        }
        @chmod($path, 0640);
    }

    public function get(string $key): string
    {
        $path = $this->path($key);
        $data = is_file($path) ? file_get_contents($path) : false;
        if ($data === false) {
            throw new \RuntimeException('File not found.');
        }
        return $data;
    }

    public function exists(string $key): bool
    {
        return is_file($this->path($key));
    }

    public function delete(string $key): void
    {
        $path = $this->path($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function path(string $key): string
    {
        return $this->root . '/' . self::cleanKey($key);
    }

    /** Normalise a key and refuse traversal / absolute paths / NUL bytes. */
    public static function cleanKey(string $key): string
    {
        $key = str_replace('\\', '/', $key);
        if ($key === '' || str_contains($key, "\0") || str_starts_with($key, '/') || preg_match('#(^|/)\.\.(/|$)#', $key) === 1) {
            throw new \InvalidArgumentException('Invalid storage key.');
        }
        return $key;
    }
}
