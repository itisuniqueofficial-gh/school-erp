<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database\Model;
use Core\Database\QueryBuilder;

/**
 * Role model (RBAC).
 */
final class Role extends Model
{
    protected string $table = 'roles';
    protected string $primaryKey = 'id';
    protected array $fillable = ['slug', 'name', 'description', 'is_system'];

    /** @return array<int,array<string,mixed>> */
    public static function withPermissionCounts(): array
    {
        return QueryBuilder::table('roles')
            ->selectRaw('`roles`.*, (SELECT COUNT(*) FROM `role_permissions` WHERE `role_permissions`.`role_id` = `roles`.`id`) AS permission_count, (SELECT COUNT(*) FROM `user_roles` WHERE `user_roles`.`role_id` = `roles`.`id`) AS user_count')
            ->orderBy('roles.id')
            ->get();
    }

    /** @return int[] permission ids attached to a role */
    public static function permissionIds(int $roleId): array
    {
        $rows = QueryBuilder::table('role_permissions')->where('role_id', $roleId)->get();
        return array_map(static fn ($r) => (int) $r['permission_id'], $rows);
    }

    /**
     * Replace a role's permissions with the given permission ids.
     *
     * @param int[] $permissionIds
     */
    public static function syncPermissions(int $roleId, array $permissionIds): void
    {
        QueryBuilder::table('role_permissions')->where('role_id', $roleId)->delete();
        foreach (array_unique($permissionIds) as $pid) {
            QueryBuilder::table('role_permissions')->insert([
                'role_id' => $roleId, 'permission_id' => (int) $pid,
            ]);
        }
    }
}
