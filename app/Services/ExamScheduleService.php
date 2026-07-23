<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\Connection;
use Core\Database\QueryBuilder;

/**
 * Class-wise exam scheduling engine.
 *
 * Builds an exam's subject schedule from the class's assigned subjects
 * (`class_subjects`), one row per subject in `exam_schedules` (which already
 * carries a UNIQUE(exam, class, subject) constraint). Saving is idempotent —
 * existing rows are updated, not duplicated — and wrapped in a transaction.
 * Also provides scheduling-conflict detection and copy-from-another-exam.
 */
final class ExamScheduleService
{
    /** Subjects assigned to a class, with default marks. @return array<int,array<string,mixed>> */
    public function classSubjects(int $classId): array
    {
        return QueryBuilder::table('class_subjects')
            ->select([
                'class_subjects.subject_id', 'subjects.name', 'subjects.code',
                'class_subjects.full_marks', 'class_subjects.pass_marks',
            ])
            ->join('subjects', 'class_subjects.subject_id', '=', 'subjects.id')
            ->where('class_subjects.class_id', $classId)
            ->where('class_subjects.status', 'active')
            ->orderBy('subjects.name')->get();
    }

    /** Current schedule rows for an exam + class. @return array<int,array<string,mixed>> */
    public function currentSchedule(int $examId, int $classId): array
    {
        return QueryBuilder::table('exam_schedules')
            ->select([
                'exam_schedules.id', 'exam_schedules.subject_id', 'exam_schedules.exam_date',
                'exam_schedules.start_time', 'exam_schedules.end_time',
                'exam_schedules.full_marks', 'exam_schedules.pass_marks',
                'subjects.name AS subject', 'subjects.code',
            ])
            ->leftJoin('subjects', 'exam_schedules.subject_id', '=', 'subjects.id')
            ->where('exam_schedules.exam_id', $examId)
            ->where('exam_schedules.class_id', $classId)
            ->orderBy('exam_schedules.exam_date')->get();
    }

    /**
     * Save (upsert) the per-subject schedule for an exam + class. Only subjects
     * assigned to the class are accepted. Idempotent: re-saving updates rows.
     *
     * @param array<int,array{date?:string,start?:string,end?:string,full_marks?:mixed,pass_marks?:mixed,include?:mixed}> $rows keyed by subject_id
     * @return array{created:int,updated:int,removed:int}
     */
    public function saveSchedule(int $examId, int $classId, array $rows): array
    {
        $allowed = array_map(static fn ($s) => (int) $s['subject_id'], $this->classSubjects($classId));
        $created = 0; $updated = 0; $removed = 0;
        $now = date('Y-m-d H:i:s');

        Connection::instance()->transaction(function () use ($examId, $classId, $rows, $allowed, &$created, &$updated, &$removed, $now): void {
            foreach ($allowed as $subjectId) {
                $row = $rows[$subjectId] ?? null;
                $existing = QueryBuilder::table('exam_schedules')
                    ->where('exam_id', $examId)->where('class_id', $classId)->where('subject_id', $subjectId)->first();

                // Uncheck / no data -> remove any existing row for that subject.
                if ($row === null || empty($row['include'])) {
                    if ($existing !== null) {
                        QueryBuilder::table('exam_schedules')->where('id', (int) $existing['id'])->delete();
                        $removed++;
                    }
                    continue;
                }

                $payload = [
                    'exam_date'  => trim((string) ($row['date'] ?? '')) ?: null,
                    'start_time' => trim((string) ($row['start'] ?? '')) ?: null,
                    'end_time'   => trim((string) ($row['end'] ?? '')) ?: null,
                    'full_marks' => (float) ($row['full_marks'] ?? 100),
                    'pass_marks' => (float) ($row['pass_marks'] ?? 33),
                    'updated_at' => $now,
                ];
                if ($existing !== null) {
                    QueryBuilder::table('exam_schedules')->where('id', (int) $existing['id'])->update($payload);
                    $updated++;
                } else {
                    QueryBuilder::table('exam_schedules')->insert($payload + [
                        'exam_id' => $examId, 'class_id' => $classId, 'subject_id' => $subjectId, 'created_at' => $now,
                    ]);
                    $created++;
                }
            }
        });

        AuditLogger::log('exam.scheduled', 'exam', $examId, "Class {$classId} schedule: +{$created} ~{$updated} -{$removed}");
        return ['created' => $created, 'updated' => $updated, 'removed' => $removed];
    }

