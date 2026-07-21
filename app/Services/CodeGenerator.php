<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Generates identifiers and machine-readable codes.
 *
 * - Sequential codes (admission/employee/invoice/receipt/roll/book).
 * - A self-contained, scannable Code 39 barcode as inline SVG (no dependencies).
 * - QR codes are rendered client-side (see public/assets/js/app.js, data-qr) to
 *   keep the server dependency-free; qrPayload() builds the encoded string.
 */
final class CodeGenerator
{
    /** Code 39 symbol table (each char -> 9-element bar/space widths, b/w alternating). */
    private const CODE39 = [
        '0' => 'nnnwwnwnn', '1' => 'wnnwnnnnw', '2' => 'nnwwnnnnw', '3' => 'wnwwnnnnn',
        '4' => 'nnnwwnnnw', '5' => 'wnnwwnnnn', '6' => 'nnwwwnnnn', '7' => 'nnnwnnwnw',
        '8' => 'wnnwnnwnn', '9' => 'nnwwnnwnn', 'A' => 'wnnnnwnnw', 'B' => 'nnwnnwnnw',
        'C' => 'wnwnnwnnn', 'D' => 'nnnnwwnnw', 'E' => 'wnnnwwnnn', 'F' => 'nnwnwwnnn',
        'G' => 'nnnnnwwnw', 'H' => 'wnnnnwwnn', 'I' => 'nnwnnwwnn', 'J' => 'nnnnwwwnn',
        'K' => 'wnnnnnnww', 'L' => 'nnwnnnnww', 'M' => 'wnwnnnnwn', 'N' => 'nnnnwnnww',
        'O' => 'wnnnwnnwn', 'P' => 'nnwnwnnwn', 'Q' => 'nnnnnnwww', 'R' => 'wnnnnnwwn',
        'S' => 'nnwnnnwwn', 'T' => 'nnnnwnwwn', 'U' => 'wwnnnnnnw', 'V' => 'nwwnnnnnw',
        'W' => 'wwwnnnnnn', 'X' => 'nwnnwnnnw', 'Y' => 'wwnnwnnnn', 'Z' => 'nwwnwnnnn',
        '-' => 'nwnnnnwnw', '.' => 'wwnnnnwnn', ' ' => 'nwwnnnwnn', '*' => 'nwnnwnwnn',
    ];

    public static function admissionNo(int $count): string
    {
        return sprintf('ADM-%s-%04d', date('Y'), $count + 1);
    }

    public static function employeeCode(int $count): string
    {
        return sprintf('EMP-%04d', $count + 1);
    }

    public static function invoiceNo(int $count): string
    {
        return sprintf('INV-%s-%05d', date('Y'), $count + 1);
    }

    public static function receiptNo(int $count): string
    {
        return sprintf('RCPT-%s-%05d', date('Y'), $count + 1);
    }

    public static function rollNo(int $classSequence): string
    {
        return (string) ($classSequence + 1);
    }

    public static function bookCode(int $count): string
    {
        return sprintf('LIB-%06d', $count + 1);
    }

    /**
     * Build a scannable Code 39 barcode as an inline SVG string.
     */
    public static function barcodeSvg(string $data, int $height = 60, int $narrow = 2): string
    {
        $data = strtoupper(preg_replace('/[^0-9A-Z\-\. ]/', '', $data) ?? '');
        $encoded = '*' . $data . '*';
        $wide = $narrow * 3;
        $x = 0;
        $bars = '';

        foreach (str_split($encoded) as $char) {
            $pattern = self::CODE39[$char] ?? self::CODE39['*'];
            for ($i = 0; $i < 9; $i++) {
                $w = $pattern[$i] === 'w' ? $wide : $narrow;
                // Even index = bar (black), odd = space (white).
                if ($i % 2 === 0) {
                    $bars .= '<rect x="' . $x . '" y="0" width="' . $w . '" height="' . $height . '"/>';
                }
                $x += $w;
            }
            $x += $narrow; // inter-character gap
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $x . '" height="' . ($height + 16)
            . '" viewBox="0 0 ' . $x . ' ' . ($height + 16) . '" fill="#111827" role="img" aria-label="Barcode ' . e($data) . '">'
            . $bars
            . '<text x="' . ($x / 2) . '" y="' . ($height + 13) . '" text-anchor="middle" font-family="monospace" font-size="11" fill="#111827">' . e($data) . '</text>'
            . '</svg>';
    }

    /**
     * Payload string for a client-side QR code (rendered by app.js).
     */
    public static function qrPayload(string $type, string|int $id, array $extra = []): string
    {
        $payload = array_merge(['t' => $type, 'id' => (string) $id], $extra);
        return json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '';
    }
}
