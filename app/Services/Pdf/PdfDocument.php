<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Services\QrService;

require_once dirname(__DIR__, 3) . '/lib/fpdf/fpdf.php';

/**
 * Shared official-document engine. One FPDF subclass provides the SAME
 * professional header, title, verification band, signature/stamp blocks,
 * typography and brand colour to every document type (certificates, report
 * cards, marksheets, …) so they share a single, consistent design language.
 *
 * Layout constants are A4 portrait with 15mm side margins.
 */
final class PdfDocument extends \FPDF
{
    public const LEFT = 15.0;
    public const RIGHT = 195.0;
    public const WIDTH = 180.0;

    /** @var array{0:int,1:int,2:int} */
    private array $accent = [30, 64, 120];

    /** Set the brand accent colour (used for headers, title, footer). */
    public function setAccent(?string $hex): void
    {
        $rgb = self::hexToRgb((string) $hex);
        if ($rgb !== null) {
            $this->accent = $rgb;
        }
    }

    public function accentColor(): void
    {
        $this->SetTextColor($this->accent[0], $this->accent[1], $this->accent[2]);
    }

    public function accentDraw(): void
    {
        $this->SetDrawColor($this->accent[0], $this->accent[1], $this->accent[2]);
    }

    /** Sanitise UTF-8 to the Latin-1 range the core fonts support. */
    public function tx(string $s): string
    {
        $out = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $s);
        return $out !== false ? $out : $s;
    }

    /**
     * Official school header: name, contact line, board/affiliation/udise line,
     * then a divider. Leaves the cursor just below the divider.
     *
     * @param array<string,mixed> $b branding
     */
    public function officialHeader(array $b, float $topY = 23.0): void
    {
        // Logo centered above the school name, aspect-ratio preserved (never
        // stretched), width-capped, so the header reads as one balanced unit.
        $logo = (string) ($b['logo'] ?? '');
        $y = $topY;
        if ($logo !== '' && is_file($logo)) {
            $size = @getimagesize($logo);
            if (is_array($size) && (int) $size[1] > 0) {
                $h = 16.0;
                $w = $h * ((int) $size[0] / (int) $size[1]);
                if ($w > 45.0) { $w = 45.0; $h = $w * ((int) $size[1] / (int) $size[0]); }
                try {
                    $this->Image($logo, (210 - $w) / 2, $y, $w, $h);
                    $y += $h + 3.0;
                } catch (\Throwable) {}
            }
        }
        $this->SetY($y);
        $this->SetFont('Helvetica', 'B', 19);
        $this->accentColor();
        $this->Cell(0, 9, $this->tx((string) ($b['name'] ?? 'School')), 0, 1, 'C');

        $this->SetFont('Helvetica', '', 9.5);
        $this->SetTextColor(75, 75, 75);
        $meta = array_filter([$b['address'] ?? '', $b['phone'] ?? '', $b['email'] ?? '', $b['website'] ?? '']);
        if ($meta !== []) {
            $this->Cell(0, 5, $this->tx(implode('   |   ', $meta)), 0, 1, 'C');
        }
        $extra = array_filter([
            !empty($b['board']) ? 'Board: ' . $b['board'] : '',
            !empty($b['affiliation']) ? 'Affiliation No: ' . $b['affiliation'] : '',
            !empty($b['udise']) ? 'UDISE: ' . $b['udise'] : '',
        ]);
        if ($extra !== []) {
            $this->Cell(0, 5, $this->tx(implode('   |   ', $extra)), 0, 1, 'C');
        }

        $this->Ln(3);
        $y = $this->GetY();
        $this->SetDrawColor(200, 200, 200);
        $this->SetLineWidth(0.3);
        $this->Line(self::LEFT, $y, self::RIGHT, $y);
    }

    /** Centered document title with a width-sized underline. */
    public function centeredTitle(string $title, float $gapBefore = 6.0): void
    {
        $this->Ln($gapBefore);
        $this->SetFont('Helvetica', 'B', 17);
        $this->SetTextColor(20, 20, 20);
        $t = $this->tx(strtoupper($title));
        $this->Cell(0, 9, $t, 0, 1, 'C');
        $tw = $this->GetStringWidth($t);
        $ty = $this->GetY() + 0.5;
        $this->accentDraw();
        $this->SetLineWidth(0.5);
        $this->Line(105 - $tw / 2 - 4, $ty, 105 + $tw / 2 + 4, $ty);
    }

    /** A left/right meta row (e.g. Ref / Date). */
    public function metaRow(string $left, string $right, float $gapBefore = 6.0): void
    {
        $this->Ln($gapBefore);
        $this->SetFont('Helvetica', '', 10);
        $this->SetTextColor(60, 60, 60);
        $this->SetX(self::LEFT);
        $this->Cell(90, 6, $this->tx($left), 0, 0, 'L');
        $this->Cell(90, 6, $this->tx($right), 0, 1, 'R');
    }

    /** Draw an official stamp placeholder box at (x,y). */
    public function stampBox(float $x, float $y, float $w = 45, float $h = 24, ?string $img = null): void
    {
        if ($img !== null && $img !== '' && is_file($img)) {
            $size = @getimagesize($img);
            if (is_array($size) && (int) $size[0] > 0 && (int) $size[1] > 0) {
                $pad = 2.0; $mw = $w - 2 * $pad; $mh = $h - 2 * $pad;
                $iw = $mw; $ih = $iw * ((int) $size[1] / (int) $size[0]);
                if ($ih > $mh) { $ih = $mh; $iw = $ih * ((int) $size[0] / (int) $size[1]); }
                try { $this->Image($img, $x + ($w - $iw) / 2, $y + ($h - $ih) / 2, $iw, $ih); return; } catch (\Throwable) {}
            }
        }
        // No stamp configured -> clear pending placeholder.
        $this->SetDrawColor(150, 150, 150);
        $this->SetLineWidth(0.2);
        $this->Rect($x, $y, $w, $h);
        $this->SetFont('Helvetica', 'I', 8);
        $this->SetTextColor(150, 150, 150);
        $this->Text($x + $w / 2 - 11, $y + $h / 2 + 1, $this->tx('Stamp Pending'));
    }

    /** A signature line + caption centred within [x, x+w]. */
    public function signatureLine(float $x, float $y, float $w, string $caption, ?string $img = null): void
    {
        if ($img !== null && $img !== '' && is_file($img)) {
            $size = @getimagesize($img);
            if (is_array($size) && (int) $size[0] > 0 && (int) $size[1] > 0) {
                $ih = 12.0; $iw = $ih * ((int) $size[0] / (int) $size[1]);
                if ($iw > $w) { $iw = $w; $ih = $iw * ((int) $size[1] / (int) $size[0]); }
                try { $this->Image($img, $x + ($w - $iw) / 2, $y - $ih - 1, $iw, $ih); } catch (\Throwable) {}
            }
        } else {
            $this->SetFont('Helvetica', 'I', 7.5);
            $this->SetTextColor(150, 150, 150);
            $this->Text($x + $w / 2 - 12, $y - 3, $this->tx('Signature Pending'));
        }
        $this->SetDrawColor(60, 60, 60);
        $this->SetLineWidth(0.2);
        $this->Line($x, $y, $x + $w, $y);
        $this->SetXY($x, $y + 1);
        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(30, 30, 30);
        $this->Cell($w, 5, $this->tx($caption), 0, 1, 'C');
    }

    /**
     * Unified verification band (divider + QR + document ID + verify URL).
     * Renders at the given absolute Y (or current Y when null).
     */
    public function verificationBand(string $idLabel, string $id, string $url, ?float $atY = null): void
    {
        $y = $atY ?? $this->GetY();
        $this->SetDrawColor(200, 200, 200);
        $this->SetLineWidth(0.3);
        $this->Line(self::LEFT, $y, self::RIGHT, $y);

        $qr = (new QrService())->pngPath($url);
        if ($qr !== null) {
            try { $this->Image($qr, self::LEFT, $y + 4, 22, 22, 'PNG'); } catch (\Throwable) {}
        }
        $this->SetXY(self::LEFT + 26, $y + 5);
        $this->SetFont('Helvetica', 'B', 9.5);
        $this->accentColor();
        $this->Cell(150, 5, $this->tx($idLabel . ': ' . $id), 0, 2, 'L');
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(90, 90, 90);
        $this->Cell(150, 5, $this->tx('This is a digitally generated document. Scan the QR code or visit:'), 0, 2, 'L');
        $this->accentColor();
        $this->Cell(150, 5, $this->tx($url), 0, 1, 'L');
    }

    /** @return array{0:int,1:int,2:int}|null */
    private static function hexToRgb(string $hex): ?array
    {
        $hex = ltrim(trim($hex), '#');
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return null;
        }
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
