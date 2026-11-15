<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\StudentEnrollment;
use Core\Database\Connection;
use Core\Database\QueryBuilder;

/**
 * Student academic enrollment and class-wise roll numbers.
 *
 * Roll number = serial inside (academic year, class, section). It is NOT the
 * student's identity; the PRN (students.prn) is.
 *
 * Rules:
 *  - Numbers are issued as 1,2,3... under a row lock on `roll_counters`
 *    (the lock anchor and high-water mark), so concurrent admissions cannot
 *    get the same number. The UNIQUE key on student_enrollments is the backstop.
 *  - Numbers are never reused inside a year/class/section: a withdrawn student
 *    keeps their number, a student who moves section leaves a gap behind.
 *  - Every assignment/change is written to student_roll_history and the audit
 *    log. Past years' enrollments are never rewritten by promotion.
 *  - Every public mutator is atomic. When called inside an outer transaction
 *    it uses a SAVEPOINT, so a failure rolls back only its own writes.
 */
final class EnrollmentService
{
    private const DEADLOCK_RETRIES = 3;
    private const MAX_ROLL = 99999;

    // -----------------------------------------------------------------------
    // Read helpers
    // -----------------------------------------------------------------------

    /** Next roll number that would be issued (display only, not reserved). */
    public function nextRollNumber(int $yearId, int $classId, ?int $sectionId): int
    {
        $key = (int) $sectionId;
        $conn = Connection::instance();
        $max = (int) $conn->scalar(
            'SELECT COALESCE(MAX(`roll_no`),0) FROM `student_enrollments` WHERE `academic_year_id`=? AND `class_id`=? AND `section_key`=?',
            [$yearId, $classId, $key]
        );
        $last = (int) $conn->scalar(
            'SELECT `last_roll` FROM `roll_counters` WHERE `academic_year_id`=? AND `class_id`=? AND `section_key`=?',
            [$yearId, $classId, $key]
        );
        return max($max, $last) + 1;
    }

    public function rollTaken(int $yearId, int $classId, ?int $sectionId, int $roll, ?int $exceptStudentId = null): bool
    {
        return $this->holder($yearId, $classId, $sectionId, $roll, $exceptStudentId) !== null;
    }

    /** Roll number a student held in a given academic year (null if not enrolled). */
    public function rollFor(int $studentId, int $yearId): ?int
    {
        $v = QueryBuilder::table('student_enrollments')
            ->where('student_id', $studentId)->where('academic_year_id', $yearId)->value('roll_no');
        return $v === null ? null : (int) $v;
    }

    /** @return array<int,array<string,mixed>> Roll history for a student, newest first. */
    public function history(int $studentId): array
    {
        return QueryBuilder::table('student_roll_history')
            ->select(['student_roll_history.*', 'users.name AS changed_by_name', 'academic_years.name AS year_name',
                'classes.name AS class_name', 'sections.name AS section_name'])
            ->leftJoin('users', 'student_roll_history.changed_by', '=', 'users.id')
            ->leftJoin('academic_years', 'student_roll_history.academic_year_id', '=', 'academic_years.id')
            ->leftJoin('classes', 'student_roll_history.class_id', '=', 'classes.id')
            ->leftJoin('sections', 'student_roll_history.section_id', '=', 'sections.id')
            ->where('student_roll_history.student_id', $studentId)
            ->orderBy('student_roll_history.id', 'DESC')
            ->get();
    }

    // -----------------------------------------------------------------------
    // Enrollment
    // -----------------------------------------------------------------------

