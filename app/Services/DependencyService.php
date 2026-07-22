<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\QueryBuilder;

/**
 * Counts the dependent records that reference an academic entity. Used to make
 * deletion dependency-aware: an entity with any dependents must be ARCHIVED,
 * never hard-deleted, so historical data (students, attendance, marks, …) is
 * never silently destroyed.
 */
final class DependencyService
{
    private function count(callable $q): int
    {
        try {
            return (int) $q();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @param array<string,int> $raw
     * @return array{total:int,items:array<string,int>}
     */
    private function pack(array $raw): array
    {
        $items = array_filter($raw, static fn (int $n) => $n > 0);
        return ['total' => array_sum($items), 'items' => $items];
    }

    /** @return array{total:int,items:array<string,int>} */
    public function forClass(int $classId): array
    {
        return $this->pack([
            'Students'            => $this->count(fn () => QueryBuilder::table('students')->where('class_id', $classId)->whereNull('deleted_at')->count()),
            'Sections'            => $this->count(fn () => QueryBuilder::table('sections')->where('class_id', $classId)->count()),
            'Subject allocations' => $this->count(fn () => QueryBuilder::table('class_subjects')->where('class_id', $classId)->count()),
            'Teacher assignments' => $this->count(fn () => QueryBuilder::table('teacher_assignments')->where('class_id', $classId)->count()),
            'Enrollments'         => $this->count(fn () => QueryBuilder::table('student_enrollments')->where('class_id', $classId)->count()),
            'Attendance records'  => $this->count(fn () => QueryBuilder::table('student_attendance')->where('class_id', $classId)->count()),
        ]);
    }

    /** @return array{total:int,items:array<string,int>} */
    public function forSection(int $sectionId): array
    {
        return $this->pack([
            'Students'            => $this->count(fn () => QueryBuilder::table('students')->where('section_id', $sectionId)->whereNull('deleted_at')->count()),
            'Enrollments'         => $this->count(fn () => QueryBuilder::table('student_enrollments')->where('section_id', $sectionId)->count()),
            'Teacher assignments' => $this->count(fn () => QueryBuilder::table('teacher_assignments')->where('section_id', $sectionId)->count()),
            'Class teachers'      => $this->count(fn () => QueryBuilder::table('class_teachers')->where('section_id', $sectionId)->count()),
            'Attendance records'  => $this->count(fn () => QueryBuilder::table('student_attendance')->where('section_id', $sectionId)->count()),
        ]);
    }

    /** @return array{total:int,items:array<string,int>} */
    public function forSubject(int $subjectId): array
    {
        return $this->pack([
            'Class allocations'   => $this->count(fn () => QueryBuilder::table('class_subjects')->where('subject_id', $subjectId)->count()),
            'Teacher assignments' => $this->count(fn () => QueryBuilder::table('teacher_assignments')->where('subject_id', $subjectId)->count()),
            'Exam schedules'      => $this->count(fn () => QueryBuilder::table('exam_schedules')->where('subject_id', $subjectId)->count()),
            'Subject attendance'  => $this->count(fn () => QueryBuilder::table('subject_attendance')->where('subject_id', $subjectId)->count()),
        ]);
    }

    /** @return array{total:int,items:array<string,int>} */
    public function forYear(int $yearId): array
    {
        return $this->pack([
            'Enrollments'         => $this->count(fn () => QueryBuilder::table('student_enrollments')->where('academic_year_id', $yearId)->count()),
            'Teacher assignments' => $this->count(fn () => QueryBuilder::table('teacher_assignments')->where('academic_year_id', $yearId)->count()),
            'Class teachers'      => $this->count(fn () => QueryBuilder::table('class_teachers')->where('academic_year_id', $yearId)->count()),
            'Exams'               => $this->count(fn () => QueryBuilder::table('exams')->where('academic_year_id', $yearId)->count()),
            'Subject attendance'  => $this->count(fn () => QueryBuilder::table('subject_attendance')->where('academic_year_id', $yearId)->count()),
        ]);
    }

    /** Human-readable summary like "3 students, 2 sections, 5 teacher assignments". */
    public function summary(array $dep): string
    {
        $parts = [];
        foreach ($dep['items'] as $label => $n) {
            $parts[] = $n . ' ' . strtolower($label);
        }
        return implode(', ', $parts);
    }
}
