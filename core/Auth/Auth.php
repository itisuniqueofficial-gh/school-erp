<?php

declare(strict_types=1);

namespace Core\Auth;

use Core\Config\Config;
use Core\Database\Connection;
use Core\Database\QueryBuilder;
use Core\Http\Session;
use Core\Security\Hash;

/**
 * Session-based authentication manager with RBAC awareness and "remember me".
 * Loads the authenticated user's roles and permissions for authorization checks.
 */
final class Auth
{
    private ?array $user = null;
    private bool $resolved = false;
    private ?int $onceId = null;

    public function __construct(private readonly Session $session)
    {
    }

    /**
     * Attempt to authenticate using email/username and password.
     */
    public function attempt(string $login, string $password, bool $remember = false): bool
    {
        $user = QueryBuilder::table('users')
            ->where('email', $login)
            ->orWhere('username', $login)
            ->whereNull('deleted_at')
            ->first();

        if ($user === null) {
            return false;
        }

        if ((int) ($user['is_active'] ?? 0) !== 1) {
            return false;
        }

        if (!Hash::verify($password, (string) $user['password'])) {
            return false;
        }

        // Transparently upgrade hash if cost changed.
        if (Hash::needsRehash((string) $user['password'])) {
            QueryBuilder::table('users')
                ->where('id', $user['id'])
                ->update(['password' => Hash::make($password)]);
        }

        $this->login((int) $user['id'], $remember);
        return true;
    }

    /**
     * Log a user in by id and set up the session (and remember cookie).
     */
    public function login(int $userId, bool $remember = false): void
    {
        $this->session->regenerate();
        $this->session->put((string) Config::get('auth.session_key', 'auth_user_id'), $userId);

        QueryBuilder::table('users')->where('id', $userId)->update([
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);

        if ($remember) {
            $this->setRememberToken($userId);
        }

        $this->resolved = false;
        $this->user = null;
    }

    private function setRememberToken(int $userId): void
    {
        $selector = bin2hex(random_bytes(9));
        $validator = bin2hex(random_bytes(32));
        $hashed = hash('sha256', $validator);
        $days = (int) Config::get('auth.remember_days', 30);
        $expires = date('Y-m-d H:i:s', time() + ($days * 86400));

        QueryBuilder::table('remember_tokens')->insert([
            'user_id'    => $userId,
            'selector'   => $selector,
            'token'      => $hashed,
            'expires_at' => $expires,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        setcookie(
            (string) Config::get('auth.remember_name', 'remember_token'),
            $selector . ':' . $validator,
            [
                'expires'  => time() + ($days * 86400),
                'path'     => '/',
                'secure'   => (bool) Config::get('session.secure', false),
                'httponly' => true,
                'samesite' => (string) Config::get('session.same_site', 'Lax'),
            ]
        );
    }

    /**
     * Attempt to authenticate via the remember-me cookie.
     */
    public function attemptRemember(): bool
    {
        $cookieName = (string) Config::get('auth.remember_name', 'remember_token');
        $cookie = $_COOKIE[$cookieName] ?? null;
        if (!$cookie || !str_contains($cookie, ':')) {
            return false;
        }

        [$selector, $validator] = explode(':', $cookie, 2);
        $record = QueryBuilder::table('remember_tokens')
            ->where('selector', $selector)
            ->first();

        if ($record === null || strtotime((string) $record['expires_at']) < time()) {
            return false;
        }

        if (!hash_equals((string) $record['token'], hash('sha256', $validator))) {
            return false;
        }

        $this->session->put((string) Config::get('auth.session_key', 'auth_user_id'), (int) $record['user_id']);
        return true;
    }

    public function check(): bool
    {
        return $this->id() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * Authenticate as the given user for the current request only (stateless).
     * Used by the API where no session/remember cookie should be created.
     */
    public function onceUsingId(int $userId): void
    {
        $this->onceId = $userId;
        $this->resolved = false;
        $this->user = null;
    }

    public function id(): ?int
    {
        if ($this->onceId !== null) {
            return $this->onceId;
        }
        $id = $this->session->get((string) Config::get('auth.session_key', 'auth_user_id'));
        return $id !== null ? (int) $id : null;
    }

    /**
     * Get the authenticated user (with roles + permissions), or null.
     *
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        if ($this->resolved) {
            return $this->user;
        }
        $this->resolved = true;

        $id = $this->id();
        if ($id === null) {
            return $this->user = null;
        }

        $user = QueryBuilder::table('users')->where('id', $id)->whereNull('deleted_at')->first();
        if ($user === null) {
            return $this->user = null;
        }

        unset($user['password']);
        $user['roles'] = $this->loadRoles($id);
        $user['permissions'] = $this->loadPermissions($id);

        return $this->user = $user;
    }

    /** @return string[] */
    private function loadRoles(int $userId): array
    {
        $rows = QueryBuilder::table('user_roles')
            ->select(['roles.slug'])
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->where('user_roles.user_id', $userId)
            ->get();
        return array_column($rows, 'slug');
    }

    /** @return string[] */
    private function loadPermissions(int $userId): array
    {
        $rows = QueryBuilder::table('user_roles')
            ->selectRaw('DISTINCT `permissions`.`slug` AS slug')
            ->join('role_permissions', 'user_roles.role_id', '=', 'role_permissions.role_id')
            ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->where('user_roles.user_id', $userId)
            ->get();
        return array_column($rows, 'slug');
    }

    public function hasRole(string $role): bool
    {
        $user = $this->user();
        return $user !== null && in_array($role, $user['roles'] ?? [], true);
    }

    /** @param string[] $roles */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }
        return false;
    }

    public function can(string $permission): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        // Super admin bypasses all permission checks.
        if (in_array('super_admin', $user['roles'] ?? [], true)) {
            return true;
        }
        return in_array($permission, $user['permissions'] ?? [], true);
    }

    public function logout(): void
    {
        $cookieName = (string) Config::get('auth.remember_name', 'remember_token');
        $cookie = $_COOKIE[$cookieName] ?? null;
        if ($cookie && str_contains($cookie, ':')) {
            [$selector] = explode(':', $cookie, 2);
            QueryBuilder::table('remember_tokens')->where('selector', $selector)->delete();
            setcookie($cookieName, '', ['expires' => time() - 3600, 'path' => '/']);
        }

        $this->session->forget((string) Config::get('auth.session_key', 'auth_user_id'));
        $this->session->destroy();
        $this->user = null;
        $this->resolved = false;
    }
}
