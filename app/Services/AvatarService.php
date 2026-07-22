<?php

declare(strict_types=1);

namespace App\Services;

use Core\Http\UploadedFile;

/**
 * Secure profile-picture pipeline.
 *
 * Security model:
 *  - The real MIME type is read from the file contents (finfo), never trusting
 *    the client-supplied type or extension.
 *  - Only jpeg / png / webp are accepted, and the bytes must actually decode as
 *    an image via GD (`imagecreatefromstring`) — anything else is rejected.
 *  - The image is **re-encoded** from scratch (never the original bytes are
 *    stored), which strips EXIF payloads, PHP polyglots, and any appended
 *    executable content. The stored file therefore cannot contain runnable code.
 *  - Files are written outside the web root (storage/uploads/avatars) with a
 *    random hex name and served only through an authenticated controller.
 */
final class AvatarService
{
    private const MAX_DIM   = 512; // px, largest edge of the main image
    private const THUMB_DIM = 160; // px, largest edge of the thumbnail

    /** Real MIME => output extension. */
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    private string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = rtrim($dir ?? (dirname(__DIR__, 2) . '/storage/uploads/avatars'), '/');
    }

    /**
     * Validate + re-encode an uploaded image into a main file and a thumbnail.
     *
     * @return array{ok:bool,message:string,filename:?string}
     */
    public function store(UploadedFile $file): array
    {
        $mime = $file->mimeType();
        if (!isset(self::ALLOWED[$mime])) {
            return ['ok' => false, 'message' => 'Only JPG, PNG or WebP images are allowed.', 'filename' => null];
        }
        $maxSize = (int) config('app.upload_max_size', 5242880);
        if ($file->size() <= 0 || $file->size() > $maxSize) {
            return ['ok' => false, 'message' => 'Image must be between 1 byte and ' . (int) ($maxSize / 1048576) . ' MB.', 'filename' => null];
        }

        $tmp = $file->path();
        if ($tmp === '' || !is_file($tmp)) {
            return ['ok' => false, 'message' => 'Upload failed. Please try again.', 'filename' => null];
        }

        $raw = (string) file_get_contents($tmp);
        $src = @imagecreatefromstring($raw);
        if ($src === false) {
            return ['ok' => false, 'message' => 'The file is not a valid image.', 'filename' => null];
        }

        if (!is_dir($this->dir) && !mkdir($this->dir, 0775, true) && !is_dir($this->dir)) {
            imagedestroy($src);
            return ['ok' => false, 'message' => 'Storage is not writable.', 'filename' => null];
        }

        $ext      = self::ALLOWED[$mime];
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;

        $ok = $this->encode($this->resample($src, self::MAX_DIM), $ext, $this->dir . '/' . $filename)
            && $this->encode($this->resample($src, self::THUMB_DIM), $ext, $this->dir . '/thumb_' . $filename);
        imagedestroy($src);

        if (!$ok) {
            @unlink($this->dir . '/' . $filename);
            @unlink($this->dir . '/thumb_' . $filename);
            return ['ok' => false, 'message' => 'Could not process the image.', 'filename' => null];
        }

        return ['ok' => true, 'message' => 'Profile picture updated.', 'filename' => $filename];
    }

    /** Delete a stored avatar and its thumbnail (safe no-op for null/blank). */
    public function delete(?string $filename): void
    {
        $hex = $this->hexId($filename);
        if ($hex === null) {
            return;
        }
        foreach (['jpg', 'png', 'webp'] as $ext) {
            @unlink($this->dir . '/' . $hex . '.' . $ext);
            @unlink($this->dir . '/thumb_' . $hex . '.' . $ext);
        }
    }

    /**
     * Resolve the absolute path of a stored avatar (or its thumbnail). Accepts
     * either the bare 32-hex id or the full "hex.ext" filename. Returns null for
     * invalid ids, traversal attempts, or missing files.
     */
    public function path(?string $filename, bool $thumb = false): ?string
    {
        $hex = $this->hexId($filename);
        if ($hex === null) {
            return null;
        }
        $prefix = $thumb ? 'thumb_' : '';
        foreach (['jpg', 'png', 'webp'] as $ext) {
            $p = $this->dir . '/' . $prefix . $hex . '.' . $ext;
            if (is_file($p)) {
                return $p;
            }
        }
        return null;
    }

    /** MIME type for serving, derived from a stored filename or path extension. */
    public function mime(string $filename): string
    {
        return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'webp'        => 'image/webp',
            default       => 'application/octet-stream',
        };
    }

    // -- internals ----------------------------------------------------------

    /**
     * Extract and validate the 32-hex id from a filename or bare id.
     * Rejects path traversal and any unexpected shape.
     */
    private function hexId(?string $filename): ?string
    {
        if ($filename === null || $filename === '') {
            return null;
        }
        $base = basename($filename);
        if ($base !== $filename) {
            return null; // contained a path separator
        }
        return preg_match('/^([a-f0-9]{32})(\.(jpg|png|webp))?$/', $base, $m) === 1 ? $m[1] : null;
    }

    /** @param \GdImage $src */
    private function resample(\GdImage $src, int $max): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1.0, $max / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $dst = imagecreatetruecolor($nw, $nh);
        // Preserve transparency for png/webp.
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $nw, $nh, $transparent);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        return $dst;
    }

    /** @param \GdImage $img */
    private function encode(\GdImage $img, string $ext, string $path): bool
    {
        $encoder = ['jpg' => 'imagejpeg', 'png' => 'imagepng', 'webp' => 'imagewebp'][$ext] ?? null;
        if ($encoder === null || !function_exists($encoder)) {
            imagedestroy($img);
            return false; // this GD build cannot write the requested format
        }
        $ok = match ($ext) {
            'jpg'  => imagejpeg($img, $path, 85),
            'png'  => imagepng($img, $path, 6),
            'webp' => imagewebp($img, $path, 85),
            default => false,
        };
        imagedestroy($img);
        if ($ok) {
            @chmod($path, 0644);
        }
        return $ok;
    }
}
