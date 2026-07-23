<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Pdf\PdfDocument;

/**
 * Renders a professional A4 hall ticket / admit card PDF using the shared
 * {@see PdfDocument} engine, so it matches certificates and report cards.
 * Single page by design (auto page-break off + fixed verification band).
 */
final class HallTicketPdfService
{
    /** @param array<string,mixed> $doc */
    public function render(array $doc): string
    {
        $b = $doc['branding'];
        $s = $doc['student'];
        $L = PdfDocument::LEFT;
        $revoked = ($doc['status'] ?? '') === 'revoked';

        $pdf = new PdfDocument('P', 'mm', 'A4');
        $pdf->setAccent((string) ($b['accent'] ?? ''));
        $pdf->SetTitle($doc['type_label'] . ' ' . $doc['ht_uid']);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        // Frame
        $pdf->accentDraw();
        $pdf->SetLineWidth(0.6);
        $pdf->Rect(10, 10, 190, 277);

        $pdf->officialHeader($b, 22);
        $pdf->centeredTitle((string) $doc['type_label'], 6);
        $pdf->metaRow('Ticket No: ' . $doc['ht_uid'], 'Issued: ' . $doc['issued_on'], 5);

        // Student block + photo
        $pdf->Ln(3);
        $top = $pdf->GetY();
        $rows = [
            ['Student Name', $s['name']],
            ['Roll No', $s['roll_no'] !== '' ? $s['roll_no'] : '-'],
            ['Admission No', $s['admission_no']],
            ['Student ID', $s['uid']],
            ['Class', trim($s['class'] . ($s['section'] !== '' ? ' - ' . $s['section'] : ''))],
            ['Academic Year', $s['year'] !== '' ? $s['year'] : '-'],
            ['Examination', $doc['exam']['name'] !== '' ? $doc['exam']['name'] : '-'],
        ];
        foreach ($rows as $r) {
            $pdf->SetX($L);
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->Cell(38, 6.5, $pdf->tx($r[0]), 0, 0, 'L');
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->Cell(112, 6.5, $pdf->tx(': ' . $r[1]), 0, 1, 'L');
        }
        // Photo box (right)
        $photo = $this->photoPath((string) $s['photo']);
        $pdf->SetDrawColor(150, 150, 150); $pdf->SetLineWidth(0.2);
        $pdf->Rect(160, $top, 35, 42);
        if ($photo !== null) {
            try { $pdf->Image($photo, 160.5, $top + 0.5, 34, 41); } catch (\Throwable) {}
        } else {
            $pdf->SetFont('Helvetica', 'I', 8); $pdf->SetTextColor(150, 150, 150);
            $pdf->Text(168, $top + 22, $pdf->tx('Photo'));
        }

        if ($doc['center'] !== '') {
            $pdf->Ln(2);
            $pdf->SetX($L);
            $pdf->SetFont('Helvetica', 'B', 10); $pdf->SetTextColor(20, 20, 20);
            $pdf->Cell(38, 6.5, $pdf->tx('Exam Center'), 0, 0);
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->Cell(112, 6.5, $pdf->tx(': ' . $doc['center']), 0, 1);
        }

        // Exam schedule table
        $pdf->Ln(3);
        $pdf->SetX($L);
        $pdf->SetFont('Helvetica', 'B', 11); $pdf->accentColor();
        $pdf->Cell(0, 7, $pdf->tx('Examination Schedule'), 0, 1, 'L');
        $w = [70, 30, 38, 38, 4];
        $pdf->SetFont('Helvetica', 'B', 9); $pdf->SetFillColor(230, 236, 245); $pdf->SetTextColor(20, 20, 20);
        $pdf->SetX($L);
        $pdf->Cell($w[0], 7, $pdf->tx('Subject'), 1, 0, 'L', true);
        $pdf->Cell($w[1], 7, $pdf->tx('Code'), 1, 0, 'C', true);
        $pdf->Cell($w[2], 7, $pdf->tx('Date'), 1, 0, 'C', true);
        $pdf->Cell($w[3], 7, $pdf->tx('Time'), 1, 1, 'C', true);
        $pdf->SetFont('Helvetica', '', 9);
        if (empty($doc['subjects'])) {
            $pdf->SetX($L);
            $pdf->Cell(176, 7, $pdf->tx('Schedule to be announced.'), 1, 1, 'C');
        } else {
            foreach ($doc['subjects'] as $row) {
                $time = trim((string) ($row['start_time'] ?? '')) !== '' ? substr((string) $row['start_time'], 0, 5) . ' - ' . substr((string) ($row['end_time'] ?? ''), 0, 5) : '-';
                $pdf->SetX($L);
                $pdf->Cell($w[0], 6.5, $pdf->tx((string) ($row['subject'] ?? '-')), 1, 0, 'L');
                $pdf->Cell($w[1], 6.5, $pdf->tx((string) ($row['code'] ?? '')), 1, 0, 'C');
                $pdf->Cell($w[2], 6.5, $pdf->tx((string) ($row['exam_date'] ?? '-')), 1, 0, 'C');
                $pdf->Cell($w[3], 6.5, $pdf->tx($time), 1, 1, 'C');
            }
        }

        // Instructions
        $pdf->Ln(3);
        $pdf->SetX($L);
        $pdf->SetFont('Helvetica', 'B', 10); $pdf->accentColor();
        $pdf->Cell(0, 6, $pdf->tx('Instructions'), 0, 1);
        $pdf->SetFont('Helvetica', '', 9); $pdf->SetTextColor(40, 40, 40);
        $instructions = $doc['instructions'] !== ''
            ? $doc['instructions']
            : "1. Bring this hall ticket to every examination.  2. Reach the center at least 30 minutes before.  3. Electronic devices are prohibited.  4. Follow all invigilator instructions.";
        $pdf->SetX($L);
        $pdf->MultiCell(PdfDocument::WIDTH, 5.5, $pdf->tx($instructions), 0, 'L');

        if ($revoked) {
            $pdf->Ln(2); $pdf->SetX($L);
            $pdf->SetFont('Helvetica', 'B', 12); $pdf->SetTextColor(190, 0, 0);
            $pdf->Cell(PdfDocument::WIDTH, 8, $pdf->tx('THIS HALL TICKET HAS BEEN REVOKED'), 0, 1, 'C');
            $pdf->SetTextColor(20, 20, 20);
        }

        // Signature + stamp (fixed row)
        $sigY = 232.0;
        $pdf->stampBox($L, $sigY - 6);
        $pdf->signatureLine(135, $sigY + 14, 60, 'Controller of Examinations');

        // Verification band
        $pdf->verificationBand('Hall Ticket ID', (string) $doc['ht_uid'], (string) $doc['verify_url'], 262.0);

        return $pdf->Output('S');
    }

    private function photoPath(string $photo): ?string
    {
        if ($photo === '') {
            return null;
        }
        foreach ([
            dirname(__DIR__, 2) . '/storage/uploads/' . ltrim($photo, '/'),
            dirname(__DIR__, 2) . '/public/' . ltrim($photo, '/'),
        ] as $p) {
            if (is_file($p) && preg_match('/\.(png|jpe?g|gif)$/i', $p) === 1) {
                return $p;
            }
        }
        return null;
    }
}
