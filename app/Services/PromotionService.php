<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\StudentEnrollment;
use Core\Database\Connection;
use Core\Database\QueryBuilder;

/**
 * Automated student promotion + academic-year rollover.
 *
 * - Next-class mapping is derived from `classes.numeric_level` (n -> n+1); the
 *   highest level graduates.
 * - Eligibility is evaluated from a final/promotion exam's marks (pass = every
 *   scheduled subject >= its pass mark). Without an exam, active students are
 *   eligible by default (admin can still override per student).
 * - Applying a batch reuses the history-preserving EnrollmentService::promote()
 *   inside a transaction and is idempotent (students already enrolled in the
 *   target year are skipped). Historical enrollments/marks are never overwritten.
 */
final class PromotionService
{
    /** The next class up by numeric level, or null if this is the top class. */
    public function nextClassId(int $classId): ?int
    {
        $level = QueryBuilder::table('classes')->where('id', $classId)->value('numeric_level');
        if ($level === null) {
            return null;
        }
        $next = QueryBuilder::table('classes')
            ->where('status', 'active')
            ->whereRaw('`numeric_level` > ?', [(int) $level])
            ->orderBy('numeric_level')
            ->first();
        return $next ? (int) $next['id'] : null;
    }

    /**
     * Build a review list for a source year (optionally a single class), with an
     * eligibility result and a suggested next class for each active student.
     *
     * result: promoted | failed | graduated | no_result
     *
     * @return array<int,array<string,mixed>>
     */
    public function evaluate(int $fromYearId, ?int $classId = null, ?int $examId = null): array
    {
        $q = QueryBuilder::table('student_enrollments')
            ->select([
                'student_enrollments.student_id', 'student_enrollments.class_id', 'student_enrollments.section_id',
                'student_enrollments.roll_no', 'students.first_name', 'students.last_name', 'students.uid',
                'classes.name AS class_name', 'classes.numeric_level',
            ])
            ->join('students', 'student_enrollments.student_id', '=', 'students.id')
            ->leftJoin('classes', 'student_enrollments.class_id', '=', 'classes.id')
            ->where('student_enrollments.academic_year_id', $fromYearId)
            ->where('student_enrollments.status', 'active')
            ->whereNull('students.deleted_at');
        if ($classId) {
            $q->where('student_enrollments.class_id', $classId);
        }
        $rows = $q->orderBy('classes.numeric_level')->orderBy('student_enrollments.roll_no')->get();

        $out = [];
        foreach ($rows as $r) {
            $sid = (int) $r['student_id'];
            $cid = (int) $r['class_id'];
            $next = $this->nextClassId($cid);

            $pct = null;
            $result = 'promoted';
            if ($examId) {
                [$passed, $pct] = $this->examResult($examId, $cid, $sid);
                $result = $passed === null ? 'no_result' : ($passed ? 'promoted' : 'failed');
            }
            if ($result === 'promoted' && $next === null) {
                $result = 'graduated';
            }

            $out[] = [
                'student_id'   => $sid,
                'name'         => trim((string) ($r['first_name'] . ' ' . $r['last_name'])),
                'uid'          => (string) ($r['uid'] ?? ''),
                'from_class_id' => $cid,
                'from_class'   => (string) ($r['class_name'] ?? ''),
                'from_section_id' => $r['section_id'] !== null ? (int) $r['section_id'] : null,
                'roll_no'      => $r['roll_no'],
                'percentage'   => $pct,
                'result'       => $result,
                'next_class_id' => $result === 'graduated' ? null : ($next ?? $cid),
            ];
        }
        return $out;
    }

    /**
     * Exam pass/fail for a student in a class.
     * @return array{0:?bool,1:?float} [passed(null=no marks), percentage]
     */
    private function examResult(int $examId, int $classId, int $studentId): array
    {
        $rows = QueryBuilder::table('exam_marks')
            ->select(['exam_marks.marks_obtained', 'exam_schedules.full_marks', 'exam_schedules.pass_marks'])
            ->join('exam_schedules', 'exam_marks.exam_schedule_id', '=', 'exam_schedules.id')
            ->where('exam_schedules.exam_id', $examId)
            ->where('exam_schedules.class_id', $classId)
            ->where('exam_marks.student_id', $studentId)
            ->get();
        if ($rows === []) {
            return [null, null];
        }
        $obtained = 0.0;
        $total = 0.0;
        $passed = true;
        foreach ($rows as $m) {
            $obtained += (float) $m['marks_obtained'];
            $total += (float) $m['full_marks'];
            if ((float) $m['marks_obtained'] < (float) $m['pass_marks']) {
                $passed = false;
            }
        }
        $pct = $total > 0 ? round($obtained / $total * 100, 2) : 0.0;
        return [$passed, $pct];
    }

