<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\Connection;
use Core\Database\QueryBuilder;
use Core\Security\Hash;
use DomainException;
use InvalidArgumentException;

/**
 * Authorized super-administrator recovery.
 *
 * This service only ever touches the single account it is asked to recover:
 * it resets that user's password, reactivates the account, and guarantees the
 * super_admin role. All other users and all application data are left intact.
 */
final class AdminRecoveryService
{
    /** @return array<int, array<string, mixed>> */
    public function superAdmins(): array
    {
        return Connection::instance()->select(
            'SELECT `u`.`id`, `u`.`name`, `u`.`email`, `u`.`username`, `u`.`is_active`, `u`.`deleted_at`
               FROM `users` `u`
               JOIN `user_roles` `ur` ON `ur`.`user_id` = `u`.`id`
               JOIN `roles` `r` ON `r`.`id` = `ur`.`role_id`
              WHERE `r`.`slug` = ?
              ORDER BY `u`.`id`',
            ['super_admin']
        );
    }

    /**
     * Reset an existing administrator or create one when explicitly allowed.
     *
     * @return array{id:int,email:string,username:string,name:string,created:bool}
     */
    public function resetOrCreate(
        string $email,
        string $password,
        ?string $name = null,
        bool $createIfMissing = false,
        ?string $preferredUsername = null,
        string $source = 'cli'
    ): array {
        $email = strtolower(trim($email));
        $name = $name !== null ? trim($name) : null;
        $preferredUsername = $preferredUsername !== null ? trim($preferredUsername) : null;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid administrator email address.');
        }
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('The administrator password must be at least 8 characters.');
        }
        if (strlen($password) > 4096) {
            throw new InvalidArgumentException('The administrator password is too long.');
        }
        if ($name !== null && strlen($name) > 150) {
            throw new InvalidArgumentException('The administrator name is too long (maximum 150 characters).');
        }

        $roleId = QueryBuilder::table('roles')->where('slug', 'super_admin')->value('id');
        if ($roleId === null) {
            throw new DomainException('The super_admin role is missing. Run migrations and seeding first.');
        }

        $user = QueryBuilder::table('users')->where('email', $email)->first();

        if ($user === null && $preferredUsername !== null && $preferredUsername !== '') {
            $user = QueryBuilder::table('users')->where('username', $preferredUsername)->first();

            if ($user !== null && strcasecmp((string) $user['email'], $email) !== 0) {
                $conflict = QueryBuilder::table('users')->where('email', $email)->first();
                if ($conflict !== null) {
                    throw new DomainException('That email address already belongs to another account.');
                }
            }
        }

        $now = date('Y-m-d H:i:s');
        $created = false;

        if ($user === null) {
            if (!$createIfMissing) {
                throw new DomainException('No account exists for that email address. Use --create to create a new super administrator.');
            }

            $username = $this->uniqueUsername($preferredUsername ?: $this->deriveUsername($email));
            $userId = QueryBuilder::table('users')->insert([
                'name'              => $name !== null && $name !== '' ? $name : $this->deriveName($email),
                'email'             => $email,
                'username'          => $username,
                'password'          => Hash::make($password),
                'is_active'         => 1,
                'email_verified_at' => $now,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
            $created = true;
        } else {
            $userId = (int) $user['id'];
            $username = (string) ($user['username'] ?? '');

            if ($username === '') {
                $username = $this->uniqueUsername($this->deriveUsername($email), $userId);
            }

            $update = [
                'email'      => $email,
                'password'   => Hash::make($password),
                'is_active'  => 1,
                'updated_at' => $now,
            ];

            if ($name !== null && $name !== '') {
                $update['name'] = $name;
            }
            if ($username !== (string) ($user['username'] ?? '')) {
                $update['username'] = $username;
            }
            if (($user['deleted_at'] ?? null) !== null) {
                $update['deleted_at'] = null;
            }

            QueryBuilder::table('users')->where('id', $userId)->update($update);
        }

        $link = QueryBuilder::table('user_roles')
            ->where('user_id', $userId)
            ->where('role_id', (int) $roleId)
            ->first();
        if ($link === null) {
            QueryBuilder::table('user_roles')->insert([
                'user_id' => $userId,
                'role_id' => (int) $roleId,
            ]);
        }

        $this->revokeRememberTokens($userId);

        $action = $created ? 'admin.recovery_created' : 'admin.recovery_reset';
        AuditLogger::log(
            $action,
            'user',
            $userId,
            ucfirst($source) . ' super administrator recovery for ' . $email . ($created ? ' (created)' : ' (password reset)'),
            null
        );

        $fresh = QueryBuilder::table('users')->where('id', $userId)->first();

        return [
            'id'       => $userId,
            'email'    => $email,
            'username' => $username,
            'name'     => (string) ($fresh['name'] ?? ($name ?? '')),
            'created'  => $created,
        ];
    }

    private function revokeRememberTokens(int $userId): void
    {
        try {
            QueryBuilder::table('remember_tokens')->where('user_id', $userId)->delete();
        } catch (\Throwable) {
            // Older/partial schemas may not have the table yet; recovery still succeeds.
        }
    }

    private function deriveUsername(string $email): string
    {
        $local = strstr($email, '@', true);
        $base = strtolower((string) ($local !== false ? $local : 'superadmin'));
        $base = (string) preg_replace('/[^a-z0-9._-]+/', '', $base);
        $base = trim($base, '._-');
        if ($base === '') {
            $base = 'superadmin';
        }
        return substr($base, 0, 80);
    }

    private function deriveName(string $email): string
    {
        $local = strstr($email, '@', true);
        $name = str_replace(['.', '_', '-'], ' ', (string) ($local !== false ? $local : 'Super Admin'));
        $name = trim((string) preg_replace('/\s+/', ' ', $name));
        return $name !== '' ? ucwords($name) : 'Super Admin';
    }

    private function uniqueUsername(string $base, ?int $ignoreUserId = null): string
    {
        $base = substr(trim($base) !== '' ? trim($base) : 'superadmin', 0, 80);
        $candidate = $base;
        $suffix = 1;

        while (true) {
            $query = QueryBuilder::table('users')->where('username', $candidate);
            if ($ignoreUserId !== null) {
                $query->where('id', '!=', $ignoreUserId);
            }
            if ($query->first() === null) {
                return $candidate;
            }
            $suffix++;
            $candidate = substr($base, 0, 74) . $suffix;
        }
    }
}
