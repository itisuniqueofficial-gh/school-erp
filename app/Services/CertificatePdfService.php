<?php

declare(strict_types=1);

namespace App\Services;

require_once dirname(__DIR__, 2) . '/lib/fpdf/fpdf.php';

/**
 * Renders a branded, print-ready A4 certificate PDF from a document array
 * produced by {@see CertificateService::document()}. Uses the bundled FPDF
 * engine (zero dependencies). Returns raw PDF bytes for a one-click download.
 */
final class CertificatePdfService
{
    /** @param array<string,mixed> $doc */
    public function render(array $doc): string
    {
        $b = $doc['branding'];
        $s = $doc['student'];

        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->SetTitle($doc['title'] . ' ' . $doc['cert_uid']);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->AddPage();

        // Decorative border
        $pdf->SetDrawColor(30, 64, 120);
        $pdf->SetLineWidth(1.2);
        $pdf->Rect(8, 8, 194, 281);
        $pdf->SetLineWidth(0.3);
        $pdf->Rect(11, 11, 188, 275);

        // School header
        $pdf->SetY(20);
        $pdf->SetFont('Helvetica', 'B', 20);
        $pdf->SetTextColor(30, 64, 120);
        $pdf->Cell(0, 10, $this->tx((string) $b['name']), 0, 1, 'C');
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(70, 70, 70);
        $meta = array_filter([$b['address'], $b['phone'], $b['email']]);
        if ($meta !== []) {
            $pdf->Cell(0, 5, $this->tx(implode('  |  ', $meta)), 0, 1, 'C');
        }
        $extra = array_filter([
            $b['board'] ? 'Board: ' . $b['board'] : '',
            $b['affiliation'] ? 'Affiliation: ' . $b['affiliation'] : '',
            $b['udise'] ? 'UDISE: ' . $b['udise'] : '',
        ]);
        if ($extra !== []) {
            $pdf->Cell(0, 5, $this->tx(implode('  |  ', $extra)), 0, 1, 'C');
        }
        $pdf->Ln(6);

        // Title
        $pdf->SetFont('Helvetica', 'B', 16);
        $pdf->SetTextColor(20, 20, 20);
        $pdf->Cell(0, 10, $this->tx(strtoupper((string) $doc['title'])), 0, 1, 'C');
        $y = $pdf->GetY();
        $pdf->SetDrawColor(30, 64, 120);
        $pdf->SetLineWidth(0.6);
        $pdf->Line(70, $y, 140, $y);
        $pdf->Ln(4);

        // Reference + date row
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(60, 60, 60);
        $ref = (string) ($doc['reference_no'] ?? '');
        $pdf->Cell(95, 6, $this->tx('Ref: ' . ($ref !== '' ? $ref : $doc['cert_uid'])), 0, 0, 'L');
        $pdf->Cell(95, 6, $this->tx('Date: ' . $doc['issued_on']), 0, 1, 'R');
        $pdf->Ln(4);

        // Body (justified)
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->SetTextColor(20, 20, 20);
        $pdf->MultiCell(0, 8, $this->tx((string) $doc['body']), 0, 'J');
        $pdf->Ln(6);

        // Student quick facts
        $pdf->SetFont('Helvetica', '', 10);
        foreach ([
            ['Student Name', $s['name']],
            ['Admission No', $s['admission_no']],
            ['Class', $s['class']],
            ['Academic Year', $s['year']],
        ] as $rowf) {
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->Cell(40, 6, $this->tx($rowf[0]), 0, 0);
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->Cell(0, 6, $this->tx(': ' . $rowf[1]), 0, 1);
        }

        if ($doc['status'] === 'revoked') {
            $pdf->Ln(4);
            $pdf->SetFont('Helvetica', 'B', 12);
            $pdf->SetTextColor(200, 0, 0);
            $pdf->Cell(0, 8, $this->tx('*** THIS CERTIFICATE HAS BEEN REVOKED ***'), 0, 1, 'C');
            $pdf->SetTextColor(20, 20, 20);
        }

        // Signatures + official stamp area
        $pdf->Ln(22);
        $sigY = $pdf->GetY();
        $pdf->SetFont('Helvetica', '', 9);
        // Stamp box (left)
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->SetLineWidth(0.2);
        $pdf->Rect(24, $sigY, 45, 22);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->Text(34, $sigY + 12, $this->tx('Official Stamp'));
        // Signature (right)
        $pdf->SetTextColor(20, 20, 20);
        $pdf->SetXY(120, $sigY + 16);
        $pdf->Cell(60, 6, $this->tx('__________________________'), 0, 2, 'C');
        $pdf->Cell(60, 5, $this->tx('Principal / Authorised Signatory'), 0, 1, 'C');

        // Verification footer with QR image (part of the single source of truth)
        $pdf->SetY(-34);
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->SetLineWidth(0.2);
        $pdf->Line(14, $pdf->GetY(), 196, $pdf->GetY());
        $pdf->Ln(2);
        $footY = $pdf->GetY();
        $qr = (new QrService())->pngPath((string) $doc['verify_url']);
        if ($qr !== null) {
            try { $pdf->Image($qr, 16, $footY, 22, 22, 'PNG'); } catch (\Throwable) {}
        }
        $pdf->SetXY(42, $footY + 2);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetTextColor(30, 64, 120);
        $pdf->Cell(150, 5, $this->tx('Certificate ID: ' . $doc['cert_uid']), 0, 2, 'L');
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->Cell(150, 5, $this->tx('Scan the QR code or visit the link below to verify authenticity:'), 0, 2, 'L');
        $pdf->SetTextColor(30, 64, 120);
        $pdf->Cell(150, 5, $this->tx((string) $doc['verify_url']), 0, 1, 'L');

        return $pdf->Output('S');
    }

    private function tx(string $s): string
    {
        $out = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $s);
        return $out !== false ? $out : $s;
    }
}
