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
    protected array $fillable = ['academic_year_id', 'class_id', 'section_id', 'subject_id', 'staff_id', 'role'];

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

    public static function exists(int $yearId, int $classId, int $sectionId, int $subjectId, int $staffId): bool
    {
        return QueryBuilder::table('teacher_assignments')
            ->where('academic_year_id', $yearId)->where('class_id', $classId)
            ->where('section_id', $sectionId)->where('subject_id', $subjectId)
            ->where('staff_id', $staffId)->exists();
    }
}
