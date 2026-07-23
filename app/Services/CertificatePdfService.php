<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Pdf\PdfDocument;

/**
 * Renders a branded, print-ready A4 certificate PDF from a document array
 * produced by {@see CertificateService::document()} using the shared
 * {@see PdfDocument} engine, so its header, verification band, typography and
 * brand colour match every other official document. Single-page by design
 * (auto page-break off + fixed footer) — no orphan verification page.
 */
final class CertificatePdfService
{
    /** @param array<string,mixed> $doc */
    public function render(array $doc): string
    {
        $b = $doc['branding'];
        $s = $doc['student'];
        $revoked = ($doc['status'] ?? '') === 'revoked';
        $L = PdfDocument::LEFT;
        $W = PdfDocument::WIDTH;

        $pdf = new PdfDocument('P', 'mm', 'A4');
        $pdf->setAccent((string) ($b['accent'] ?? ''));
        $pdf->SetTitle($doc['title'] . ' ' . $doc['cert_uid']);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        // Decorative frame
        $pdf->accentDraw();
        $pdf->SetLineWidth(0.9);
        $pdf->Rect(10, 10, 190, 277);
        $pdf->SetLineWidth(0.25);
        $pdf->Rect(13, 13, 184, 271);

        // Shared header + title + meta row
        $pdf->officialHeader($b, 23);
        $pdf->centeredTitle((string) $doc['title'], 7);
        $ref = trim((string) ($doc['reference_no'] ?? ''));
        $pdf->metaRow('Ref: ' . ($ref !== '' ? $ref : $doc['cert_uid']), 'Date: ' . $this->prettyDate((string) $doc['issued_on']), 6);

        // Body (justified)
        $pdf->Ln(4);
        $pdf->SetX($L);
        $pdf->SetFont('Helvetica', '', 11.5);
        $pdf->SetTextColor(25, 25, 25);
        $pdf->MultiCell($W, 7, $pdf->tx((string) $doc['body']), 0, 'J');

        // Student details grid
        $pdf->Ln(5);
        $pdf->SetX($L);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(32, 7, $pdf->tx('Student Name'), 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(148, 7, $pdf->tx(': ' . $s['name']), 0, 1, 'L');
        $pairRows = [
            ['Admission No', (string) $s['admission_no'], 'Student ID', (string) $s['uid']],
            ['Class', (string) ($s['class'] !== '' ? $s['class'] : '-'), 'Academic Year', (string) ($s['year'] !== '' ? $s['year'] : '-')],
        ];
        foreach ($pairRows as $r) {
            $pdf->SetX($L);
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->Cell(32, 7, $pdf->tx($r[0]), 0, 0, 'L');
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->Cell(58, 7, $pdf->tx(': ' . $r[1]), 0, 0, 'L');
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->Cell(32, 7, $pdf->tx($r[2]), 0, 0, 'L');
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->Cell(58, 7, $pdf->tx(': ' . $r[3]), 0, 1, 'L');
        }

        if ($revoked) {
            $pdf->Ln(4);
            $pdf->SetFont('Helvetica', 'B', 12);
            $pdf->SetTextColor(190, 0, 0);
            $pdf->SetX($L);
            $pdf->Cell($W, 8, $pdf->tx('THIS CERTIFICATE HAS BEEN REVOKED'), 0, 1, 'C');
            $pdf->SetTextColor(20, 20, 20);
        }

        // Signature + stamp (fixed row)
        $sigY = 224.0;
        $pdf->stampBox($L, $sigY - 6);
        $pdf->signatureLine(135, $sigY + 14, 60, 'Principal / Authorised Signatory');

        // Unified verification band (fixed bottom)
        $pdf->verificationBand('Certificate ID', (string) $doc['cert_uid'], (string) $doc['verify_url'], 254.0);

        return $pdf->Output('S');
    }

    /** Format an ISO date as e.g. "23 July 2026"; fall back to the raw value. */
    private function prettyDate(string $iso): string
    {
        $ts = strtotime($iso);
        return $ts !== false ? date('d F Y', $ts) : $iso;
    }
}
