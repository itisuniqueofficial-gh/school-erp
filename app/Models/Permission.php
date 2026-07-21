<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database\Model;
use Core\Database\QueryBuilder;

/**
 * Permission model (RBAC).
 */
final class Permission extends Model
{
    protected string $table = 'permissions';
    protected string $primaryKey = 'id';
    protected array $fillable = ['slug', 'name', 'module'];

    /**
     * All permissions grouped by module for building permission matrices.
     *
     * @return array<string, array<int, array<string,mixed>>>
     */
    public static function groupedByModule(): array
    {
        $rows = QueryBuilder::table('permissions')->orderBy('module')->orderBy('slug')->get();
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['module']][] = $row;
        }
        return $grouped;
    }
}
