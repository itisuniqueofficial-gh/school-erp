<?php

declare(strict_types=1);

namespace App\Services;

require_once dirname(__DIR__, 2) . '/lib/fpdf/fpdf.php';

/**
 * Renders a branded, print-ready A4 report card PDF from a document array
 * produced by {@see ReportCardDocumentService}. Uses the bundled FPDF engine
 * (zero external dependencies). Returns raw PDF bytes for a one-click download.
 */
final class ReportCardPdfService
{
    /** @param array<string,mixed> $doc */
    public function render(array $doc): string
    {
        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->SetTitle('Report Card ' . (string) $doc['rc_uid']);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        $b = $doc['branding'];
        $s = $doc['student'];
        $t = $doc['totals'];
        $a = $doc['attendance'];
        $ex = $doc['exam'] ?? [];

        // ---- School header --------------------------------------------------
        $pdf->SetDrawColor(30, 64, 120);
        $pdf->SetLineWidth(0.5);
        $pdf->SetFont('Helvetica', 'B', 18);
        $pdf->SetTextColor(30, 64, 120);
        $pdf->Cell(0, 9, $this->tx((string) $b['name']), 0, 1, 'C');
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(60, 60, 60);
        $meta = array_filter([$b['address'], $b['phone'], $b['email'], $b['website']]);
        if ($meta !== []) {
            $pdf->Cell(0, 5, $this->tx(implode('  |  ', $meta)), 0, 1, 'C');
        }
        $line3 = array_filter([
            $b['board'] ? 'Board: ' . $b['board'] : '',
            $b['affiliation'] ? 'Affiliation: ' . $b['affiliation'] : '',
            $b['udise'] ? 'UDISE: ' . $b['udise'] : '',
        ]);
        if ($line3 !== []) {
            $pdf->Cell(0, 5, $this->tx(implode('  |  ', $line3)), 0, 1, 'C');
        }
        $pdf->Ln(1);
        $y = $pdf->GetY();
        $pdf->Line(10, $y, 200, $y);
        $pdf->Ln(2);

        // Title bar
        $pdf->SetFillColor(30, 64, 120);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', 'B', 11);
        $title = 'REPORT CARD  -  ' . (string) ($ex['name'] ?? '') . '  (' . (string) $s['year'] . ')';
        $pdf->Cell(0, 8, $this->tx($title), 0, 1, 'C', true);
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
        // Optional student photo.
        $photoPath = $this->photoPath((string) $s['photo']);
        $startY = $pdf->GetY();
        for ($i = 0; $i < 5; $i++) {
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->Cell(28, 6, $this->tx($rowsLeft[$i][0]), 0, 0);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->Cell(62, 6, $this->tx(': ' . $rowsLeft[$i][1]), 0, 0);
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->Cell(30, 6, $this->tx($rowsRight[$i][0]), 0, 0);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->Cell(50, 6, $this->tx(': ' . $rowsRight[$i][1]), 0, 1);
        }
        if ($photoPath !== null) {
            try { $pdf->Image($photoPath, 175, $startY, 25, 30); } catch (\Throwable) {}
        }
        $pdf->Ln(2);

        // ---- Marks table ----------------------------------------------------
        $w = [66, 20, 20, 24, 18, 18, 24]; // Subject, Code, Max, Obtained, %, Grade, Result
        $head = ['Subject', 'Code', 'Max', 'Obtained', '%', 'Grade', 'Result'];
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetFillColor(230, 236, 245);
        $pdf->SetTextColor(20, 20, 20);
        foreach ($head as $i => $h) {
            $pdf->Cell($w[$i], 7, $this->tx($h), 1, 0, 'C', true);
        }
        $pdf->Ln();
        $pdf->SetFont('Helvetica', '', 9);
        foreach ($doc['subjects'] as $row) {
            $pdf->Cell($w[0], 6, $this->tx((string) $row['subject']), 1, 0, 'L');
            $pdf->Cell($w[1], 6, $this->tx((string) $row['code']), 1, 0, 'C');
            $pdf->Cell($w[2], 6, $this->num($row['max']), 1, 0, 'C');
            $pdf->Cell($w[3], 6, is_numeric($row['obtained']) ? $this->num($row['obtained']) : (string) $row['obtained'], 1, 0, 'C');
            $pdf->Cell($w[4], 6, $this->num($row['percentage']) . '%', 1, 0, 'C');
            $pdf->Cell($w[5], 6, $this->tx((string) $row['grade']), 1, 0, 'C');
            $pdf->Cell($w[6], 6, $this->tx((string) $row['result']), 1, 1, 'C');
        }
        // Totals row
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetFillColor(245, 245, 245);
        $pdf->Cell($w[0] + $w[1], 7, $this->tx('Grand Total'), 1, 0, 'R', true);
        $pdf->Cell($w[2], 7, $this->num($t['max']), 1, 0, 'C', true);
        $pdf->Cell($w[3], 7, $this->num($t['obtained']), 1, 0, 'C', true);
        $pdf->Cell($w[4], 7, $this->num($t['percentage']) . '%', 1, 0, 'C', true);
        $pdf->Cell($w[5] + $w[6], 7, $this->tx((string) $t['grade']), 1, 1, 'C', true);
        $pdf->Ln(2);

        // ---- Result + attendance summary (two boxes) ------------------------
        $colW = 95;
        $topY = $pdf->GetY();
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetFillColor(30, 64, 120); $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell($colW, 7, $this->tx('Result Summary'), 1, 0, 'C', true);
        $pdf->Cell(4, 7, '', 0, 0);
        $pdf->Cell($colW, 7, $this->tx('Attendance (' . $s['year'] . ')'), 1, 1, 'C', true);
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
            $pdf->Cell(45, 6, $this->tx($resLines[$i][0]), 'LR', 0);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->Cell($colW - 45, 6, $this->tx($resLines[$i][1]), 'R', 0, 'R');
            $pdf->Cell(4, 6, '', 0, 0);
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->Cell(45, 6, $this->tx($attLines[$i][0]), 'LR', 0);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->Cell($colW - 45, 6, $this->tx($attLines[$i][1]), 'R', 1, 'R');
        }
        // close boxes
        $pdf->Cell($colW, 0, '', 'T', 0);
        $pdf->Cell(4, 0, '', 0, 0);
        $pdf->Cell($colW, 0, '', 'T', 1);
        $pdf->Ln(6);

