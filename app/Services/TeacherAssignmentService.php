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
    public function assign(int $yearId, int $classId, ?int $sectionId, int $subjectId, int $staffId, string $role = 'subject_teacher'): array
    {
        $sectionId = $sectionId ?: null; // no-section class => null
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
            ->where('subject_id', $subjectId)->where('role', 'subject_teacher');
        if ($sectionId) {
            $other->where('section_id', $sectionId);
        } else {
            $other->whereNull('section_id');
        }
        $other = $other->first();
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
                $aid = TeacherAssignment::create([
                    'academic_year_id' => $yearId, 'class_id' => $classId, 'section_id' => $sectionId, 'section_key' => (int) ($sectionId ?? 0),
                    'subject_id' => $subjectId, 'staff_id' => $staffId, 'role' => $role,
                ]);
                // Open the first ownership period in the history timeline.
                QueryBuilder::table('teacher_assignment_history')->insert([
                    'assignment_id' => $aid, 'staff_id' => $staffId, 'role' => $role,
                    'start_date' => date('Y-m-d'), 'status' => 'active',
                    'changed_by' => auth()->id(), 'created_at' => date('Y-m-d H:i:s'),
                ]);
                return $aid;
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
                        'academic_year_id' => $yearId, 'class_id' => $classId, 'section_id' => $sectionId, 'section_key' => (int) ($sectionId ?? 0),
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
     * One-click reassignment with an effective date. Preserves history: the
     * current teacher's ownership period is closed and the new teacher's period
     * opens, while historical attendance/marks (owned by acting users) are
     * untouched. The single teacher_assignments row is updated in place so its
     * UNIQUE key still guarantees no duplicate active assignment.
     *
     * @return array{ok:bool,message:string}
     */
    public function reassign(int $assignmentId, int $newStaffId, string $reason, ?string $effectiveDate = null): array
    {
        $a = QueryBuilder::table('teacher_assignments')->where('id', $assignmentId)->first();
        if ($a === null) {
            return ['ok' => false, 'message' => 'Assignment not found.'];
        }
        $oldStaff = (int) $a['staff_id'];
        if ($oldStaff === $newStaffId) {
            return ['ok' => false, 'message' => 'That teacher is already assigned here.'];
        }
        if (QueryBuilder::table('staff')->where('id', $newStaffId)->whereNull('deleted_at')->first() === null) {
            return ['ok' => false, 'message' => 'The selected teacher is invalid or inactive.'];
        }
        if (trim($reason) === '') {
            return ['ok' => false, 'message' => 'A reason for the change is required.'];
        }
        $eff = $effectiveDate ?: date('Y-m-d');
        $role = (string) ($a['role'] ?? 'subject_teacher');

        try {
            Connection::instance()->transaction(function () use ($assignmentId, $oldStaff, $newStaffId, $reason, $eff, $role, $a): void {
                $reasonTxt = mb_substr($reason, 0, 255);
                // Close the current active ownership period. Legacy assignments
                // created before history tracking (e.g. via seeds) have none, so
                // synthesise the outgoing teacher's closed period from the
                // assignment's creation date.
                $closed = QueryBuilder::table('teacher_assignment_history')
                    ->where('assignment_id', $assignmentId)->where('status', 'active')
                    ->update(['end_date' => $eff, 'status' => 'ended', 'reason' => $reasonTxt]);
                if ($closed === 0) {
                    QueryBuilder::table('teacher_assignment_history')->insert([
                        'assignment_id' => $assignmentId, 'staff_id' => $oldStaff, 'role' => $role,
                        'start_date' => substr((string) ($a['created_at'] ?? date('Y-m-d')), 0, 10),
                        'end_date' => $eff, 'status' => 'ended', 'reason' => $reasonTxt,
                        'changed_by' => auth()->id(), 'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
                // Move the current assignment to the new teacher (UNIQUE key still holds).
                QueryBuilder::table('teacher_assignments')->where('id', $assignmentId)
                    ->update(['staff_id' => $newStaffId, 'updated_at' => date('Y-m-d H:i:s')]);
                // Open the new ownership period.
                QueryBuilder::table('teacher_assignment_history')->insert([
                    'assignment_id' => $assignmentId, 'staff_id' => $newStaffId, 'role' => $role,
                    'start_date' => $eff, 'status' => 'active', 'reason' => $reasonTxt,
                    'changed_by' => auth()->id(), 'created_at' => date('Y-m-d H:i:s'),
                ]);
            });
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Could not reassign — the new teacher may already hold this exact assignment.'];
        }

        AuditLogger::log('teacher.reassigned', 'teacher_assignment', $assignmentId,
            "Reassigned from staff {$oldStaff} to {$newStaffId} effective {$eff}: {$reason}");
        return ['ok' => true, 'message' => 'Teacher reassigned (effective ' . $eff . '). History preserved.'];
    }

    /** @return array<int,array<string,mixed>> Ownership timeline for a teacher. */
    public function historyForTeacher(int $staffId): array
    {
        return QueryBuilder::table('teacher_assignment_history')
            ->select([
                'teacher_assignment_history.*',
                'classes.name AS class_name', 'sections.name AS section_name', 'subjects.name AS subject_name',
            ])
            ->leftJoin('teacher_assignments', 'teacher_assignment_history.assignment_id', '=', 'teacher_assignments.id')
            ->leftJoin('classes', 'teacher_assignments.class_id', '=', 'classes.id')
            ->leftJoin('sections', 'teacher_assignments.section_id', '=', 'sections.id')
            ->leftJoin('subjects', 'teacher_assignments.subject_id', '=', 'subjects.id')
            ->where('teacher_assignment_history.staff_id', $staffId)
            ->orderBy('teacher_assignment_history.id', 'DESC')->get();
    }

    /**
     * Verify a teacher is authorized for a subject/class/section (server-side gate).
     */
    public function canTeach(int $staffId, int $yearId, int $classId, ?int $sectionId, int $subjectId): bool
    {
        return TeacherAssignment::exists($yearId, $classId, $sectionId, $subjectId, $staffId);
    }
}
