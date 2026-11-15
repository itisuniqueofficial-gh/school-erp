<?php

declare(strict_types=1);

namespace Core\Storage;

use Core\Support\Env;

/**
 * Chooses the storage driver from configuration.
 *   FILESYSTEM_DISK=local (default) | s3        (UPLOAD_DISK is accepted as an alias)
 *   STORAGE_PATH=/home/user/erp-storage        local root; keep it outside the web root
 *   S3_ENDPOINT, S3_REGION, S3_BUCKET, S3_KEY, S3_SECRET, S3_PATH_STYLE, S3_PREFIX   (Cloudflare R2: region "auto")
 */
final class Storage
{
    /** @var array<string,StorageDriver> */
    private static array $drivers = [];

    public static function disk(?string $name = null): StorageDriver
    {
        $name = $name ?? (string) Env::get('FILESYSTEM_DISK', Env::get('UPLOAD_DISK', 'local'));
        $name = in_array($name, ['s3', 'r2'], true) ? 's3' : 'local';
        return self::$drivers[$name] ??= $name === 's3' ? self::s3() : new LocalStorage(self::localRoot());
    }

    public static function localRoot(): string
    {
        return rtrim((string) config('paths.uploads'), '/');
    }

    public static function forget(): void
    {
        self::$drivers = [];
    }

    private static function s3(): S3Storage
    {
        $g = static fn (string $k, string $d = ''): string => (string) Env::get($k, $d);
        $endpoint = $g('S3_ENDPOINT');
        return new S3Storage(
            $endpoint !== '' ? $endpoint : 'https://s3.' . $g('S3_REGION', 'us-east-1') . '.amazonaws.com',
            $g('S3_REGION', 'us-east-1'), $g('S3_BUCKET'), $g('S3_KEY'), $g('S3_SECRET'),
            !in_array(strtolower($g('S3_PATH_STYLE', 'true')), ['0', 'false', 'no', 'off'], true), $g('S3_PREFIX'),
            !in_array(strtolower($g('S3_VERIFY_TLS', 'true')), ['0', 'false', 'no', 'off'], true)
        );
    }
}
