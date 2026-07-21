<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database\Model;
use Core\Database\QueryBuilder;

/**
 * Student attendance model. Architecture supports manual, biometric and QR
 * marking methods (the `method` column) — biometric/QR ready.
 */
final class StudentAttendance extends Model
{
    protected string $table = 'student_attendance';

    protected array $fillable = [
        'student_id', 'class_id', 'section_id', 'date', 'status', 'remarks', 'marked_by', 'method',
    ];

    /**
     * Existing attendance for a class/section on a date, keyed by student_id.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function forClassSectionDate(int $classId, int $sectionId, string $date): array
    {
        $rows = QueryBuilder::table('student_attendance')
            ->where('class_id', $classId)
            ->where('section_id', $sectionId)
            ->where('date', $date)
            ->get();

        $keyed = [];
        foreach ($rows as $row) {
            $keyed[(int) $row['student_id']] = $row;
        }
        return $keyed;
    }

    /**
     * Insert or update a single student's attendance for a date (idempotent).
     */
    public static function mark(int $studentId, int $classId, int $sectionId, string $date, string $status, ?int $markedBy, string $method = 'manual'): void
    {
        $existing = QueryBuilder::table('student_attendance')
            ->where('student_id', $studentId)->where('date', $date)->first();

        if ($existing !== null) {
            QueryBuilder::table('student_attendance')->where('id', $existing['id'])->update([
                'status' => $status, 'class_id' => $classId, 'section_id' => $sectionId,
                'marked_by' => $markedBy, 'method' => $method, 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            return;
        }

        QueryBuilder::table('student_attendance')->insert([
            'student_id' => $studentId, 'class_id' => $classId, 'section_id' => $sectionId,
            'date' => $date, 'status' => $status, 'marked_by' => $markedBy, 'method' => $method,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Monthly attendance rows for a class/section (for the report grid).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function monthly(int $classId, int $sectionId, string $monthPrefix): array
    {
        return QueryBuilder::table('student_attendance')
            ->where('class_id', $classId)
            ->where('section_id', $sectionId)
            ->whereLike('date', $monthPrefix . '%')
            ->get();
    }
}
