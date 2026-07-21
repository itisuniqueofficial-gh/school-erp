<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TeacherAssignment;
use Core\Database\Connection;
use Core\Database\QueryBuilder;

/**
 * Assigns teachers to (year, class, section, subject) with duplicate and
 * conflict prevention. Enforced at the application layer AND by a database
 * UNIQUE constraint (uq_teacher_assignment) inside a transaction.
 */
final class TeacherAssignmentService
{
    /** Configurable maximum distinct subject assignments per teacher per year. */
    private const MAX_WORKLOAD = 30;

    /**
     * @return array{ok:bool,message:string,id:?int}
     */
    public function assign(int $yearId, int $classId, int $sectionId, int $subjectId, int $staffId, string $role = 'subject_teacher'): array
    {
        // Academic year must be valid (and normally active).
        $year = QueryBuilder::table('academic_years')->where('id', $yearId)->first();
        if ($year === null) {
            return ['ok' => false, 'message' => 'Invalid academic year.', 'id' => null];
        }

        // Application-level duplicate check (fast, friendly message).
        if (TeacherAssignment::exists($yearId, $classId, $sectionId, $subjectId, $staffId)) {
            return ['ok' => false, 'message' => 'This teacher is already assigned to that subject for this class, section and year.', 'id' => null];
        }

        // Conflict: is another teacher already assigned this subject here?
        $other = QueryBuilder::table('teacher_assignments')
            ->where('academic_year_id', $yearId)->where('class_id', $classId)
            ->where('section_id', $sectionId)->where('subject_id', $subjectId)
            ->where('role', 'subject_teacher')->first();
        if ($other !== null && $role === 'subject_teacher') {
            return ['ok' => false, 'message' => 'Another subject teacher is already assigned to this subject/class/section. Remove them first or use the assistant role.', 'id' => null];
        }

        // Workload guard.
        $load = QueryBuilder::table('teacher_assignments')
            ->where('staff_id', $staffId)->where('academic_year_id', $yearId)->count();
        if ($load >= self::MAX_WORKLOAD) {
            return ['ok' => false, 'message' => 'Teacher workload limit reached (' . self::MAX_WORKLOAD . ' assignments).', 'id' => null];
        }

        try {
            $id = Connection::instance()->transaction(function () use ($yearId, $classId, $sectionId, $subjectId, $staffId, $role): int {
                return TeacherAssignment::create([
                    'academic_year_id' => $yearId, 'class_id' => $classId, 'section_id' => $sectionId,
                    'subject_id' => $subjectId, 'staff_id' => $staffId, 'role' => $role,
                ]);
            });
        } catch (\Throwable $e) {
            // DB UNIQUE constraint caught a concurrent duplicate.
            return ['ok' => false, 'message' => 'Duplicate assignment prevented by the database.', 'id' => null];
        }

        AuditLogger::log('teacher.assigned', 'teacher_assignment', $id,
            "Assigned staff {$staffId} to subject {$subjectId} for class {$classId}-{$sectionId} ({$yearId})");

        return ['ok' => true, 'message' => 'Assignment saved.', 'id' => $id];
    }

    /**
     * Set the class teacher for a (year, class, section) — exactly one allowed.
     *
     * @return array{ok:bool,message:string}
     */
    public function assignClassTeacher(int $yearId, int $classId, int $sectionId, int $staffId): array
    {
        try {
            Connection::instance()->transaction(function () use ($yearId, $classId, $sectionId, $staffId): void {
                $existing = QueryBuilder::table('class_teachers')
                    ->where('academic_year_id', $yearId)->where('class_id', $classId)
                    ->where('section_id', $sectionId)->first();
                if ($existing !== null) {
                    QueryBuilder::table('class_teachers')->where('id', $existing['id'])
                        ->update(['staff_id' => $staffId, 'updated_at' => date('Y-m-d H:i:s')]);
                } else {
                    QueryBuilder::table('class_teachers')->insert([
                        'academic_year_id' => $yearId, 'class_id' => $classId, 'section_id' => $sectionId,
                        'staff_id' => $staffId, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
                // Reflect on the sections table (denormalised current class teacher).
                QueryBuilder::table('sections')->where('id', $sectionId)->update(['class_teacher_id' => $staffId]);
            });
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Could not set class teacher: ' . $e->getMessage()];
        }
        AuditLogger::log('teacher.class_teacher', 'class_teacher', $sectionId, "Class teacher set to staff {$staffId}");
        return ['ok' => true, 'message' => 'Class teacher assigned.'];
    }

    public function remove(int $assignmentId): void
    {
        TeacherAssignment::deleteById($assignmentId);
        AuditLogger::log('teacher.unassigned', 'teacher_assignment', $assignmentId, 'Removed teaching assignment');
    }

    /**
     * Verify a teacher is authorized for a subject/class/section (server-side gate).
     */
    public function canTeach(int $staffId, int $yearId, int $classId, int $sectionId, int $subjectId): bool
    {
        return TeacherAssignment::exists($yearId, $classId, $sectionId, $subjectId, $staffId);
    }
}
