<?php

declare(strict_types=1);

namespace Core\Support;

use InvalidArgumentException;
use RuntimeException;

/**
 * Safe read/update helper for .env files.
 *
 * Updates are performed on a temporary file in the same directory and then
 * atomically renamed into place. When the file already exists, a timestamped
 * backup is created before the replacement so a failed installation can be
 * rolled back without losing the previous configuration.
 */
final class EnvFile
{
    /**
     * Read key/value pairs from a .env file without mutating process state.
     *
     * @return array<string, string>
     */
    public static function read(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $values = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            $values[$name] = self::decode(trim($value));
        }

        return $values;
    }

    /**
     * Update the given keys, creating the file from a template when absent.
     *
     * @param array<string, string> $values
     * @return array{path:string,backup:?string,created:bool}
     */
    public static function update(string $path, array $values, ?string $template = null): array
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            throw new RuntimeException('The directory for the environment file does not exist.');
        }

        $existed = is_file($path);
        $content = $existed
            ? (string) file_get_contents($path)
            : ($template !== null && is_file($template) ? (string) file_get_contents($template) : '');

        if ($content !== '' && !str_ends_with($content, "\n")) {
            $content .= "\n";
        }

        foreach ($values as $key => $value) {
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) !== 1) {
                throw new InvalidArgumentException('Invalid environment key.');
            }

            $line = $key . '=' . self::encode((string) $value);
            $pattern = '/^[ \t]*' . preg_quote($key, '/') . '[ \t]*=.*$/m';

            if (preg_match($pattern, $content) === 1) {
                $content = (string) preg_replace_callback(
                    $pattern,
                    static fn (): string => $line,
                    $content,
                    1
                );
            } else {
                if ($content !== '' && !str_ends_with($content, "\n")) {
                    $content .= "\n";
                }
                $content .= $line . "\n";
            }
        }

        $backup = null;
        $mode = $existed ? (fileperms($path) & 0777) : 0600;

        if ($existed) {
            $backup = $path . '.bak.' . date('YmdHis') . '.' . bin2hex(random_bytes(3));
            if (!copy($path, $backup)) {
                throw new RuntimeException('Could not create a backup of the existing environment file.');
            }
            @chmod($backup, $mode);
        }

        $temp = $path . '.tmp.' . bin2hex(random_bytes(6));
        if (file_put_contents($temp, $content, LOCK_EX) === false) {
            @unlink($temp);
            throw new RuntimeException('Could not write the environment file.');
        }
        @chmod($temp, $mode);

        if (!@rename($temp, $path)) {
            @unlink($temp);
            throw new RuntimeException('Could not move the new environment file into place.');
        }

        return ['path' => $path, 'backup' => $backup, 'created' => !$existed];
    }

    /**
     * Restore the file from a backup. When no backup exists the file is
     * removed, returning the installation to its original "no .env" state.
     */
    public static function restore(string $path, ?string $backup): void
    {
        if ($backup !== null && is_file($backup)) {
            if (!copy($backup, $path)) {
                throw new RuntimeException('Could not restore the previous environment file.');
            }
            @chmod($path, 0600);
            return;
        }

        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Decode a value using the same quoting rules as Env::parseValue().
     */
    public static function decode(string $value): string
    {
        if (strlen($value) < 2) {
            return $value;
        }

        $first = $value[0];
        $last = $value[strlen($value) - 1];
        if (($first !== '"' || $last !== '"') && ($first !== "'" || $last !== "'")) {
            return $value;
        }

        $value = substr($value, 1, -1);
        if ($first === '"') {
            $value = strtr($value, ['\\\\' => '\\', '\\"' => '"', '\\$' => '$']);
        }

        return $value;
    }

    /**
     * Encode a value so EnvFile::read()/Env::load() returns it unchanged.
     * Newlines are rejected because they would break the line-based format.
     */
    public static function encode(string $value): string
    {
        if (str_contains($value, "\n") || str_contains($value, "\r")) {
            throw new InvalidArgumentException('Environment values cannot contain newlines.');
        }

        if ($value === '') {
            return '';
        }

        // Unquoted values are safe when they contain no whitespace, quotes,
        // hash, dollar sign, or backslash. Keep the common case readable.
        if (preg_match('/^[A-Za-z0-9_.,:@\/+\-=~!*()\[\]{}|<>?;%&^]+$/', $value) === 1) {
            return $value;
        }

        return '"' . strtr($value, ['\\' => '\\\\', '"' => '\\"', '$' => '\\$']) . '"';
    }
}
