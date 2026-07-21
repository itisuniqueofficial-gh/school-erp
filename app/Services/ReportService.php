<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\QueryBuilder;

/**
 * Generates report datasets for the reports module and API.
 * Each report returns ['columns'=>[], 'rows'=>[], 'title'=>string] or null.
 */
final class ReportService
{
    private const TYPES = ['attendance', 'fees', 'academic', 'students', 'staff'];

    public function types(): array
    {
        return self::TYPES;
    }

    /**
     * @param array<string,mixed> $params
     * @return array{title:string,columns:string[],rows:array<int,array<string,mixed>>}|null
     */
    public function generate(string $type, array $params = []): ?array
    {
        return match ($type) {
            'attendance' => $this->attendance($params),
            'fees'       => $this->fees($params),
            'academic'   => $this->academic($params),
            'students'   => $this->students($params),
            'staff'      => $this->staff($params),
            default      => null,
        };
    }

    private function attendance(array $params): array
    {
        $month = (string) ($params['month'] ?? date('Y-m'));
        $rows = QueryBuilder::table('student_attendance')
            ->selectRaw('`status`, COUNT(*) AS count')
            ->whereLike('date', $month . '%')
            ->groupBy('status')
            ->get();
        return ['title' => "Attendance summary — {$month}", 'columns' => ['status', 'count'], 'rows' => $rows];
    }

    private function fees(array $params): array
    {
        $rows = QueryBuilder::table('fee_payments')
            ->selectRaw('`payment_date` AS date, `method`, SUM(`amount`) AS total')
            ->where('status', 'success')
            ->groupBy('payment_date', 'method')
            ->orderBy('payment_date', 'DESC')
            ->limit(100)
            ->get();
        return ['title' => 'Fee collection report', 'columns' => ['date', 'method', 'total'], 'rows' => $rows];
    }

    private function academic(array $params): array
    {
        $rows = QueryBuilder::table('report_cards')
            ->selectRaw('`result`, COUNT(*) AS count, AVG(`percentage`) AS avg_percent')
            ->groupBy('result')
            ->get();
        return ['title' => 'Academic performance', 'columns' => ['result', 'count', 'avg_percent'], 'rows' => $rows];
    }

    private function students(array $params): array
    {
        $rows = QueryBuilder::table('students')
            ->selectRaw('`classes`.`name` AS class_name, COUNT(`students`.`id`) AS count')
            ->leftJoin('classes', 'students.class_id', '=', 'classes.id')
            ->whereNull('students.deleted_at')
            ->groupBy('students.class_id')
            ->get();
        return ['title' => 'Students by class', 'columns' => ['class_name', 'count'], 'rows' => $rows];
    }

    private function staff(array $params): array
    {
        $rows = QueryBuilder::table('staff')
            ->selectRaw('`staff_type`, `status`, COUNT(*) AS count')
            ->whereNull('deleted_at')
            ->groupBy('staff_type', 'status')
            ->get();
        return ['title' => 'Staff summary', 'columns' => ['staff_type', 'status', 'count'], 'rows' => $rows];
    }

    /**
     * Convert a report to CSV text.
     */
    public function toCsv(array $report): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, $report['columns']);
        foreach ($report['rows'] as $row) {
            $line = [];
            foreach ($report['columns'] as $col) {
                $line[] = $row[$col] ?? '';
            }
            fputcsv($out, $line);
        }
        rewind($out);
        return (string) stream_get_contents($out);
    }
}
