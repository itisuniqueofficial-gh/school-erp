<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Centralized, reusable upload validator used across the ERP.
 *
 * Defence in depth for every upload:
 *   1. Size limit (per category, overridable).
 *   2. Real MIME type read from the file CONTENT via finfo (never the client
 *      header or extension).
 *   3. The declared extension must be allowed for the category.
 *   4. Magic-byte signature must match the detected MIME (catches renamed /
 *      spoofed files).
 *   5. Executable / polyglot rejection: any file whose bytes contain PHP or
 *      script markers (`<?php`, `<?=`, `<script`) is refused, and dangerous
 *      extensions (php, exe, sh, …) are always refused.
 *
 * This never trusts the client. It returns a structured result so callers can
 * surface a plain-language message.
 */
final class FileValidator
{
    /** @var array<string,array{ext:array<int,string>,mime:array<int,string>,max:int}> */
    public const CATEGORIES = [
        'image'    => [
            'ext'  => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
            'mime' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
            'max'  => 2097152, // 2 MB
        ],
        'document' => [
            'ext'  => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'ppt', 'pptx'],
            'mime' => [
                'application/pdf', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/plain', 'text/csv', 'application/zip', // docx/xlsx/pptx report as zip on some systems
            ],
            'max'  => 10485760, // 10 MB
        ],
        'archive'  => [
            'ext'  => ['zip'],
            'mime' => ['application/zip'],
            'max'  => 20971520, // 20 MB
        ],
    ];

    /** Extensions that must never be accepted, regardless of category. */
    private const DANGEROUS_EXT = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'exe', 'dll', 'sh', 'bat',
        'cmd', 'com', 'cgi', 'pl', 'py', 'rb', 'js', 'mjs', 'jsp', 'asp', 'aspx',
        'html', 'htm', 'htaccess', 'svg',
    ];

    /**
     * Inspect a file on disk.
     *
     * @return array{ok:bool,reason:string,mime:?string,ext:?string}
     */
    public function inspect(string $path, string $category, ?string $originalName = null, ?int $maxBytes = null): array
    {
        $rules = self::CATEGORIES[$category] ?? null;
        if ($rules === null) {
            return $this->fail('Unknown upload category.');
        }
        if ($path === '' || !is_file($path)) {
            return $this->fail('The uploaded file could not be read. Please try again.');
        }

        $size = (int) filesize($path);
        $max = $maxBytes ?? $rules['max'];
        if ($size <= 0) {
            return $this->fail('The file is empty.');
        }
        if ($size > $max) {
            return $this->fail('File size is ' . $this->human($size) . '. Maximum allowed size is ' . $this->human($max) . '.');
        }

        // Declared extension (from the original client name) must be allowed and
        // never dangerous.
        $ext = strtolower((string) pathinfo((string) ($originalName ?? $path), PATHINFO_EXTENSION));
        if ($ext !== '' && in_array($ext, self::DANGEROUS_EXT, true)) {
            return $this->fail('This file type is not allowed for security reasons.');
        }
        if ($ext !== '' && !in_array($ext, $rules['ext'], true)) {
            return $this->fail('Unsupported file type ".' . $ext . '". Allowed: ' . implode(', ', $rules['ext']) . '.');
        }

        // Real MIME from content.
        $mime = $this->detectMime($path);
        if ($mime === null || !in_array($mime, $rules['mime'], true)) {
            return $this->fail('The file content is not a supported ' . $category . '.');
        }

        // Magic-byte signature must be consistent with the detected MIME.
        if (!$this->signatureMatches($path, $mime)) {
            return $this->fail('The file appears to be corrupted or its type does not match its contents.');
        }

        // Executable / polyglot content rejection.
        if ($this->containsExecutableMarkers($path)) {
            return $this->fail('The file contains disallowed embedded code and was rejected.');
        }

        return ['ok' => true, 'reason' => '', 'mime' => $mime, 'ext' => $ext ?: null];
    }

    private function detectMime(string $path): ?string
    {
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            if ($fi !== false) {
                $m = finfo_file($fi, $path);
                finfo_close($fi);
                return $m !== false ? strtolower((string) $m) : null;
            }
        }
        return null;
    }

    private function signatureMatches(string $path, string $mime): bool
    {
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return false;
        }
        $head = (string) fread($fh, 16);
        fclose($fh);

        return match ($mime) {
            'image/jpeg' => str_starts_with($head, "\xFF\xD8\xFF"),
            'image/png'  => str_starts_with($head, "\x89PNG\r\n\x1a\n"),
            'image/gif'  => str_starts_with($head, 'GIF87a') || str_starts_with($head, 'GIF89a'),
            'image/webp' => str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP',
            'application/pdf' => str_starts_with($head, '%PDF-'),
            'application/zip',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation'
                => str_starts_with($head, "PK\x03\x04") || str_starts_with($head, "PK\x05\x06"),
            // Legacy Office + plain text have no reliable signature — accept.
            default => true,
        };
    }

    /** Scan the first chunk for PHP/script markers (polyglot protection). */
    private function containsExecutableMarkers(string $path): bool
    {
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return true; // fail closed
        }
        $chunk = (string) fread($fh, 8192);
        fclose($fh);
        $lower = strtolower($chunk);
        foreach (['<?php', '<?=', '<script', '#!/bin/', '#!/usr/bin'] as $marker) {
            if (str_contains($lower, $marker)) {
                return true;
            }
        }
        return false;
    }

    private function human(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }

    /** @return array{ok:false,reason:string,mime:null,ext:null} */
    private function fail(string $reason): array
    {
        return ['ok' => false, 'reason' => $reason, 'mime' => null, 'ext' => null];
    }
}