    /**
     * Enroll a student for a year/class/section. Auto-assigns the next roll
     * number unless one is given (then it must be free).
     *
     * @return array{ok:bool,message:string,roll_no:?int}
     */
    public function enroll(
        int $studentId, int $yearId, int $classId, ?int $sectionId = null, ?int $rollNo = null,
        string $changeType = 'assigned', ?string $reason = null
    ): array {
        $sectionId = $sectionId ?: null;
        try {
            $res = $this->atomic(function () use ($studentId, $yearId, $classId, $sectionId, $rollNo, $changeType, $reason): array {
                $existing = QueryBuilder::table('student_enrollments')
                    ->where('academic_year_id', $yearId)->where('student_id', $studentId)->first();
                if ($existing !== null) {
                    return ['ok' => false, 'message' => 'Student is already enrolled for this academic year.', 'roll_no' => (int) $existing['roll_no']];
                }
                $roll = $this->issueRoll($yearId, $classId, $sectionId, $rollNo, null);
                if (is_array($roll)) {
                    return $roll;
                }
                $enrId = StudentEnrollment::create([
                    'student_id' => $studentId, 'academic_year_id' => $yearId,
                    'class_id' => $classId, 'section_id' => $sectionId,
                    'roll_no' => $roll, 'status' => 'active',
                ]);
                $this->placeStudent($studentId, $yearId, $classId, $sectionId, $roll);
                $this->logRoll($studentId, $yearId, $classId, $sectionId, null, null, null, $changeType, $reason, $roll);
                AuditLogger::log('student.enrolled', 'student_enrollment', $enrId,
                    "Enrolled student {$studentId} into class {$classId}" . ($sectionId ? "/section {$sectionId}" : '') . " (year {$yearId}) roll {$roll}");
                return ['ok' => true, 'message' => 'Student enrolled with roll number ' . $roll . '.', 'roll_no' => $roll];
            });
        } catch (\Throwable $e) {
            return $this->failure($e, 'Enrollment failed');
        }
        return $res;
    }

    /**
     * Promote a student into a new academic year/class/section. Prior
     * enrollment is kept (status 'promoted') with its roll number intact; a
     * new enrollment with a fresh roll number is created. PRN is untouched.
     *
     * @return array{ok:bool,message:string,roll_no:?int}
     */
    public function promote(int $studentId, int $fromYearId, int $toYearId, int $toClassId, ?int $toSectionId = null): array
    {
        $toSectionId = $toSectionId ?: null;
        if ($fromYearId === $toYearId) {
            return ['ok' => false, 'message' => 'Promotion target year must differ from the source year.', 'roll_no' => null];
        }
        try {
            return $this->atomic(function () use ($studentId, $fromYearId, $toYearId, $toClassId, $toSectionId): array {
                if (QueryBuilder::table('student_enrollments')->where('academic_year_id', $toYearId)->where('student_id', $studentId)->first() !== null) {
                    return ['ok' => false, 'message' => 'Student is already enrolled in the target academic year.', 'roll_no' => null];
                }
                $from = QueryBuilder::table('student_enrollments')
                    ->where('academic_year_id', $fromYearId)->where('student_id', $studentId)->first();

                $roll = $this->issueRoll($toYearId, $toClassId, $toSectionId, null, null);
                if (is_array($roll)) {
                    return $roll;
                }
                if ($from !== null && $from['status'] === 'active') {
                    QueryBuilder::table('student_enrollments')->where('id', $from['id'])
                        ->update(['status' => 'promoted', 'updated_at' => date('Y-m-d H:i:s')]);
                }
                StudentEnrollment::create([
                    'student_id' => $studentId, 'academic_year_id' => $toYearId,
                    'class_id' => $toClassId, 'section_id' => $toSectionId,
                    'roll_no' => $roll, 'status' => 'active',
                ]);
                QueryBuilder::table('student_promotions')->insert([
                    'student_id' => $studentId,
                    'from_class_id' => $from['class_id'] ?? null, 'to_class_id' => $toClassId,
                    'from_section_id' => $from['section_id'] ?? null, 'to_section_id' => $toSectionId,
                    'academic_year_id' => $toYearId, 'result' => 'promoted',
                    'promoted_by' => $this->actor(), 'created_at' => date('Y-m-d H:i:s'),
                ]);
                $this->placeStudent($studentId, $toYearId, $toClassId, $toSectionId, $roll);
                $this->logRoll($studentId, $toYearId, $toClassId, $toSectionId,
                    isset($from['class_id']) ? (int) $from['class_id'] : null,
                    isset($from['section_id']) ? (int) $from['section_id'] : null,
                    $from !== null ? (int) $from['roll_no'] : null, 'promotion', "From year {$fromYearId}", $roll);
                AuditLogger::log('student.promoted', 'student', $studentId,
                    "Promoted student {$studentId} to year {$toYearId} class {$toClassId}" . ($toSectionId ? "/section {$toSectionId}" : '') . " roll {$roll}");
                return ['ok' => true, 'message' => 'Student promoted (new roll number ' . $roll . ', history preserved).', 'roll_no' => $roll];
            });
        } catch (\Throwable $e) {
            return $this->failure($e, 'Promotion failed');
        }
    }

