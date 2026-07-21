<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\QueryBuilder;

/**
 * Aggregates dashboard metrics from across the system. Each query is guarded so
 * a missing table (e.g. before migration) degrades gracefully to zero.
 */
final class StatsService
{
    private function safe(callable $fn, mixed $default = 0): mixed
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return $default;
        }
    }

    /** @return array<string,int|float|string> */
    public function overview(): array
    {
        return [
            'students'   => (int) $this->safe(fn () => QueryBuilder::table('students')->whereNull('deleted_at')->where('status', 'active')->count()),
            'teachers'   => (int) $this->safe(fn () => QueryBuilder::table('staff')->whereNull('deleted_at')->where('staff_type', 'teaching')->count()),
            'staff'      => (int) $this->safe(fn () => QueryBuilder::table('staff')->whereNull('deleted_at')->count()),
            'classes'    => (int) $this->safe(fn () => QueryBuilder::table('classes')->count()),
            'fees_today' => (float) $this->safe(fn () => (float) (QueryBuilder::table('fee_payments')->where('payment_date', date('Y-m-d'))->where('status', 'success')->selectRaw('COALESCE(SUM(amount),0) AS t')->value('t') ?? 0)),
            'fees_month' => (float) $this->safe(fn () => (float) (QueryBuilder::table('fee_payments')->where('status', 'success')->whereLike('payment_date', date('Y-m') . '%')->selectRaw('COALESCE(SUM(amount),0) AS t')->value('t') ?? 0)),
        ];
    }

    /**
     * Today's attendance percentage (present / total marked).
     */
    public function attendanceRate(): float
    {
        return (float) $this->safe(function (): float {
            $total = QueryBuilder::table('student_attendance')->where('date', date('Y-m-d'))->count();
            if ($total === 0) {
                return 0.0;
            }
            $present = QueryBuilder::table('student_attendance')
                ->where('date', date('Y-m-d'))
                ->whereIn('status', ['present', 'late', 'half_day'])
                ->count();
            return round(($present / $total) * 100, 1);
        }, 0.0);
    }

    /**
     * Fee collection totals for the last N months (for charting).
     *
     * @return array{labels:string[],values:float[]}
     */
    public function feeTrend(int $months = 6): array
    {
        $labels = [];
        $values = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $ts = strtotime("first day of -{$i} month");
            $label = date('M Y', $ts);
            $prefix = date('Y-m', $ts);
            $labels[] = $label;
            $values[] = (float) $this->safe(fn () => (float) (QueryBuilder::table('fee_payments')
                ->where('status', 'success')
                ->whereLike('payment_date', $prefix . '%')
                ->selectRaw('COALESCE(SUM(amount),0) AS t')->value('t') ?? 0));
        }
        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Student count per class (for charting).
     *
     * @return array{labels:string[],values:int[]}
     */
    public function studentsByClass(): array
    {
        $rows = $this->safe(fn () => QueryBuilder::table('students')
            ->selectRaw('`classes`.`name` AS class_name, COUNT(`students`.`id`) AS cnt')
            ->join('classes', 'students.class_id', '=', 'classes.id')
            ->whereNull('students.deleted_at')
            ->groupBy('classes.id')
            ->orderBy('classes.numeric_level')
            ->get(), []);

        $labels = [];
        $values = [];
        foreach ((array) $rows as $row) {
            $labels[] = (string) $row['class_name'];
            $values[] = (int) $row['cnt'];
        }
        return ['labels' => $labels, 'values' => $values];
    }

    /** @return array<int,array<string,mixed>> */
    public function recentActivity(int $limit = 8): array
    {
        return (array) $this->safe(fn () => QueryBuilder::table('audit_logs')
            ->select(['audit_logs.action', 'audit_logs.description', 'audit_logs.created_at', 'users.name'])
            ->leftJoin('users', 'audit_logs.user_id', '=', 'users.id')
            ->orderBy('audit_logs.id', 'DESC')
            ->limit($limit)
            ->get(), []);
    }

    /** @return array<int,array<string,mixed>> */
    public function notices(int $limit = 5): array
    {
        return (array) $this->safe(fn () => QueryBuilder::table('notices')
            ->where('is_published', 1)
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->get(), []);
    }
}
