<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database\Model;
use Core\Database\QueryBuilder;

/**
 * Student academic enrollment (historical, one row per academic year).
 */
final class StudentEnrollment extends Model
{
    protected string $table = 'student_enrollments';
    protected array $fillable = ['student_id', 'academic_year_id', 'class_id', 'section_id', 'roll_no', 'status'];

    /** @return array<int,array<string,mixed>> Full enrollment history for a student. */
    public static function history(int $studentId): array
    {
        return QueryBuilder::table('student_enrollments')
            ->select([
                'student_enrollments.*',
                'classes.name AS class_name', 'sections.name AS section_name',
                'academic_years.name AS year_name',
            ])
            ->leftJoin('classes', 'student_enrollments.class_id', '=', 'classes.id')
            ->leftJoin('sections', 'student_enrollments.section_id', '=', 'sections.id')
            ->leftJoin('academic_years', 'student_enrollments.academic_year_id', '=', 'academic_years.id')
            ->where('student_enrollments.student_id', $studentId)
            ->orderBy('academic_years.start_date', 'DESC')
            ->get();
    }

    /** @return array<int,array<string,mixed>> Active roster for a class/section in a year. */
    public static function roster(int $yearId, int $classId, int $sectionId): array
    {
        return QueryBuilder::table('student_enrollments')
            ->select(['student_enrollments.*', 'students.first_name', 'students.last_name', 'students.admission_no', 'students.public_id'])
            ->join('students', 'student_enrollments.student_id', '=', 'students.id')
            ->where('academic_year_id', $yearId)
            ->where('class_id', $classId)
            ->where('section_id', $sectionId)
            ->where('student_enrollments.status', 'active')
            ->whereNull('students.deleted_at')
            ->orderBy('roll_no')
            ->get();
    }
}
