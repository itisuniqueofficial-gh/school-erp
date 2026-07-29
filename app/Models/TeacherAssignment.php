<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database\Model;
use Core\Database\QueryBuilder;

/**
 * Teacher subject/section assignment (year → class → section → subject → teacher).
 */
final class TeacherAssignment extends Model
{
    protected string $table = 'teacher_assignments';
    protected array $fillable = ['academic_year_id', 'class_id', 'section_id', 'section_key', 'subject_id', 'staff_id', 'role'];

    /** @return array<int,array<string,mixed>> Assignments for a teacher, with names. */
    public static function forTeacher(int $staffId, ?int $yearId = null): array
    {
        $q = QueryBuilder::table('teacher_assignments')
            ->select([
                'teacher_assignments.*',
                'classes.name AS class_name', 'sections.name AS section_name',
                'subjects.name AS subject_name', 'academic_years.name AS year_name',
            ])
            ->leftJoin('classes', 'teacher_assignments.class_id', '=', 'classes.id')
            ->leftJoin('sections', 'teacher_assignments.section_id', '=', 'sections.id')
            ->leftJoin('subjects', 'teacher_assignments.subject_id', '=', 'subjects.id')
            ->leftJoin('academic_years', 'teacher_assignments.academic_year_id', '=', 'academic_years.id')
            ->where('teacher_assignments.staff_id', $staffId);
        if ($yearId !== null) {
            $q->where('teacher_assignments.academic_year_id', $yearId);
        }
        return $q->orderBy('teacher_assignments.id', 'DESC')->get();
    }

    /**
     * Filtered listing across ALL teachers, with names for the management module.
     *
     * @param array{academic_year_id?:int,class_id?:int,section_id?:int,subject_id?:int,staff_id?:int,role?:string} $f
     * @return array<int,array<string,mixed>>
     */
    public static function filtered(array $f = []): array
    {
        $q = QueryBuilder::table('teacher_assignments')
            ->select([
                'teacher_assignments.*',
                'classes.name AS class_name',
                'sections.name AS section_name',
                'subjects.name AS subject_name', 'subjects.code AS subject_code',
                'academic_years.name AS year_name',
                'staff.first_name', 'staff.last_name', 'staff.public_id AS teacher_pid', 'staff.employee_code',
            ])
            ->leftJoin('classes', 'teacher_assignments.class_id', '=', 'classes.id')
            ->leftJoin('sections', 'teacher_assignments.section_id', '=', 'sections.id')
            ->leftJoin('subjects', 'teacher_assignments.subject_id', '=', 'subjects.id')
            ->leftJoin('academic_years', 'teacher_assignments.academic_year_id', '=', 'academic_years.id')
            ->leftJoin('staff', 'teacher_assignments.staff_id', '=', 'staff.id');
        foreach (['academic_year_id', 'class_id', 'section_id', 'subject_id', 'staff_id'] as $col) {
            if (!empty($f[$col])) {
                $q->where('teacher_assignments.' . $col, (int) $f[$col]);
            }
        }
        if (!empty($f['role'])) {
            $q->where('teacher_assignments.role', (string) $f['role']);
        }
        return $q->orderBy('teacher_assignments.id', 'DESC')->get();
    }

    public static function exists(int $yearId, int $classId, ?int $sectionId, int $subjectId, int $staffId): bool
    {
        $q = QueryBuilder::table('teacher_assignments')
            ->where('academic_year_id', $yearId)->where('class_id', $classId)
            ->where('subject_id', $subjectId)->where('staff_id', $staffId);
        if ($sectionId) {
            $q->where('section_id', $sectionId);
        } else {
            $q->whereNull('section_id');
        }
        return $q->exists();
    }
}
