<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Pdf\PdfDocument;

/**
 * Renders a branded, print-ready A4 report card PDF from a document array
 * produced by {@see ReportCardDocumentService} using the shared
 * {@see PdfDocument} engine, so its header, verification band, typography and
 * brand colour match certificates and every other official document.
 */
final class ReportCardPdfService
{
    /** @param array<string,mixed> $doc */
    public function render(array $doc): string
    {
        $pdf = new PdfDocument('P', 'mm', 'A4');
        $pdf->setAccent((string) ($doc['branding']['accent'] ?? ''));
        $pdf->SetTitle('Report Card ' . (string) $doc['rc_uid']);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        $b = $doc['branding'];
        $s = $doc['student'];
        $t = $doc['totals'];
        $a = $doc['attendance'];
        $ex = $doc['exam'] ?? [];

        // ---- Shared official header ----------------------------------------
        $pdf->officialHeader($b, 13);
        $pdf->Ln(2);

        // Title bar (report-card specific)
        $pdf->accentDraw();
        $pdf->SetFillColor(30, 64, 120);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', 'B', 11);
        $title = 'REPORT CARD  -  ' . (string) ($ex['name'] ?? '') . '  (' . (string) $s['year'] . ')';
        $pdf->Cell(0, 8, $pdf->tx($title), 0, 1, 'C', true);
        $pdf->Ln(2);

        // ---- Student information (two columns) ------------------------------
        $pdf->SetTextColor(20, 20, 20);
        $rowsLeft = [
            ['Name', $s['name']],
            ['User ID', $s['uid']],
            ['Admission No', $s['admission_no']],
            ['Roll No', $s['roll_no'] !== '' ? $s['roll_no'] : '-'],
            ['Class', trim($s['class'] . ($s['section'] !== '' ? ' - ' . $s['section'] : ''))],
        ];
        $rowsRight = [
            ['Father', $s['father'] !== '' ? $s['father'] : '-'],
            ['Mother', $s['mother'] !== '' ? $s['mother'] : '-'],
            ['Date of Birth', $s['dob'] !== '' ? $s['dob'] : '-'],
            ['Gender', $s['gender'] !== '' ? $s['gender'] : '-'],
            ['Academic Year', $s['year']],
        ];
        $photoPath = $this->photoPath((string) $s['photo']);
        $startY = $pdf->GetY();
        for ($i = 0; $i < 5; $i++) {
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->Cell(28, 6, $pdf->tx($rowsLeft[$i][0]), 0, 0);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->Cell(62, 6, $pdf->tx(': ' . $rowsLeft[$i][1]), 0, 0);
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->Cell(30, 6, $pdf->tx($rowsRight[$i][0]), 0, 0);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->Cell(50, 6, $pdf->tx(': ' . $rowsRight[$i][1]), 0, 1);
        }
        if ($photoPath !== null) {
            try { $pdf->Image($photoPath, 175, $startY, 25, 30); } catch (\Throwable) {}
        }
        $pdf->Ln(2);

        // ---- Marks table ----------------------------------------------------
        $w = [66, 20, 20, 24, 18, 18, 24];
        $head = ['Subject', 'Code', 'Max', 'Obtained', '%', 'Grade', 'Result'];
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetFillColor(230, 236, 245);
        $pdf->SetTextColor(20, 20, 20);
        foreach ($head as $i => $h) {
            $pdf->Cell($w[$i], 7, $pdf->tx($h), 1, 0, 'C', true);
        }
        $pdf->Ln();
        $pdf->SetFont('Helvetica', '', 9);
        foreach ($doc['subjects'] as $row) {
            $pdf->Cell($w[0], 6, $pdf->tx((string) $row['subject']), 1, 0, 'L');
            $pdf->Cell($w[1], 6, $pdf->tx((string) $row['code']), 1, 0, 'C');
            $pdf->Cell($w[2], 6, $this->num($row['max']), 1, 0, 'C');
            $pdf->Cell($w[3], 6, is_numeric($row['obtained']) ? $this->num($row['obtained']) : (string) $row['obtained'], 1, 0, 'C');
            $pdf->Cell($w[4], 6, $this->num($row['percentage']) . '%', 1, 0, 'C');
            $pdf->Cell($w[5], 6, $pdf->tx((string) $row['grade']), 1, 0, 'C');
            $pdf->Cell($w[6], 6, $pdf->tx((string) $row['result']), 1, 1, 'C');
        }
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->Cell($w[0] + $w[1], 7, $pdf->tx('Grand Total'), 1, 0, 'R', true);
        $pdf->Cell($w[2], 7, $this->num($t['max']), 1, 0, 'C', true);
        $pdf->Cell($w[3], 7, $this->num($t['obtained']), 1, 0, 'C', true);
        $pdf->Cell($w[4], 7, $this->num($t['percentage']) . '%', 1, 0, 'C', true);
        $pdf->Cell($w[5] + $w[6], 7, $pdf->tx((string) $t['grade']), 1, 1, 'C', true);
        $pdf->Ln(2);

        // ---- Result + attendance summary (two boxes) ------------------------
        $colW = 95;
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetFillColor(30, 64, 120); $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell($colW, 7, $pdf->tx('Result Summary'), 1, 0, 'C', true);
        $pdf->Cell(4, 7, '', 0, 0);
        $pdf->Cell($colW, 7, $pdf->tx('Attendance (' . $s['year'] . ')'), 1, 1, 'C', true);
        $pdf->SetTextColor(20, 20, 20); $pdf->SetFont('Helvetica', '', 9);

        $resLines = [
            ['Percentage', $this->num($t['percentage']) . '%'],
            ['Overall Grade', (string) $t['grade']],
            ['GPA', $this->num($t['gpa'])],
            ['Division', (string) $t['division']],
            ['Result', (string) $t['result']],
            ['Promotion Status', (string) $t['promotion']],
        ];
        $attLines = [
            ['Working Days', (string) ((int) ($a['totalClasses'] ?? 0))],
            ['Present', (string) ((int) ($a['present'] ?? 0))],
            ['Absent', (string) ((int) ($a['absent'] ?? 0))],
            ['Late', (string) ((int) ($a['late'] ?? 0))],
            ['Leave', (string) ((int) ($a['excused'] ?? $a['leave'] ?? 0))],
            ['Attendance %', $this->num($a['attendancePercentage'] ?? 0) . '%'],
        ];
        for ($i = 0; $i < 6; $i++) {
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->Cell(45, 6, $pdf->tx($resLines[$i][0]), 'LR', 0);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->Cell($colW - 45, 6, $pdf->tx($resLines[$i][1]), 'R', 0, 'R');
            $pdf->Cell(4, 6, '', 0, 0);
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->Cell(45, 6, $pdf->tx($attLines[$i][0]), 'LR', 0);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->Cell($colW - 45, 6, $pdf->tx($attLines[$i][1]), 'R', 1, 'R');
        }
        $pdf->Cell($colW, 0, '', 'T', 0);
        $pdf->Cell(4, 0, '', 0, 0);
        $pdf->Cell($colW, 0, '', 'T', 1);
        $pdf->Ln(6);

        // ---- Remarks --------------------------------------------------------
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell(0, 6, $pdf->tx('Class Teacher Remarks:'), 0, 1);
        $pdf->SetFont('Helvetica', 'I', 9);
        $remark = $t['result'] === 'PASSED'
            ? 'A commendable performance. Keep up the consistent effort.'
            : 'Needs improvement in weaker subjects. Additional support recommended.';
        $pdf->MultiCell(0, 5, $pdf->tx($remark), 0, 'L');
        $pdf->Ln(8);

        // ---- Signatures -----------------------------------------------------
        $pdf->SetFont('Helvetica', '', 9);
        $sw = 63;
        $pdf->Cell($sw, 6, $pdf->tx('__________________'), 0, 0, 'C');
        $pdf->Cell($sw, 6, $pdf->tx('__________________'), 0, 0, 'C');
        $pdf->Cell($sw, 6, $pdf->tx('__________________'), 0, 1, 'C');
        $pdf->Cell($sw, 5, $pdf->tx('Class Teacher'), 0, 0, 'C');
        $pdf->Cell($sw, 5, $pdf->tx('Principal'), 0, 0, 'C');
        $pdf->Cell($sw, 5, $pdf->tx('Parent / Guardian'), 0, 1, 'C');
        $pdf->Ln(6);

        // ---- Unified verification band -------------------------------------
        $pdf->verificationBand('Report Card ID', (string) $doc['rc_uid'] . ' (Version ' . (int) $doc['version'] . ')', (string) $doc['verify_url']);

        return $pdf->Output('S');
    }

    private function num(mixed $n): string
    {
        $f = (float) $n;
        return rtrim(rtrim(number_format($f, 2, '.', ''), '0'), '.') ?: '0';
    }

    /** Resolve a safe, existing image path for the student photo (PNG/JPG only). */
    private function photoPath(string $photo): ?string
    {
        if ($photo === '') {
            return null;
        }
        // Photos uploaded via the centralized AvatarService live under
        // storage/uploads/avatars as "<hex>.<ext>" — resolve those first.
        $avatar = (new AvatarService())->path($photo);
        if ($avatar !== null) {
            return $avatar;
        }
        $candidates = [
            dirname(__DIR__, 2) . '/storage/uploads/' . ltrim($photo, '/'),
            dirname(__DIR__, 2) . '/public/' . ltrim($photo, '/'),
            dirname(__DIR__, 2) . '/' . ltrim($photo, '/'),
        ];
        foreach ($candidates as $p) {
            if (is_file($p) && preg_match('/\.(png|jpe?g|gif)$/i', $p) === 1) {
                return $p;
            }
        }
        return null;
    }
}
