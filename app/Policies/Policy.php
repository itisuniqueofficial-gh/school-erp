<?php

declare(strict_types=1);

namespace App\Policies;

/**
 * Base authorization policy. Concrete policies implement entity-level access
 * rules on top of the permission-based RBAC (auth()->can()).
 *
 * Permission middleware ('can:...') guards routes globally; policies add
 * finer-grained, record-aware checks (e.g. "can this teacher edit THIS class").
 */
abstract class Policy
{
    protected function user(): ?array
    {
        return auth()->user();
    }

    protected function can(string $permission): bool
    {
        return auth()->can($permission);
    }

    protected function hasRole(string $role): bool
    {
        return auth()->hasRole($role);
    }

    protected function isSuperAdmin(): bool
    {
        return auth()->hasRole('super_admin');
    }
}