    /**
     * Move a student to another class/section inside the same academic year.
     * A new roll number is issued in the destination; the old number stays in
     * student_roll_history and is not reused in the origin section.
     *
     * @param string $changeType 'transfer' (section/class change) or 'promotion' (same-year class move)
     * @return array{ok:bool,message:string,roll_no:?int}
     */
    public function transfer(int $studentId, int $yearId, int $classId, ?int $sectionId, string $changeType = 'transfer', ?string $reason = null): array
    {
        $sectionId = $sectionId ?: null;
        try {
            return $this->atomic(function () use ($studentId, $yearId, $classId, $sectionId, $changeType, $reason): array {
                $enr = QueryBuilder::table('student_enrollments')
                    ->where('academic_year_id', $yearId)->where('student_id', $studentId)->first();
                if ($enr === null) {
                    return $this->enroll($studentId, $yearId, $classId, $sectionId, null, 'assigned', $reason);
                }
                if ((int) $enr['class_id'] === $classId && ((int) ($enr['section_id'] ?? 0)) === (int) $sectionId) {
                    return ['ok' => true, 'message' => 'Already in this class/section; roll number unchanged.', 'roll_no' => (int) $enr['roll_no']];
                }
                if ($enr['status'] !== 'active') {
                    return ['ok' => false, 'message' => 'Only an active enrollment can be moved (current status: ' . $enr['status'] . ').', 'roll_no' => (int) $enr['roll_no']];
                }
                $roll = $this->issueRoll($yearId, $classId, $sectionId, null, null);
                if (is_array($roll)) {
                    return $roll;
                }
                QueryBuilder::table('student_enrollments')->where('id', $enr['id'])->update([
                    'class_id' => $classId, 'section_id' => $sectionId, 'roll_no' => $roll,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                if ($changeType === 'promotion') {
                    QueryBuilder::table('student_promotions')->insert([
                        'student_id' => $studentId, 'from_class_id' => $enr['class_id'], 'to_class_id' => $classId,
                        'from_section_id' => $enr['section_id'], 'to_section_id' => $sectionId,
                        'academic_year_id' => $yearId, 'result' => 'promoted',
                        'promoted_by' => $this->actor(), 'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
                $this->placeStudent($studentId, $yearId, $classId, $sectionId, $roll, true);
                $this->logRoll($studentId, $yearId, $classId, $sectionId, (int) $enr['class_id'],
                    $enr['section_id'] !== null ? (int) $enr['section_id'] : null, (int) $enr['roll_no'], $changeType, $reason, $roll);
                AuditLogger::log('student.' . ($changeType === 'promotion' ? 'promoted' : 'transferred'), 'student', $studentId,
                    "Moved student {$studentId} class {$enr['class_id']}/{$enr['section_id']} -> {$classId}/{$sectionId} (year {$yearId}); roll {$enr['roll_no']} -> {$roll}");
                return ['ok' => true, 'message' => 'Moved; new roll number ' . $roll . '.', 'roll_no' => $roll];
            });
        } catch (\Throwable $e) {
            return $this->failure($e, 'Move failed');
        }
    }

    /**
     * Mark the enrollment as no longer active (left / transferred out /
     * graduated). The roll number is kept as history and stays reserved.
     *
     * @return array{ok:bool,message:string,roll_no:?int}
     */
    public function withdraw(int $studentId, int $yearId, string $status = 'left', ?string $reason = null): array
    {
        if (!in_array($status, ['left', 'transferred', 'graduated'], true)) {
            return ['ok' => false, 'message' => 'Invalid withdrawal status.', 'roll_no' => null];
        }
        try {
            return $this->atomic(function () use ($studentId, $yearId, $status, $reason): array {
                $enr = QueryBuilder::table('student_enrollments')
                    ->where('academic_year_id', $yearId)->where('student_id', $studentId)->first();
                if ($enr === null) {
                    return ['ok' => true, 'message' => 'No enrollment for this year; nothing to change.', 'roll_no' => null];
                }
                if ($enr['status'] !== 'active') {
                    return ['ok' => true, 'message' => 'Enrollment is already ' . $enr['status'] . '.', 'roll_no' => (int) $enr['roll_no']];
                }
                QueryBuilder::table('student_enrollments')->where('id', $enr['id'])
                    ->update(['status' => $status, 'updated_at' => date('Y-m-d H:i:s')]);
                $this->logRoll($studentId, $yearId, (int) $enr['class_id'], $enr['section_id'] !== null ? (int) $enr['section_id'] : null,
                    null, null, (int) $enr['roll_no'], 'withdrawn', $reason ?? "Status: {$status}", (int) $enr['roll_no']);
                AuditLogger::log('student.withdrawn', 'student', $studentId,
                    "Enrollment (year {$yearId}) set to {$status}; roll {$enr['roll_no']} retained as history");
                return ['ok' => true, 'message' => 'Enrollment closed; roll number ' . $enr['roll_no'] . ' kept in history.', 'roll_no' => (int) $enr['roll_no']];
            });
        } catch (\Throwable $e) {
            return $this->failure($e, 'Withdrawal failed');
        }
    }

    /**
     * Re-open a withdrawn enrollment with the roll number the student already
     * holds (it was never released, so there is no conflict).
     *
     * @return array{ok:bool,message:string,roll_no:?int}
     */
    public function reactivate(int $studentId, int $yearId): array
    {
        try {
            return $this->atomic(function () use ($studentId, $yearId): array {
                $enr = QueryBuilder::table('student_enrollments')
                    ->where('academic_year_id', $yearId)->where('student_id', $studentId)->first();
                if ($enr === null || !in_array($enr['status'], ['left', 'transferred'], true)) {
                    return ['ok' => true, 'message' => 'Nothing to reactivate.', 'roll_no' => $enr !== null ? (int) $enr['roll_no'] : null];
                }
                QueryBuilder::table('student_enrollments')->where('id', $enr['id'])
                    ->update(['status' => 'active', 'updated_at' => date('Y-m-d H:i:s')]);
                $this->logRoll($studentId, $yearId, (int) $enr['class_id'], $enr['section_id'] !== null ? (int) $enr['section_id'] : null,
                    null, null, (int) $enr['roll_no'], 'reactivated', null, (int) $enr['roll_no']);
                AuditLogger::log('student.reactivated', 'student', $studentId, "Enrollment (year {$yearId}) reactivated with roll {$enr['roll_no']}");
                return ['ok' => true, 'message' => 'Enrollment reactivated with roll number ' . $enr['roll_no'] . '.', 'roll_no' => (int) $enr['roll_no']];
            });
        } catch (\Throwable $e) {
            return $this->failure($e, 'Reactivation failed');
        }
    }

    /**
     * Keep the enrollment in step with students.status (used by the student
     * edit form). Idempotent.
     */
    public function syncWithStudentStatus(int $studentId, string $studentStatus): void
    {
        $student = QueryBuilder::table('students')->where('id', $studentId)->first();
        $yearId = (int) ($student['academic_year_id'] ?? 0);
        if ($student === null || $yearId === 0) {
            return;
        }
        match ($studentStatus) {
            'transferred' => $this->withdraw($studentId, $yearId, 'transferred', 'Student status set to transferred'),
            'inactive'    => $this->withdraw($studentId, $yearId, 'left', 'Student status set to inactive'),
            'graduated', 'alumni' => $this->withdraw($studentId, $yearId, 'graduated', 'Student status set to ' . $studentStatus),
            'active'      => $this->reactivate($studentId, $yearId),
            default       => null,
        };
    }

    // -----------------------------------------------------------------------
    // Administrative roll changes
    // -----------------------------------------------------------------------

    /**
     * Manually set a roll number (null = next free number). Rejects duplicates
     * within the year/class/section and records history + audit entry.
     *
     * @return array{ok:bool,message:string,roll_no:?int}
     */
    public function changeRoll(int $studentId, int $yearId, ?int $newRoll, string $reason, string $changeType = 'manual'): array
    {
        $reason = trim($reason);
        if ($changeType === 'manual' && $reason === '') {
            return ['ok' => false, 'message' => 'A reason is required for a manual roll-number change.', 'roll_no' => null];
        }
        try {
            return $this->atomic(function () use ($studentId, $yearId, $newRoll, $reason, $changeType): array {
                $enr = QueryBuilder::table('student_enrollments')
                    ->where('academic_year_id', $yearId)->where('student_id', $studentId)->first();
                if ($enr === null) {
                    return ['ok' => false, 'message' => 'Student has no enrollment in this academic year.', 'roll_no' => null];
                }
                $classId = (int) $enr['class_id'];
                $sectionId = $enr['section_id'] !== null ? (int) $enr['section_id'] : null;
                $old = (int) $enr['roll_no'];
                if ($newRoll !== null && $newRoll === $old) {
                    return ['ok' => true, 'message' => 'Roll number unchanged.', 'roll_no' => $old];
                }
                $roll = $this->issueRoll($yearId, $classId, $sectionId, $newRoll, $studentId);
                if (is_array($roll)) {
                    return $roll;
                }
                QueryBuilder::table('student_enrollments')->where('id', $enr['id'])
                    ->update(['roll_no' => $roll, 'updated_at' => date('Y-m-d H:i:s')]);
                $this->placeStudent($studentId, $yearId, $classId, $sectionId, $roll, true);
                $this->logRoll($studentId, $yearId, $classId, $sectionId, $classId, $sectionId, $old, $changeType, $reason, $roll);
                AuditLogger::log('student.roll_changed', 'student', $studentId,
                    "Roll number {$old} -> {$roll} (year {$yearId}, class {$classId}" . ($sectionId ? "/section {$sectionId}" : '') . "). Reason: {$reason}");
                return ['ok' => true, 'message' => "Roll number changed from {$old} to {$roll}.", 'roll_no' => $roll];
            });
        } catch (\Throwable $e) {
            return $this->failure($e, 'Roll number change failed');
        }
    }

    /**
     * Renumber ACTIVE students of one class/section 1..n (by name or by
     * existing roll order), skipping numbers held by withdrawn/promoted
     * enrollments of the same year. Explicit admin action, current year only.
     *
     * @return array{ok:bool,message:string,changed:int}
     */
    public function resequence(int $yearId, int $classId, ?int $sectionId, string $reason, string $order = 'name'): array
    {
        $sectionId = $sectionId ?: null;
        $key = (int) $sectionId;
        if (trim($reason) === '') {
            return ['ok' => false, 'message' => 'A reason is required to resequence roll numbers.', 'changed' => 0];
        }
        $isCurrent = (int) QueryBuilder::table('academic_years')->where('id', $yearId)->value('is_current') === 1;
        if (!$isCurrent) {
            return ['ok' => false, 'message' => 'Roll numbers of a past academic year are history and cannot be resequenced.', 'changed' => 0];
        }
        try {
            return $this->atomic(function () use ($yearId, $classId, $sectionId, $key, $reason, $order): array {
                $conn = Connection::instance();
                $this->lockCounter($yearId, $classId, $key);
                $orderSql = $order === 'roll' ? 'e.`roll_no`' : 's.`first_name`, s.`last_name`, s.`id`';
                $active = $conn->select(
                    "SELECT e.`id`, e.`student_id`, e.`roll_no` FROM `student_enrollments` e JOIN `students` s ON s.`id` = e.`student_id`
                     WHERE e.`academic_year_id`=? AND e.`class_id`=? AND e.`section_key`=? AND e.`status`='active'
                     ORDER BY {$orderSql} FOR UPDATE",
                    [$yearId, $classId, $key]
                );
                $held = array_map('intval', array_column($conn->select(
                    "SELECT `roll_no` FROM `student_enrollments` WHERE `academic_year_id`=? AND `class_id`=? AND `section_key`=? AND `status`<>'active'",
                    [$yearId, $classId, $key]
                ), 'roll_no'));
                $heldSet = array_flip($held);

                $targets = [];
                $n = 1;
                foreach ($active as $row) {
                    while (isset($heldSet[$n])) {
                        $n++;
                    }
                    $targets[(int) $row['id']] = $n++;
                }
                $changes = array_filter($active, static fn ($r) => $targets[(int) $r['id']] !== (int) $r['roll_no']);
                if ($changes === []) {
                    return ['ok' => true, 'message' => 'Roll numbers are already sequential.', 'changed' => 0];
                }
                // Phase 1: park on temporary numbers above everything (avoids transient duplicates).
                $base = max(max($targets), (int) $conn->scalar(
                    'SELECT COALESCE(MAX(`roll_no`),0) FROM `student_enrollments` WHERE `academic_year_id`=? AND `class_id`=? AND `section_key`=?',
                    [$yearId, $classId, $key]
                ), (int) $conn->scalar('SELECT `last_roll` FROM `roll_counters` WHERE `academic_year_id`=? AND `class_id`=? AND `section_key`=?', [$yearId, $classId, $key])) + 1000;
                $i = 0;
                foreach ($changes as $row) {
                    $conn->statement('UPDATE `student_enrollments` SET `roll_no`=? WHERE `id`=?', [$base + (++$i), (int) $row['id']]);
                }
                // Phase 2: final numbers.
                foreach ($changes as $row) {
                    $new = $targets[(int) $row['id']];
                    $conn->statement('UPDATE `student_enrollments` SET `roll_no`=?, `updated_at`=? WHERE `id`=?', [$new, date('Y-m-d H:i:s'), (int) $row['id']]);
                    $this->placeStudent((int) $row['student_id'], $yearId, $classId, $sectionId, $new, true);
                    $this->logRoll((int) $row['student_id'], $yearId, $classId, $sectionId, $classId, $sectionId, (int) $row['roll_no'], 'resequence', $reason, $new);
                }
                $this->bumpCounter($yearId, $classId, $key, max($targets));
                AuditLogger::log('student.rolls_resequenced', 'academic_year', $yearId,
                    count($changes) . " roll number(s) resequenced in class {$classId}" . ($sectionId ? "/section {$sectionId}" : '') . ". Reason: {$reason}");
                return ['ok' => true, 'message' => count($changes) . ' roll number(s) resequenced.', 'changed' => count($changes)];
            });
        } catch (\Throwable $e) {
            $f = $this->failure($e, 'Resequence failed');
            return ['ok' => false, 'message' => $f['message'], 'changed' => 0];
        }
    }

    // -----------------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------------

    /**
     * Reserve a roll number inside the open transaction.
     *
     * @return int|array{ok:bool,message:string,roll_no:?int} roll, or a failure result
     */
    private function issueRoll(int $yearId, int $classId, ?int $sectionId, ?int $requested, ?int $exceptStudentId): int|array
    {
        $key = (int) $sectionId;
        $current = $this->lockCounter($yearId, $classId, $key);

        if ($requested === null) {
            $roll = $current + 1;
        } else {
            if ($requested < 1 || $requested > self::MAX_ROLL) {
                return ['ok' => false, 'message' => 'Roll number must be a whole number between 1 and ' . self::MAX_ROLL . '.', 'roll_no' => null];
            }
            $holder = $this->holder($yearId, $classId, $sectionId, $requested, $exceptStudentId);
            if ($holder !== null) {
                $who = trim((string) $holder['first_name'] . ' ' . (string) $holder['last_name']);
                return ['ok' => false, 'message' => "Roll number {$requested} is already used by {$who} ({$holder['status']}) in this class/section for this academic year.", 'roll_no' => null];
            }
            $roll = $requested;
        }
        $this->bumpCounter($yearId, $classId, $key, $roll);
        return $roll;
    }

    /** Create/lock the counter row; returns the highest roll known (counter vs. data). */
    private function lockCounter(int $yearId, int $classId, int $key): int
    {
        $conn = Connection::instance();
        $conn->statement(
            $conn->dialect()->upsertNoop('roll_counters', ['academic_year_id', 'class_id', 'section_key', 'last_roll', 'updated_at'], ['academic_year_id', 'class_id', 'section_key']),
            [$yearId, $classId, $key, 0, date('Y-m-d H:i:s')]
        );
        $last = (int) $conn->scalar(
            'SELECT `last_roll` FROM `roll_counters` WHERE `academic_year_id`=? AND `class_id`=? AND `section_key`=? FOR UPDATE',
            [$yearId, $classId, $key]
        );
        // Read the real data too, in case rows were written without the counter. MySQL
        // (REPEATABLE READ) needs a locking read to see the latest committed rows; PostgreSQL
        // (READ COMMITTED) already does, and rejects FOR UPDATE on an aggregate.
        $max = (int) $conn->scalar(
            'SELECT COALESCE(MAX(`roll_no`),0) FROM `student_enrollments` WHERE `academic_year_id`=? AND `class_id`=? AND `section_key`=?'
            . ($conn->isPostgres() ? '' : ' FOR UPDATE'),
            [$yearId, $classId, $key]
        );
        return max($last, $max);
    }

    private function bumpCounter(int $yearId, int $classId, int $key, int $roll): void
    {
        Connection::instance()->statement(
            'UPDATE `roll_counters` SET `last_roll` = GREATEST(`last_roll`, ?), `updated_at` = ? WHERE `academic_year_id`=? AND `class_id`=? AND `section_key`=?',
            [$roll, date('Y-m-d H:i:s'), $yearId, $classId, $key]
        );
    }

    /** @return array<string,mixed>|null */
    private function holder(int $yearId, int $classId, ?int $sectionId, int $roll, ?int $exceptStudentId): ?array
    {
        $sql = 'SELECT e.`student_id`, e.`status`, s.`first_name`, s.`last_name` FROM `student_enrollments` e
                LEFT JOIN `students` s ON s.`id` = e.`student_id`
                WHERE e.`academic_year_id`=? AND e.`class_id`=? AND e.`section_key`=? AND e.`roll_no`=?';
        $args = [$yearId, $classId, (int) $sectionId, $roll];
        if ($exceptStudentId !== null) {
            $sql .= ' AND e.`student_id` <> ?';
            $args[] = $exceptStudentId;
        }
        return Connection::instance()->selectOne($sql . ' LIMIT 1', $args);
    }

    /** Update the denormalised "current placement" on the student row. */
    private function placeStudent(int $studentId, int $yearId, int $classId, ?int $sectionId, int $roll, bool $onlyIfCurrentYear = false): void
    {
        if ($onlyIfCurrentYear) {
            $cur = QueryBuilder::table('students')->where('id', $studentId)->value('academic_year_id');
            if ($cur !== null && (int) $cur !== $yearId) {
                return; // editing an old year must not move the student's current placement
            }
        }
        QueryBuilder::table('students')->where('id', $studentId)->update([
            'class_id' => $classId, 'section_id' => $sectionId,
            'academic_year_id' => $yearId, 'roll_no' => (string) $roll,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function logRoll(
        int $studentId, ?int $yearId, ?int $classId, ?int $sectionId, ?int $oldClass, ?int $oldSection,
        ?int $oldRoll, string $type, ?string $reason, ?int $newRoll = null
    ): void {
        $prn = QueryBuilder::table('students')->where('id', $studentId)->value('prn');
        QueryBuilder::table('student_roll_history')->insert([
            'student_id' => $studentId, 'prn' => $prn,
            'academic_year_id' => $yearId, 'class_id' => $classId, 'section_id' => $sectionId,
            'old_class_id' => $oldClass, 'old_section_id' => $oldSection,
            'old_roll_no' => $oldRoll, 'new_roll_no' => $newRoll,
            'change_type' => $type, 'reason' => $reason !== null ? substr($reason, 0, 255) : null,
            'changed_by' => $this->actor(), 'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Run atomically: own transaction (with deadlock retry) or, when a
     * transaction is already open, a SAVEPOINT that is rolled back on error.
     */
    private function atomic(callable $fn): mixed
    {
        $conn = Connection::instance();
        $pdo = $conn->pdo();
        if ($pdo->inTransaction()) {
            $sp = 'sp_' . bin2hex(random_bytes(4));
            $pdo->exec("SAVEPOINT {$sp}");
            try {
                $res = $fn();
                $pdo->exec("RELEASE SAVEPOINT {$sp}");
                return $res;
            } catch (\Throwable $e) {
                $pdo->exec("ROLLBACK TO SAVEPOINT {$sp}");
                throw $e;
            }
        }
        for ($attempt = 1; ; $attempt++) {
            try {
                return $conn->transaction($fn);
            } catch (\PDOException $e) {
                $code = (int) ($e->errorInfo[1] ?? 0);
                if (($code === 1213 || $code === 1205) && $attempt < self::DEADLOCK_RETRIES) {
                    usleep(random_int(10_000, 60_000));
                    continue;
                }
                throw $e;
            }
        }
    }

    /** @return array{ok:bool,message:string,roll_no:?int} */
    private function failure(\Throwable $e, string $prefix): array
    {
        $msg = $e->getMessage();
        if (str_contains($msg, 'uq_enroll_rollno')) {
            $msg = 'that roll number was just taken by another admission; please retry';
        } elseif (str_contains($msg, 'uq_enroll_student_year')) {
            $msg = 'student is already enrolled for this academic year';
        }
        try {
            logger()->warning("{$prefix}: " . $e->getMessage());
        } catch (\Throwable) {
        }
        return ['ok' => false, 'message' => "{$prefix}: {$msg}", 'roll_no' => null];
    }

    private function actor(): ?int
    {
        try {
            return auth()->id();
        } catch (\Throwable) {
            return null;
        }
    }
}
