<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\Connection;
use Core\Database\QueryBuilder;

/**
 * Subject-wise (and optionally period-wise) attendance.
 *
 * Uniqueness key: (academic_year, class, section, subject, date, period,
 * student) — enforced by a DB UNIQUE index AND an idempotent upsert here, so a
 * student can never be double-marked for the same subject session.
 */
final class SubjectAttendanceService
{
    /** @return array<int,array<string,mixed>> existing rows keyed by student_id */
    public function forSession(int $yearId, int $classId, ?int $sectionId, int $subjectId, string $date, int $period = 0): array
    {
        $q = QueryBuilder::table('subject_attendance')
            ->where('academic_year_id', $yearId)->where('class_id', $classId)
            ->where('subject_id', $subjectId)->where('date', $date)->where('period', $period);
        if ($sectionId) {
            $q->where('section_id', $sectionId);
        } else {
            $q->whereNull('section_id');
        }
        $rows = $q->get();
        $keyed = [];
        foreach ($rows as $r) {
            $keyed[(int) $r['student_id']] = $r;
        }
        return $keyed;
    }

    /** Idempotent upsert of one student's subject attendance. */
    public function mark(int $studentId, int $yearId, int $classId, ?int $sectionId, int $subjectId, string $date, string $status, ?int $markedBy, int $period = 0): void
    {
        $sectionId = $sectionId ?: null;
        $find = QueryBuilder::table('subject_attendance')
            ->where('academic_year_id', $yearId)->where('class_id', $classId)
            ->where('subject_id', $subjectId)->where('date', $date)->where('period', $period)->where('student_id', $studentId);
        if ($sectionId) {
            $find->where('section_id', $sectionId);
        } else {
            $find->whereNull('section_id');
        }
        $existing = $find->first();

        if ($existing !== null) {
            QueryBuilder::table('subject_attendance')->where('id', $existing['id'])->update([
                'status' => $status, 'marked_by' => $markedBy, 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            return;
        }

        QueryBuilder::table('subject_attendance')->insert([
            'academic_year_id' => $yearId, 'class_id' => $classId, 'section_id' => $sectionId, 'section_key' => (int) ($sectionId ?? 0),
            'subject_id' => $subjectId, 'student_id' => $studentId, 'date' => $date, 'period' => $period,
            'status' => $status, 'marked_by' => $markedBy,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Mark a whole roster in one transaction.
     *
     * @param array<int,string> $statuses studentId => status
     * @return int number marked
     */
    public function markBatch(array $statuses, int $yearId, int $classId, ?int $sectionId, int $subjectId, string $date, ?int $markedBy, int $period = 0): int
    {
        $valid = ['present', 'absent', 'late', 'excused'];
        $sectionId = $sectionId ?: null;
        return Connection::instance()->transaction(function () use ($statuses, $valid, $yearId, $classId, $sectionId, $subjectId, $date, $markedBy, $period): int {
            $n = 0;
            foreach ($statuses as $studentId => $status) {
                if (!in_array($status, $valid, true)) {
                    continue;
                }
                $this->mark((int) $studentId, $yearId, $classId, $sectionId, $subjectId, $date, $status, $markedBy, $period);
                $n++;
            }
            return $n;
        });
    }

    /** Subject attendance percentage for a student (optionally a single subject). */
    public function studentPercentage(int $studentId, ?int $subjectId = null): float
    {
        $q = QueryBuilder::table('subject_attendance')->where('student_id', $studentId);
        if ($subjectId !== null) {
            $q->where('subject_id', $subjectId);
        }
        $total = (clone $q)->count();
        if ($total === 0) {
            return 0.0;
        }
        $present = (clone $q)->whereRaw("`status` IN ('present','late')")->count();
        return round(($present / $total) * 100, 2);
    }
}
