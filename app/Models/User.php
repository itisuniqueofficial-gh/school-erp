<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database\Model;
use Core\Database\QueryBuilder;

/**
 * User model — authentication identities across all portals.
 */
final class User extends Model
{
    protected string $table = 'users';
    protected bool $softDeletes = true;

    protected array $fillable = [
        'name', 'email', 'username', 'phone', 'password', 'avatar',
        'is_active', 'email_verified_at', 'last_login_at', 'last_login_ip',
    ];

    protected array $hidden = ['password'];

    /**
     * Find an active user by email or username.
     *
     * @return array<string,mixed>|null
     */
    public static function findByLogin(string $login): ?array
    {
        return QueryBuilder::table('users')
            ->where('email', $login)
            ->orWhere('username', $login)
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * Attach a role (by slug) to a user.
     */
    public static function assignRole(int $userId, string $roleSlug): void
    {
        $role = QueryBuilder::table('roles')->where('slug', $roleSlug)->first();
        if ($role === null) {
            return;
        }
        $exists = QueryBuilder::table('user_roles')
            ->where('user_id', $userId)->where('role_id', $role['id'])->first();
        if ($exists === null) {
            QueryBuilder::table('user_roles')->insert([
                'user_id' => $userId,
                'role_id' => $role['id'],
            ]);
        }
    }

    /**
     * Sync a user's roles to exactly the given role ids.
     *
     * @param int[] $roleIds
     */
    public static function syncRoles(int $userId, array $roleIds): void
    {
        QueryBuilder::table('user_roles')->where('user_id', $userId)->delete();
        foreach (array_unique($roleIds) as $roleId) {
            QueryBuilder::table('user_roles')->insert([
                'user_id' => $userId,
                'role_id' => (int) $roleId,
            ]);
        }
    }

    /** @return array<int,array<string,mixed>> */
    public static function roles(int $userId): array
    {
        return QueryBuilder::table('user_roles')
            ->select(['roles.id', 'roles.slug', 'roles.name'])
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $userId)
            ->get();
    }
}
