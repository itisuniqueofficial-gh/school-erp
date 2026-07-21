<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database\Model;

/**
 * Class/grade model (table: classes; "class" is a reserved word in PHP).
 */
final class ClassModel extends Model
{
    protected string $table = 'classes';
    protected array $fillable = ['name', 'numeric_level'];

    /** @return array<int,array<string,mixed>> */
    public static function ordered(): array
    {
        return self::query()->orderBy('numeric_level')->get();
    }
}
