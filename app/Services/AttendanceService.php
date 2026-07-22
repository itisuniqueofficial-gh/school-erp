<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\QueryBuilder;

/**
 * Centralized, academic-year-aware student attendance calculation — the SINGLE
 * source of truth used by the student dashboard, student profile, attendance
 * page, and admin/teacher student views. No module should compute attendance
 * inline; they all call this service so the percentage is identical everywhere.
 *
 * Formula: attendancePercentage = attended / applicable * 100, where
 *   attended    = present + late + half_day
 *   applicable  = all records for the year EXCEPT holidays
 * (leave/excused count toward applicable but not attended).
 */
final class AttendanceService
{
    /** Present-equivalent statuses (numerator). */
    private const ATTENDED = ['present', 'late', 'half_day'];

    /** Resolve an academic year's [start, end] date range (current year if null). */
    private function yearRange(?int $yearId): array
    {
        $row = $yearId
            ? QueryBuilder::table('academic_years')->where('id', $yearId)->first()
            : QueryBuilder::table('academic_years')->where('is_current', 1)->first();
        return [
            'id'    => (int) ($row['id'] ?? 0),
            'start' => $row['start_date'] ?? null,
            'end'   => $row['end_date'] ?? null,
            'name'  => $row['name'] ?? null,
        ];
    }

    /**
     * Daily attendance summary for a student within an academic year.
     *
     * @return array{totalClasses:int,present:int,absent:int,late:int,excused:int,half_day:int,attendancePercentage:float,hasData:bool,year_id:int,year_name:?string}
     */
    public function getStudentAttendanceSummary(int $studentId, ?int $yearId = null): array
    {
        $year = $this->yearRange($yearId);

        $base = QueryBuilder::table('student_attendance')->where('student_id', $studentId);
        // Scope by the academic year's date range when available.
        if ($year['start'] && $year['end']) {
            $base->whereRaw('`date` BETWEEN ? AND ?', [$year['start'], $year['end']]);
        }

        // One grouped query → counts per status (avoids N queries + duplicate logic).
        $rows = (clone $base)->selectRaw('`status`, COUNT(*) AS c')->groupBy('status')->get();
        $by = [];
        foreach ($rows as $r) {
            $by[(string) $r['status']] = (int) $r['c'];
        }

        $present = $by['present'] ?? 0;
        $late    = $by['late'] ?? 0;
        $halfDay = $by['half_day'] ?? 0;
        $absent  = $by['absent'] ?? 0;
        $excused = $by['leave'] ?? 0;

        // Applicable = everything except holidays.
        $applicable = $present + $late + $halfDay + $absent + $excused;
        $attended   = $present + $late + $halfDay;
        $pct = $applicable > 0 ? round(($attended / $applicable) * 100, 2) : 0.0;

        return [
            'totalClasses'         => $applicable,
            'present'              => $present,
            'absent'               => $absent,
            'late'                 => $late,
            'excused'              => $excused,
            'half_day'             => $halfDay,
            'attendancePercentage' => $pct,
            'hasData'              => $applicable > 0,
            'year_id'              => $year['id'],
            'year_name'            => $year['name'],
        ];
    }

    /** Convenience: just the percentage (same source as the summary). */
    public function getStudentAttendancePercentage(int $studentId, ?int $yearId = null): float
    {
        return $this->getStudentAttendanceSummary($studentId, $yearId)['attendancePercentage'];
    }

    /**
     * Subject-wise attendance breakdown from subject_attendance for the year.
     *
     * @return array<int,array{subject_id:int,subject_name:string,total:int,present:int,percentage:float}>
     */
    public function getStudentSubjectAttendance(int $studentId, ?int $yearId = null): array
    {
        $year = $this->yearRange($yearId);
        try {
            $q = QueryBuilder::table('subject_attendance')
                ->selectRaw('`subject_attendance`.`subject_id`, `subjects`.`name` AS subject_name, COUNT(*) AS total, SUM(CASE WHEN `subject_attendance`.`status` IN (\'present\',\'late\') THEN 1 ELSE 0 END) AS present')
                ->leftJoin('subjects', 'subject_attendance.subject_id', '=', 'subjects.id')
                ->where('subject_attendance.student_id', $studentId);
            if ($year['id']) {
                $q->where('subject_attendance.academic_year_id', $year['id']);
            }
            $rows = $q->groupBy('subject_attendance.subject_id')->groupBy('subjects.name')->get();
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $total = (int) $r['total'];
            $present = (int) $r['present'];
            $out[] = [
                'subject_id'   => (int) $r['subject_id'],
                'subject_name' => (string) ($r['subject_name'] ?? '—'),
                'total'        => $total,
                'present'      => $present,
                'percentage'   => $total > 0 ? round(($present / $total) * 100, 2) : 0.0,
            ];
        }
        return $out;
    }

    /**
     * Monthly attendance percentages for the year (for the trend chart).
     *
     * @return array<string,float> e.g. ['2026-04' => 92.31, ...]
     */
    public function getStudentMonthlyAttendance(int $studentId, ?int $yearId = null): array
    {
        $year = $this->yearRange($yearId);
        $q = QueryBuilder::table('student_attendance')
            ->selectRaw("DATE_FORMAT(`date`, '%Y-%m') AS ym, COUNT(*) AS total, SUM(CASE WHEN `status` IN ('present','late','half_day') THEN 1 ELSE 0 END) AS attended")
            ->where('student_id', $studentId)
            ->whereRaw("`status` <> 'holiday'");
        if ($year['start'] && $year['end']) {
            $q->whereRaw('`date` BETWEEN ? AND ?', [$year['start'], $year['end']]);
        }
        $rows = $q->groupBy('ym')->get();
        $out = [];
        foreach ($rows as $r) {
            $total = (int) $r['total'];
            $out[(string) $r['ym']] = $total > 0 ? round(((int) $r['attended'] / $total) * 100, 2) : 0.0;
        }
        ksort($out);
        return $out;
    }

    /** @return array<int,array<string,mixed>> Recent daily attendance rows for the year. */
    public function getStudentAttendanceHistory(int $studentId, ?int $yearId = null, int $limit = 60): array
    {
        $year = $this->yearRange($yearId);
        $q = QueryBuilder::table('student_attendance')->where('student_id', $studentId);
        if ($year['start'] && $year['end']) {
            $q->whereRaw('`date` BETWEEN ? AND ?', [$year['start'], $year['end']]);
        }
        return $q->orderBy('date', 'DESC')->limit($limit)->get();
    }
}
