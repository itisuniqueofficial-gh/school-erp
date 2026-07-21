<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database\Model;
use Core\Database\QueryBuilder;

/**
 * Academic year model.
 */
final class AcademicYear extends Model
{
    protected string $table = 'academic_years';
    protected array $fillable = ['name', 'start_date', 'end_date', 'is_current'];

    /** @return array<string,mixed>|null */
    public static function current(): ?array
    {
        return self::query()->where('is_current', 1)->first();
    }

    /**
     * Mark one year current, unset the others.
     */
    public static function makeCurrent(int $id): void
    {
        QueryBuilder::table('academic_years')->update(['is_current' => 0]);
        QueryBuilder::table('academic_years')->where('id', $id)->update(['is_current' => 1]);
    }
}
