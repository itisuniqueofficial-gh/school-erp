<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database\Model;
use Core\Database\QueryBuilder;

/**
 * Section model (belongs to a class).
 */
final class Section extends Model
{
    protected string $table = 'sections';
    protected array $fillable = ['class_id', 'name', 'capacity', 'class_teacher_id'];

    /** @return array<int,array<string,mixed>> */
    public static function forClass(int $classId): array
    {
        return self::query()->where('class_id', $classId)->orderBy('name')->get();
    }

    /** @return array<int,array<string,mixed>> Sections with their class name. */
    public static function withClass(): array
    {
        return QueryBuilder::table('sections')
            ->select(['sections.*', 'classes.name AS class_name'])
            ->join('classes', 'sections.class_id', '=', 'classes.id')
            ->orderBy('classes.numeric_level')->orderBy('sections.name')
            ->get();
    }
}
