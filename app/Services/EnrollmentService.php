<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\StudentEnrollment;
use Core\Database\Connection;
use Core\Database\QueryBuilder;

/**
 * Manages student academic enrollment: automatic, gap-preserving roll numbers
 * scoped to (year, class, section), enrollment, and history-preserving
 * promotion. Roll-number uniqueness is enforced by a DB UNIQUE constraint and
 * a transaction to avoid race conditions.
 */
final class EnrollmentService
{
    /**
     * Next available roll number = MAX(roll_no)+1 within the context.
     * Preserves existing numbers (does not fill gaps by default).
     */
    public function nextRollNumber(int $yearId, int $classId, ?int $sectionId): int
    {
        $q = QueryBuilder::table('student_enrollments')
            ->where('academic_year_id', $yearId)->where('class_id', $classId);
        if ($sectionId) {
            $q->where('section_id', $sectionId);
        } else {
            $q->whereNull('section_id');
        }
        $max = $q->selectRaw('COALESCE(MAX(`roll_no`),0) AS m')->value('m');
        return (int) $max + 1;
    }

    /**
     * Enroll a student for a year/class/section. Auto-assigns a roll number if
     * not given. Also updates the student's denormalised "current" placement.
     *
     * @return array{ok:bool,message:string,roll_no:?int}
     */
    public function enroll(int $studentId, int $yearId, int $classId, ?int $sectionId = null, ?int $rollNo = null): array
    {
        $sectionId = $sectionId ?: null; // 0 / '' => no section
        // One enrollment per student per year.
        $existing = QueryBuilder::table('student_enrollments')
            ->where('academic_year_id', $yearId)->where('student_id', $studentId)->first();
        if ($existing !== null) {
            return ['ok' => false, 'message' => 'Student is already enrolled for this academic year.', 'roll_no' => (int) $existing['roll_no']];
        }

        try {
            $roll = Connection::instance()->transaction(function () use ($studentId, $yearId, $classId, $sectionId, $rollNo): int {
                $roll = $rollNo ?? $this->nextRollNumber($yearId, $classId, $sectionId);
                StudentEnrollment::create([
                    'student_id' => $studentId, 'academic_year_id' => $yearId,
                    'class_id' => $classId, 'section_id' => $sectionId, 'section_key' => (int) ($sectionId ?? 0),
                    'roll_no' => $roll, 'status' => 'active',
                ]);
                QueryBuilder::table('students')->where('id', $studentId)->update([
                    'class_id' => $classId, 'section_id' => $sectionId,
                    'academic_year_id' => $yearId, 'roll_no' => (string) $roll,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                return $roll;
            });
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Enrollment failed (duplicate roll number or constraint). Try again.', 'roll_no' => null];
        }

        AuditLogger::log('student.enrolled', 'student_enrollment', $studentId,
            "Enrolled student {$studentId} into {$classId}-{$sectionId} (year {$yearId}) roll {$roll}");
        return ['ok' => true, 'message' => 'Student enrolled with roll number ' . $roll . '.', 'roll_no' => $roll];
    }

    /**
     * Promote a student to a new year/class/section. Preserves Student ID and
     * all historical enrollment/attendance/marks; marks the old enrollment
     * 'promoted' and creates a fresh enrollment with a new roll number.
     *
     * @return array{ok:bool,message:string,roll_no:?int}
     */
    public function promote(int $studentId, int $fromYearId, int $toYearId, int $toClassId, ?int $toSectionId = null): array
    {
        $toSectionId = $toSectionId ?: null; // 0 / '' => no section
        if ($fromYearId === $toYearId) {
            return ['ok' => false, 'message' => 'Promotion target year must differ from the source year.', 'roll_no' => null];
        }
        $from = QueryBuilder::table('student_enrollments')
            ->where('academic_year_id', $fromYearId)->where('student_id', $studentId)->first();

        try {
            $roll = Connection::instance()->transaction(function () use ($studentId, $from, $fromYearId, $toYearId, $toClassId, $toSectionId): int {
                if ($from !== null) {
                    QueryBuilder::table('student_enrollments')->where('id', $from['id'])
                        ->update(['status' => 'promoted', 'updated_at' => date('Y-m-d H:i:s')]);
                }
                $roll = $this->nextRollNumber($toYearId, $toClassId, $toSectionId);
                StudentEnrollment::create([
                    'student_id' => $studentId, 'academic_year_id' => $toYearId,
                    'class_id' => $toClassId, 'section_id' => $toSectionId, 'section_key' => (int) ($toSectionId ?? 0),
                    'roll_no' => $roll, 'status' => 'active',
                ]);
                // History record (student_promotions) — never overwrites past data.
                QueryBuilder::table('student_promotions')->insert([
                    'student_id' => $studentId,
                    'from_class_id' => $from['class_id'] ?? null,
                    'to_class_id' => $toClassId,
                    'from_section_id' => $from['section_id'] ?? null,
                    'to_section_id' => $toSectionId,
                    'academic_year_id' => $toYearId,
                    'result' => 'promoted',
                    'promoted_by' => auth()->id(),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                // Update denormalised current placement (Student ID unchanged).
                QueryBuilder::table('students')->where('id', $studentId)->update([
                    'class_id' => $toClassId, 'section_id' => $toSectionId,
                    'academic_year_id' => $toYearId, 'roll_no' => (string) $roll,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                return $roll;
            });
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Promotion failed: ' . $e->getMessage(), 'roll_no' => null];
        }

        AuditLogger::log('student.promoted', 'student', $studentId, "Promoted student {$studentId} to year {$toYearId} class {$toClassId}-{$toSectionId}");
        return ['ok' => true, 'message' => 'Student promoted (new roll number ' . $roll . ', history preserved).', 'roll_no' => $roll];
    }
}