        // ---- Remarks --------------------------------------------------------
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell(0, 6, $this->tx('Class Teacher Remarks:'), 0, 1);
        $pdf->SetFont('Helvetica', 'I', 9);
        $remark = $t['result'] === 'PASSED'
            ? 'A commendable performance. Keep up the consistent effort.'
            : 'Needs improvement in weaker subjects. Additional support recommended.';
        $pdf->MultiCell(0, 5, $this->tx($remark), 0, 'L');
        $pdf->Ln(10);

        // ---- Signatures -----------------------------------------------------
        $pdf->SetFont('Helvetica', '', 9);
        $sw = 63;
        $pdf->Cell($sw, 6, $this->tx('__________________'), 0, 0, 'C');
        $pdf->Cell($sw, 6, $this->tx('__________________'), 0, 0, 'C');
        $pdf->Cell($sw, 6, $this->tx('__________________'), 0, 1, 'C');
        $pdf->Cell($sw, 5, $this->tx('Class Teacher'), 0, 0, 'C');
        $pdf->Cell($sw, 5, $this->tx('Principal'), 0, 0, 'C');
        $pdf->Cell($sw, 5, $this->tx('Parent / Guardian'), 0, 1, 'C');
        $pdf->Ln(6);

        // ---- Verification footer -------------------------------------------
        $pdf->SetDrawColor(180, 180, 180);
        $y = $pdf->GetY();
        $pdf->Line(10, $y, 200, $y);
        $pdf->Ln(2);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell(0, 5, $this->tx('Report Card ID: ' . $doc['rc_uid'] . '   (Version ' . $doc['version'] . ')'), 0, 1, 'C');
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(0, 5, $this->tx('Verify authenticity at: ' . $doc['verify_url']), 0, 1, 'C');

        return $pdf->Output('S');
    }

    /** Sanitise UTF-8 text to the Latin-1 range FPDF core fonts support. */
    private function tx(string $s): string
    {
        $out = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $s);
        return $out !== false ? $out : $s;
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
