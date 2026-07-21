<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database\Model;
use Core\Database\QueryBuilder;

/**
 * Guardian/parent model.
 */
final class Guardian extends Model
{
    protected string $table = 'guardians';
    protected array $fillable = ['user_id', 'name', 'relation', 'occupation', 'email', 'phone', 'address'];

    /** @return array<int,array<string,mixed>> Guardians of a student. */
    public static function forStudent(int $studentId): array
    {
        return QueryBuilder::table('student_guardians')
            ->select(['guardians.*', 'student_guardians.is_primary'])
            ->join('guardians', 'student_guardians.guardian_id', '=', 'guardians.id')
            ->where('student_guardians.student_id', $studentId)
            ->get();
    }
}
