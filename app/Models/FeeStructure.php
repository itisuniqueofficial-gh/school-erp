<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database\Model;
use Core\Database\QueryBuilder;

/**
 * Fee structure model.
 */
final class FeeStructure extends Model
{
    protected string $table = 'fee_structures';
    protected array $fillable = ['name', 'class_id', 'academic_year_id', 'frequency'];

    /** @return array<int,array<string,mixed>> Structures with class name. */
    public static function withClass(): array
    {
        return QueryBuilder::table('fee_structures')
            ->select(['fee_structures.*', 'classes.name AS class_name'])
            ->leftJoin('classes', 'fee_structures.class_id', '=', 'classes.id')
            ->orderBy('fee_structures.id', 'DESC')
            ->get();
    }
}
