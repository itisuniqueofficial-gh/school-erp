<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TeacherAssignment;
use Core\Database\Connection;
use Core\Database\QueryBuilder;

/**
 * Academic-year automation. Copying an academic year's SETUP replicates only
 * the year-scoped academic configuration (teacher assignments + class teachers)
 * into a target year. It NEVER copies transactional/historical data — students,
 * enrollments, attendance, marks, exams or fees are left untouched.
 *
 * (Classes, sections, subjects and class-subject allocations are shared across
 * years in this schema, so they do not need copying.)
 */
final class AcademicYearService
{
    /**
     * @return array{ok:bool,message:string,teacher_assignments:int,class_teachers:int}
     */
    public function copySetup(int $fromYearId, int $toYearId, bool $copyTeachers = true): array
    {
        $zero = ['teacher_assignments' => 0, 'class_teachers' => 0];

        if ($fromYearId === $toYearId) {
            return ['ok' => false, 'message' => 'Source and target academic years must differ.'] + $zero;
        }
        $from = QueryBuilder::table('academic_years')->where('id', $fromYearId)->first();
        $to   = QueryBuilder::table('academic_years')->where('id', $toYearId)->first();
        if ($from === null || $to === null) {
            return ['ok' => false, 'message' => 'Invalid source or target academic year.'] + $zero;
        }

        $taCopied = 0;
        $ctCopied = 0;

        try {
            [$taCopied, $ctCopied] = Connection::instance()->transaction(function () use ($fromYearId, $toYearId, $copyTeachers): array {
                $ta = 0;
                $ct = 0;
                if ($copyTeachers) {
                    // Teacher subject/assistant assignments (year-scoped).
                    $rows = QueryBuilder::table('teacher_assignments')->where('academic_year_id', $fromYearId)->get();
                    foreach ($rows as $r) {
                        $sectionId = $r['section_id'] !== null ? (int) $r['section_id'] : null;
                        if (TeacherAssignment::exists($toYearId, (int) $r['class_id'], $sectionId, (int) $r['subject_id'], (int) $r['staff_id'])) {
                            continue; // dup-safe: skip existing
                        }
                        $newId = QueryBuilder::table('teacher_assignments')->insert([
                            'academic_year_id' => $toYearId, 'class_id' => (int) $r['class_id'],
                            'section_id' => $sectionId, 'subject_id' => (int) $r['subject_id'],
                            'staff_id' => (int) $r['staff_id'], 'role' => (string) $r['role'],
                            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                        QueryBuilder::table('teacher_assignment_history')->insert([
                            'assignment_id' => $newId, 'staff_id' => (int) $r['staff_id'], 'role' => (string) $r['role'],
                            'start_date' => date('Y-m-d'), 'status' => 'active',
                            'changed_by' => auth()->id(), 'created_at' => date('Y-m-d H:i:s'),
                        ]);
                        $ta++;
                    }

                    // Class teachers (year-scoped, one per class/section).
                    $cts = QueryBuilder::table('class_teachers')->where('academic_year_id', $fromYearId)->get();
                    foreach ($cts as $r) {
                        $sectionId = $r['section_id'] !== null ? (int) $r['section_id'] : null;
                        $exists = QueryBuilder::table('class_teachers')
                            ->where('academic_year_id', $toYearId)->where('class_id', (int) $r['class_id']);
                        $exists = $sectionId ? $exists->where('section_id', $sectionId) : $exists->whereNull('section_id');
                        if ($exists->exists()) {
                            continue;
                        }
                        QueryBuilder::table('class_teachers')->insert([
                            'academic_year_id' => $toYearId, 'class_id' => (int) $r['class_id'],
                            'section_id' => $sectionId, 'staff_id' => (int) $r['staff_id'],
                            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                        $ct++;
                    }
                }
                return [$ta, $ct];
            });
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Copy failed: ' . $e->getMessage()] + $zero;
        }

        AuditLogger::log('academic.year_setup_copied', 'academic_year', $toYearId,
            "Copied setup from year {$fromYearId} to {$toYearId}: {$taCopied} teacher assignments, {$ctCopied} class teachers");

        return [
            'ok' => true,
            'message' => "Copied {$taCopied} teacher assignment(s) and {$ctCopied} class teacher(s). Students, attendance, marks and fees were not copied.",
            'teacher_assignments' => $taCopied,
            'class_teachers' => $ctCopied,
        ];
    }
}