    /**
     * Detect scheduling conflicts within an exam + class: subjects sharing the
     * same date AND start time.
     *
     * @return array<int,array<string,mixed>>
     */
    public function conflicts(int $examId, int $classId): array
    {
        $bySlot = [];
        foreach ($this->currentSchedule($examId, $classId) as $r) {
            $date = trim((string) ($r['exam_date'] ?? ''));
            $start = trim((string) ($r['start_time'] ?? ''));
            if ($date === '' || $start === '') {
                continue;
            }
            $slot = $date . ' ' . $start;
            $bySlot[$slot][] = (string) ($r['subject'] ?? ('Subject #' . $r['subject_id']));
        }
        $conflicts = [];
        foreach ($bySlot as $slot => $subjects) {
            if (count($subjects) > 1) {
                $conflicts[] = ['slot' => $slot, 'subjects' => $subjects];
            }
        }
        return $conflicts;
    }

    /**
     * Copy a class's subject schedule from one exam into another (idempotent).
     * @return array{copied:int}
     */
    public function copyFrom(int $fromExamId, int $toExamId, int $classId): array
    {
        $copied = 0;
        $now = date('Y-m-d H:i:s');
        Connection::instance()->transaction(function () use ($fromExamId, $toExamId, $classId, &$copied, $now): void {
            foreach ($this->currentSchedule($fromExamId, $classId) as $r) {
                $subjectId = (int) $r['subject_id'];
                $exists = QueryBuilder::table('exam_schedules')
                    ->where('exam_id', $toExamId)->where('class_id', $classId)->where('subject_id', $subjectId)->first();
                $payload = [
                    'exam_date' => $r['exam_date'], 'start_time' => $r['start_time'], 'end_time' => $r['end_time'],
                    'full_marks' => $r['full_marks'], 'pass_marks' => $r['pass_marks'], 'updated_at' => $now,
                ];
                if ($exists !== null) {
                    QueryBuilder::table('exam_schedules')->where('id', (int) $exists['id'])->update($payload);
                } else {
                    QueryBuilder::table('exam_schedules')->insert($payload + [
                        'exam_id' => $toExamId, 'class_id' => $classId, 'subject_id' => $subjectId, 'created_at' => $now,
                    ]);
                }
                $copied++;
            }
        });
        AuditLogger::log('exam.schedule_copied', 'exam', $toExamId, "Copied {$copied} subject(s) from exam {$fromExamId} (class {$classId})");
        return ['copied' => $copied];
    }

    /** Exam-level date sanity: end must not be before start. */
    public function datesValid(?string $start, ?string $end): bool
    {
        $start = trim((string) $start);
        $end = trim((string) $end);
        if ($start === '' || $end === '') {
            return true; // optional
        }
        return strtotime($end) !== false && strtotime($start) !== false && strtotime($end) >= strtotime($start);
    }

    /** True if an exam with the same name already exists in the year + type scope. */
    public function duplicateExists(string $name, ?int $yearId, int $examTypeId, ?int $ignoreId = null): bool
    {
        $q = QueryBuilder::table('exams')
            ->whereRaw('LOWER(TRIM(`name`)) = ?', [strtolower(trim($name))])
            ->where('exam_type_id', $examTypeId);
        if ($yearId) {
            $q->where('academic_year_id', $yearId);
        }
        if ($ignoreId) {
            $q->where('id', '!=', $ignoreId);
        }
        return $q->count() > 0;
    }
}
