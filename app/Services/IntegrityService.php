<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\QueryBuilder;

/**
 * Automated data-integrity checker for the academic system. Each check returns
 * a count and an actionable link; the dashboard renders them as
 * Critical / Warning / Healthy.
 */
final class IntegrityService
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
     * @return array<int,array{key:string,label:string,count:int,severity:string,link:string}>
     */
    public function checks(): array
    {
        $yearId = (int) (QueryBuilder::table('academic_years')->where('is_current', 1)->value('id') ?? 0);

        $out = [];

        $out[] = $this->row('students_no_enrollment', 'Students without an enrollment this year',
            $this->count(fn () => QueryBuilder::table('students')
                ->whereNull('deleted_at')->where('status', 'active')
                ->whereRaw('`students`.`id` NOT IN (SELECT `student_id` FROM `student_enrollments` WHERE `academic_year_id` = ?)', [$yearId])
                ->count()),
            'critical', '/admin/students');

        $out[] = $this->row('subjects_no_teacher', 'Class-subjects without a teacher (current year)',
            $this->count(fn () => QueryBuilder::table('class_subjects')
                ->whereRaw('NOT EXISTS (SELECT 1 FROM `teacher_assignments` ta WHERE ta.`class_id` = `class_subjects`.`class_id` AND ta.`subject_id` = `class_subjects`.`subject_id` AND ta.`academic_year_id` = ?)', [$yearId])
                ->count()),
            'warning', '/admin/teachers');

        $out[] = $this->row('teachers_no_assignment', 'Teachers with no assignments',
            $this->count(fn () => QueryBuilder::table('staff')
                ->where('staff_type', 'teaching')->whereNull('deleted_at')
                ->whereRaw('`staff`.`id` NOT IN (SELECT `staff_id` FROM `teacher_assignments`)')
                ->count()),
            'warning', '/admin/teachers');

        $out[] = $this->row('sections_no_class_teacher', 'Sections without a class teacher (current year)',
            $this->count(fn () => QueryBuilder::table('sections')
                ->whereRaw('`sections`.`id` NOT IN (SELECT `section_id` FROM `class_teachers` WHERE `academic_year_id` = ?)', [$yearId])
                ->count()),
            'warning', '/admin/academic/sections');

        $out[] = $this->row('marks_exceed_max', 'Marks exceeding the maximum',
            $this->count(fn () => QueryBuilder::table('exam_marks')
                ->join('exam_schedules', 'exam_marks.exam_schedule_id', '=', 'exam_schedules.id')
                ->whereRaw('`exam_marks`.`marks_obtained` > `exam_schedules`.`full_marks`')
                ->count()),
            'critical', '/admin/exams');

        return $out;
    }

    /** Overall status derived from the checks. */
    public function status(): string
    {
        foreach ($this->checks() as $c) {
            if ($c['count'] > 0 && $c['severity'] === 'critical') {
                return 'critical';
            }
        }
        foreach ($this->checks() as $c) {
            if ($c['count'] > 0) {
                return 'warning';
            }
        }
        return 'healthy';
    }

    private function row(string $key, string $label, int $count, string $severity, string $link): array
    {
        return ['key' => $key, 'label' => $label, 'count' => $count, 'severity' => $severity, 'link' => $link];
    }
}
