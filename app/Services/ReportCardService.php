<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Exam;
use Core\Database\Connection;
use Core\Database\QueryBuilder;

/**
 * Report card generation with immutable unique IDs, version control and a
 * public QR-verification lookup.
 *
 *  - `report_cards` holds ONE current row per (exam, student) — the latest
 *    version, with a pointer (`rc_uid`, `version`).
 *  - `report_card_versions` is the immutable history; every generation inserts a
 *    new versioned row with its own unique RC id. Nothing is ever overwritten.
 */
final class ReportCardService
{
    /**
     * Aggregate a student's marks for an exam into totals + grade + result.
     * @return array{obtained:float,total:float,percent:float,grade_id:?int,result:string}
     */
    public function compute(int $examId, int $studentId): array
    {
        $r = QueryBuilder::table('exam_marks')
            ->selectRaw('SUM(`exam_marks`.`marks_obtained`) AS obtained, SUM(`exam_schedules`.`full_marks`) AS total')
            ->join('exam_schedules', 'exam_marks.exam_schedule_id', '=', 'exam_schedules.id')
            ->where('exam_schedules.exam_id', $examId)
            ->where('exam_marks.student_id', $studentId)
            ->first();
        $obtained = (float) ($r['obtained'] ?? 0);
        $total = (float) ($r['total'] ?? 0);
        $percent = $total > 0 ? round($obtained / $total * 100, 2) : 0.0;
        return [
            'obtained' => $obtained,
            'total'    => $total,
            'percent'  => $percent,
            'grade_id' => Exam::gradeForPercentage($percent),
            'result'   => $percent >= 33 ? 'pass' : 'fail',
        ];
    }

    /** The current report card row for an exam+student, or null. */
    public function latest(int $examId, int $studentId): ?array
    {
        return QueryBuilder::table('report_cards')
            ->where('exam_id', $examId)->where('student_id', $studentId)->first();
    }

    /**
     * Generate a NEW versioned report card: records an immutable history row and
     * updates the current pointer. Preserves all prior versions.
     * @return string the new RC id
     */
    public function generate(int $examId, int $studentId, ?string $reason = null): string
    {
        $c = $this->compute($examId, $studentId);
        $rcUid = (new IdSequenceService())->generate('RC');
        $now = date('Y-m-d H:i:s');
        $uid = auth()->id();

        Connection::instance()->transaction(function () use ($examId, $studentId, $c, $rcUid, $reason, $now, $uid): void {
            $prevVer = (int) (Connection::instance()->scalar(
                'SELECT COALESCE(MAX(`version`),0) FROM `report_card_versions` WHERE `exam_id` = ? AND `student_id` = ?',
                [$examId, $studentId]
            ) ?? 0);
            $version = $prevVer + 1;

            // Immutable history row.
            QueryBuilder::table('report_card_versions')->insert([
                'rc_uid' => $rcUid, 'exam_id' => $examId, 'student_id' => $studentId, 'version' => $version,
                'total_marks' => $c['total'], 'obtained_marks' => $c['obtained'], 'percentage' => $c['percent'],
                'grade_id' => $c['grade_id'], 'result' => $c['result'], 'generated_by' => $uid,
                'reason' => $reason, 'created_at' => $now,
            ]);

            // Current pointer (one row per exam+student).
            $existing = QueryBuilder::table('report_cards')->where('exam_id', $examId)->where('student_id', $studentId)->first();
            $payload = [
                'rc_uid' => $rcUid, 'version' => $version,
                'total_marks' => $c['total'], 'obtained_marks' => $c['obtained'], 'percentage' => $c['percent'],
                'grade_id' => $c['grade_id'], 'result' => $c['result'], 'generated_by' => $uid, 'reason' => $reason,
                'updated_at' => $now,
            ];
            if ($existing) {
                QueryBuilder::table('report_cards')->where('id', $existing['id'])->update($payload);
            } else {
                QueryBuilder::table('report_cards')->insert($payload + ['exam_id' => $examId, 'student_id' => $studentId, 'created_at' => $now]);
            }
        });

        AuditLogger::log('reportcard.generated', 'report_card', $studentId,
            "Report card {$rcUid} for student {$studentId} (exam {$examId})");
        return $rcUid;
    }

    /**
     * Ensure a report card exists for every student with marks in the exam.
     * Idempotent: existing cards are left untouched (use generate() for a new
     * version). Returns the current cards with student data for rendering.
     *
     * @return array<int,array<string,mixed>>
     */
    public function ensureForExam(int $examId): array
    {
        $students = QueryBuilder::table('exam_marks')
            ->select(['exam_marks.student_id'])
            ->join('exam_schedules', 'exam_marks.exam_schedule_id', '=', 'exam_schedules.id')
            ->where('exam_schedules.exam_id', $examId)
            ->groupBy('exam_marks.student_id')
            ->get();

        foreach ($students as $s) {
            $sid = (int) $s['student_id'];
            if ($this->latest($examId, $sid) === null) {
                $this->generate($examId, $sid);
            }
        }

        return QueryBuilder::table('report_cards')
            ->select(['report_cards.*', 'students.first_name', 'students.last_name', 'students.admission_no'])
            ->join('students', 'report_cards.student_id', '=', 'students.id')
            ->where('report_cards.exam_id', $examId)
            ->orderBy('report_cards.percentage', 'DESC')
            ->get();
    }

    /**
     * Public verification lookup by RC id (against the immutable history).
     * Returns ONLY non-sensitive fields (no marks/percentage), or null.
     *
     * @return array<string,mixed>|null
     */
    public function verify(string $rcUid): ?array
    {
        $row = QueryBuilder::table('report_card_versions')
            ->select([
                'report_card_versions.rc_uid', 'report_card_versions.version', 'report_card_versions.result',
                'report_card_versions.created_at',
                'students.first_name', 'students.last_name', 'students.uid AS student_uid',
                'exams.name AS exam_name', 'academic_years.name AS academic_year', 'classes.name AS class_name',
            ])
            ->join('students', 'report_card_versions.student_id', '=', 'students.id')
            ->leftJoin('exams', 'report_card_versions.exam_id', '=', 'exams.id')
            ->leftJoin('academic_years', 'exams.academic_year_id', '=', 'academic_years.id')
            ->leftJoin('classes', 'students.class_id', '=', 'classes.id')
            ->where('report_card_versions.rc_uid', $rcUid)
            ->first();
        if ($row === null) {
            return null;
        }
        return [
            'rc_uid'        => (string) $row['rc_uid'],
            'version'       => (int) $row['version'],
            'student_name'  => trim((string) ($row['first_name'] . ' ' . $row['last_name'])),
            'student_uid'   => (string) ($row['student_uid'] ?? ''),
            'academic_year' => (string) ($row['academic_year'] ?? ''),
            'class_name'    => (string) ($row['class_name'] ?? ''),
            'exam_name'     => (string) ($row['exam_name'] ?? ''),
            'result'        => (string) $row['result'],
            'published'     => true,
            'issued_on'     => (string) ($row['created_at'] ?? ''),
        ];
    }
}
