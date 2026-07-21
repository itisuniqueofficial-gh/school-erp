<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database\Model;
use Core\Database\QueryBuilder;

/**
 * Examination model.
 */
final class Exam extends Model
{
    protected string $table = 'exams';
    protected array $fillable = ['exam_type_id', 'academic_year_id', 'name', 'start_date', 'end_date', 'status'];

    /** @return array<int,array<string,mixed>> Exams with their type name. */
    public static function withType(): array
    {
        return QueryBuilder::table('exams')
            ->select(['exams.*', 'exam_types.name AS type_name'])
            ->leftJoin('exam_types', 'exams.exam_type_id', '=', 'exam_types.id')
            ->orderBy('exams.id', 'DESC')
            ->get();
    }

    /**
     * Resolve the grade id for a percentage using the grades scale.
     */
    public static function gradeForPercentage(float $percent): ?int
    {
        $row = QueryBuilder::table('grades')
            ->where('min_percent', '<=', $percent)
            ->where('max_percent', '>=', $percent)
            ->first();
        return $row !== null ? (int) $row['id'] : null;
    }
}
