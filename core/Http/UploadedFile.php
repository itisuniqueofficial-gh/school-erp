<?php

declare(strict_types=1);

namespace Core\Http;

use RuntimeException;

/**
 * Represents a single uploaded file with validation and secure move helpers.
 */
final class UploadedFile
{
    public function __construct(
        /** @var array{name:string,type:string,tmp_name:string,error:int,size:int} */
        private readonly array $file
    ) {
    }

    public function isValid(): bool
    {
        return ($this->file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
            && is_uploaded_file($this->file['tmp_name']);
    }

    public function originalName(): string
    {
        return basename((string) ($this->file['name'] ?? ''));
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->originalName(), PATHINFO_EXTENSION));
    }

    public function size(): int
    {
        return (int) ($this->file['size'] ?? 0);
    }

    /** Absolute path to the uploaded temp file (for server-side processing). */
    public function path(): string
    {
        return (string) ($this->file['tmp_name'] ?? '');
    }

    /**
     * Detect the real MIME type from file contents (not the client-supplied type).
     */
    public function mimeType(): string
    {
        if (!is_file($this->file['tmp_name'])) {
            return 'application/octet-stream';
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return (string) ($this->file['type'] ?? 'application/octet-stream');
        }
        $mime = finfo_file($finfo, $this->file['tmp_name']) ?: 'application/octet-stream';
        finfo_close($finfo);
        return $mime;
    }

    /**
     * Generate a collision-resistant hashed filename preserving the extension.
     */
    public function hashName(): string
    {
        $ext = $this->extension();
        return bin2hex(random_bytes(16)) . ($ext ? '.' . $ext : '');
    }

    /**
     * Move the uploaded file to a destination directory using a safe filename.
     *
     * @return string The stored filename.
     */
    public function store(string $directory, ?string $filename = null): string
    {
        if (!$this->isValid()) {
            throw new RuntimeException('Cannot store an invalid uploaded file.');
        }

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException("Failed to create upload directory: {$directory}");
        }

        $filename ??= $this->hashName();
        $filename = basename($filename); // prevent path traversal
        $destination = rtrim($directory, '/') . '/' . $filename;

        if (!move_uploaded_file($this->file['tmp_name'], $destination)) {
            throw new RuntimeException('Failed to move uploaded file.');
        }

        @chmod($destination, 0644);
        return $filename;
    }
}
