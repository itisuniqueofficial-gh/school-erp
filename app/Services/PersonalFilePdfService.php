<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Student;
use App\Services\Pdf\PdfDocument;

/**
 * Printable Student Personal File checklist (A4).
 *
 * Reuses the shared PdfDocument engine (same branded header/typography as
 * certificates & hall tickets) rather than introducing a second PDF stack.
 * Purely a filing checklist — official signed documents keep their existing
 * server-side generators. Authorization is enforced by the calling controller.
 */
final class PersonalFilePdfService
{
    /** Render the checklist PDF for a student. Returns raw PDF bytes, or null if the student is missing. */
    public function render(int $studentId): ?string
    {
        $student = Student::profile($studentId);
        if ($student === null) {
            return null;
        }

        $pf = new PersonalFileService();
        $items = $pf->checklist($studentId);
        $summary = $pf->summary($studentId);
        $b = (new ReportCardDocumentService())->branding();

        $statusLabels = [
            'verified' => 'Verified', 'pending' => 'Under verification',
            'rejected' => 'Rejected', 'missing' => 'Missing', 'not_applicable' => 'Not applicable',
        ];

        $L = PdfDocument::LEFT;
        $W = PdfDocument::WIDTH;

        $pdf = new PdfDocument('P', 'mm', 'A4');
        $pdf->setAccent((string) ($b['accent'] ?? ''));
        $pdf->SetTitle('Personal File Checklist - ' . trim((string) $student['first_name'] . ' ' . (string) $student['last_name']));
        $pdf->SetAutoPageBreak(true, 18); // multi-page safe for long checklists
        $pdf->AddPage();

        $pdf->officialHeader($b, 18);
        $pdf->centeredTitle('Student Personal File Checklist', 6);

        // Student meta.
        $name = trim((string) $student['first_name'] . ' ' . (string) $student['last_name']);
        $klass = trim((string) ($student['class_name'] ?? '') . (!empty($student['section_name']) ? ' - ' . $student['section_name'] : ''));
        $pdf->metaRow('Student: ' . $name, 'Date: ' . date('d M Y'), 5);
        $pdf->metaRow('Admission No: ' . (string) ($student['admission_no'] ?? '-'), 'Student ID: ' . (string) ($student['uid'] ?? '-'), 1);
        $pdf->metaRow('Class: ' . ($klass !== '' ? $klass : '-'), 'Academic Year: ' . (string) ($student['year_name'] ?? '-'), 1);

        // Completion summary line.
        $pdf->Ln(3);
        $pdf->SetX($L);
        $pdf->SetFont('Helvetica', '', 9.5);
        $pdf->SetTextColor(70, 70, 70);
        $pdf->Cell(0, 5, $pdf->tx(sprintf(
            'Completion: %d%%  (%d of %d required documents verified, %d pending, %d missing, %d N/A)',
            (int) $summary['percent'], (int) $summary['verified'], (int) $summary['applicable'],
            (int) $summary['pending'], (int) $summary['missing'], (int) $summary['not_applicable']
        )), 0, 1, 'L');
        $pdf->Ln(2);

        // Table columns: #(10) Document(80) Required(22) Status(38) Remarks(30) = 180.
        $wNo = 10.0; $wDoc = 80.0; $wReq = 22.0; $wStatus = 38.0; $wRem = 30.0;

        // Header row.
        $pdf->SetX($L);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetFillColor(241, 245, 249);
        $pdf->SetTextColor(30, 30, 30);
        $pdf->Cell($wNo, 8, '#', 1, 0, 'C', true);
        $pdf->Cell($wDoc, 8, $pdf->tx('Document'), 1, 0, 'L', true);
        $pdf->Cell($wReq, 8, $pdf->tx('Required'), 1, 0, 'C', true);
        $pdf->Cell($wStatus, 8, $pdf->tx('Status'), 1, 0, 'C', true);
        $pdf->Cell($wRem, 8, $pdf->tx('Remarks'), 1, 1, 'L', true);

        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->SetTextColor(40, 40, 40);
        $lineH = 4.6;
        $i = 0;
        foreach ($items as $it) {
            $i++;
            $docName = $pdf->tx((string) $it['name']);
            $lines = max(1, $this->nbLines($pdf, $wDoc - 2, $docName));
            $rowH = $lines * $lineH;

            // Page-break guard for the whole row.
            if ($pdf->GetY() + $rowH > 279) {
                $pdf->AddPage();
            }
            $y = $pdf->GetY();

            $pdf->SetXY($L, $y);
            $pdf->Cell($wNo, $rowH, (string) $i, 1, 0, 'C');
            // Document name (multi-line) — draw with matching line height.
            $pdf->MultiCell($wDoc, $lineH, $docName, 1, 'L');
            $pdf->SetXY($L + $wNo + $wDoc, $y);
            $pdf->Cell($wReq, $rowH, $it['is_required'] ? 'Required' : 'Optional', 1, 0, 'C');
            $pdf->Cell($wStatus, $rowH, $pdf->tx($statusLabels[(string) $it['status']] ?? (string) $it['status']), 1, 0, 'C');
            $rem = (string) $it['status'] === 'not_applicable' ? ('N/A: ' . (string) ($it['na_reason'] ?? '')) : '';
            $pdf->Cell($wRem, $rowH, $pdf->tx($rem), 1, 1, 'L');
            $pdf->SetY($y + $rowH);
        }

        // Signature / verification area.
        $pdf->Ln(10);
        $y = $pdf->GetY();
        if ($y > 250) { $pdf->AddPage(); $y = $pdf->GetY(); }
        $pdf->signatureLine($L, $y + 12, 60, 'Verified by (Office)', $b['signature'] ?? null);
        $pdf->stampBox($L + 130, $y - 2, 45, 22, $b['stamp'] ?? null);

        return $pdf->Output('S');
    }

    /** Count the wrapped lines a string needs at the given width (FPDF-style). */
    private function nbLines(PdfDocument $pdf, float $width, string $text): int
    {
        $text = trim($text);
        if ($text === '') {
            return 1;
        }
        $words = preg_split('/\s+/', $text) ?: [$text];
        $line = '';
        $count = 1;
        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if ($pdf->GetStringWidth($candidate) > $width) {
                $count++;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }
        return $count;
    }
}