    /**
     * Apply promotion decisions into the target year. Transaction-safe per
     * student and idempotent (already-enrolled students are skipped).
     *
     * @param array<int,array{action:string,to_class_id?:int,to_section_id?:int}> $decisions keyed by student_id
     * @return array{promoted:int,repeated:int,graduated:int,skipped:int,errors:int}
     */
    public function apply(int $fromYearId, int $toYearId, array $decisions): array
    {
        $enroll = new EnrollmentService();
        $r = ['promoted' => 0, 'repeated' => 0, 'graduated' => 0, 'skipped' => 0, 'errors' => 0];
        if ($fromYearId === $toYearId) {
            return $r;
        }

        foreach ($decisions as $studentId => $d) {
            $studentId = (int) $studentId;
            $action = (string) ($d['action'] ?? 'hold');

            // Idempotency: never create a second enrollment in the target year.
            $already = QueryBuilder::table('student_enrollments')
                ->where('academic_year_id', $toYearId)->where('student_id', $studentId)->first();
            if ($already !== null || $action === 'hold') {
                $r['skipped']++;
                continue;
            }

            $from = QueryBuilder::table('student_enrollments')
                ->where('academic_year_id', $fromYearId)->where('student_id', $studentId)->first();
            $toSection = (int) ($d['to_section_id'] ?? 0);

            if ($action === 'graduated') {
                // No new enrollment; mark leaving + record graduation, preserve history.
                Connection::instance()->transaction(function () use ($from, $studentId, $toYearId): void {
                    if ($from !== null) {
                        QueryBuilder::table('student_enrollments')->where('id', $from['id'])
                            ->update(['status' => 'graduated', 'updated_at' => date('Y-m-d H:i:s')]);
                    }
                    QueryBuilder::table('student_promotions')->insert([
                        'student_id' => $studentId, 'from_class_id' => $from['class_id'] ?? null,
                        'to_class_id' => $from['class_id'] ?? null, 'from_section_id' => $from['section_id'] ?? null,
                        'to_section_id' => $from['section_id'] ?? null, 'academic_year_id' => $toYearId,
                        'result' => 'graduated', 'promoted_by' => auth()->id(), 'created_at' => date('Y-m-d H:i:s'),
                    ]);
                    QueryBuilder::table('students')->where('id', $studentId)->update([
                        'status' => 'graduated', 'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                });
                AuditLogger::log('student.graduated', 'student', $studentId, "Graduated student {$studentId}");
                $r['graduated']++;
                continue;
            }

            // promote (next class) or repeat (same class): reuse EnrollmentService.
            $toClass = (int) ($d['to_class_id'] ?? 0);
            if ($toClass === 0) {
                $r['errors']++;
                continue;
            }
            $res = $enroll->promote($studentId, $fromYearId, $toYearId, $toClass, $toSection);
            if (!$res['ok']) {
                $r['errors']++;
                continue;
            }
            if ($action === 'repeat') {
                // Correct the recorded result for a retained student.
                $lastId = (int) (Connection::instance()->scalar(
                    'SELECT MAX(`id`) FROM `student_promotions` WHERE `student_id` = ? AND `academic_year_id` = ?',
                    [$studentId, $toYearId]
                ) ?? 0);
                if ($lastId > 0) {
                    QueryBuilder::table('student_promotions')->where('id', $lastId)->update(['result' => 'retained']);
                }
                $r['repeated']++;
            } else {
                $r['promoted']++;
            }
        }

        AuditLogger::log('promotion.batch_applied', 'academic_year', $toYearId,
            "Promotion {$fromYearId}->{$toYearId}: {$r['promoted']} promoted, {$r['repeated']} repeated, {$r['graduated']} graduated, {$r['skipped']} skipped, {$r['errors']} errors");
        return $r;
    }
}
