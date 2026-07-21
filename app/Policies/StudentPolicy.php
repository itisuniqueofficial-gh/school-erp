<?php

declare(strict_types=1);

namespace App\Policies;

/**
 * Record-aware authorization rules for student resources.
 */
final class StudentPolicy extends Policy
{
    public function viewAny(): bool
    {
        return $this->can('students.view');
    }

    /**
     * @param array<string,mixed> $student
     */
    public function view(array $student): bool
    {
        if ($this->can('students.view')) {
            return true;
        }
        // A student may view their own record.
        $user = $this->user();
        return $user !== null && (int) ($student['user_id'] ?? 0) === (int) $user['id'];
    }

    public function create(): bool
    {
        return $this->can('students.create');
    }

    /**
     * @param array<string,mixed> $student
     */
    public function update(array $student): bool
    {
        return $this->can('students.update');
    }

    /**
     * @param array<string,mixed> $student
     */
    public function delete(array $student): bool
    {
        return $this->can('students.delete') && !$this->hasRole('receptionist');
    }
}
