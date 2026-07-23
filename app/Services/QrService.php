<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Server-side QR PNG generator (bundled phpqrcode, no external dependency).
 * Produces a cached PNG file whose path can be embedded into FPDF documents so
 * the QR is part of the single source of truth (identical in preview and PDF).
 */
final class QrService
{
    /** Generate (and cache) a QR PNG for the given text; returns the file path or null. */
    public function pngPath(string $text): ?string
    {
        $dir = dirname(__DIR__, 2) . '/storage/tmp';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/qr_' . md5($text) . '.png';
        if (is_file($file) && filesize($file) > 0) {
            return $file;
        }
        $prev = error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING & ~E_STRICT);
        try {
            if (!class_exists('QRcode')) {
                $lib = dirname(__DIR__, 2) . '/lib/phpqrcode/phpqrcode.php';
                if (is_file($lib)) {
                    require_once $lib;
                }
            }
            if (!class_exists('QRcode')) {
                return null;
            }
            \QRcode::png($text, $file, QR_ECLEVEL_M, 4, 2);
        } catch (\Throwable) {
            $file = null;
        } finally {
            error_reporting($prev);
        }
        return ($file !== null && is_file($file) && filesize($file) > 0) ? $file : null;
    }
}
