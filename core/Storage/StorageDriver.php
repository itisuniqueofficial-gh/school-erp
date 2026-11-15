<?php

declare(strict_types=1);

namespace Core\Storage;

/**
 * Where uploaded files and generated documents live. Keys are relative,
 * slash-separated paths such as "documents/12/ab34.pdf". Implementations must
 * reject keys that try to leave their root.
 */
interface StorageDriver
{
    public function name(): string;

    public function put(string $key, string $contents, ?string $mime = null): void;

    /** @throws \RuntimeException when the object is missing */
    public function get(string $key): string;

    public function exists(string $key): bool;

    /** Deleting a missing object is not an error. */
    public function delete(string $key): void;
}
