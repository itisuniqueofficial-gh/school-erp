<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\AuditLogger;
use Core\Database\QueryBuilder;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Admin User Management — lists every ERP user with their unique 12-digit
 * User ID, roles, status, last login and created date. Read + activate/deactivate
 * only (users with historical dependencies are never hard-deleted here).
 */
final class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $role   = trim((string) $request->query('role', ''));
        $status = trim((string) $request->query('status', ''));
        $page   = max(1, (int) $request->query('page', 1));
        $perPage = 20;

        $build = function () use ($search, $role, $status): QueryBuilder {
            $q = QueryBuilder::table('users')->whereNull('users.deleted_at');
            if ($search !== '') {
                $t = '%' . $search . '%';
                $q->whereRaw('(`users`.`name` LIKE ? OR `users`.`email` LIKE ? OR `users`.`uid` LIKE ? OR `users`.`username` LIKE ?)', [$t, $t, $t, $t]);
            }
            if ($status !== '') {
                $q->where('users.is_active', $status === 'active' ? 1 : 0);
            }
            if ($role !== '') {
                $q->whereRaw('`users`.`id` IN (SELECT `ur`.`user_id` FROM `user_roles` `ur` JOIN `roles` `r` ON `ur`.`role_id` = `r`.`id` WHERE `r`.`slug` = ?)', [$role]);
            }
            return $q;
        };

        $total = $build()->count();
        $rows = $build()
            ->selectRaw("`users`.`id`, `users`.`uid`, `users`.`public_id`, `users`.`name`, `users`.`email`, `users`.`username`, `users`.`is_active`, `users`.`last_login_at`, `users`.`created_at`, (SELECT GROUP_CONCAT(`r2`.`name` SEPARATOR ', ') FROM `user_roles` `ur2` JOIN `roles` `r2` ON `ur2`.`role_id` = `r2`.`id` WHERE `ur2`.`user_id` = `users`.`id`) AS role_names")
            ->orderBy('users.id', 'DESC')
            ->forPage($page, $perPage)->get();

        return $this->view('admin.users.index', [
            'title'   => 'User Management',
            'users'   => $rows,
            'roles'   => QueryBuilder::table('roles')->orderBy('name')->get(),
            'filters' => ['search' => $search, 'role' => $role, 'status' => $status],
            'meta'    => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => (int) max(1, ceil($total / $perPage)),
                'from'         => $total ? (($page - 1) * $perPage) + 1 : 0,
                'to'           => min($page * $perPage, $total),
            ],
        ]);
    }

    /** Activate / deactivate a user (never a hard delete). */
    public function toggleActive(Request $request, string $id): Response
    {
        $user = QueryBuilder::table('users')->where('id', (int) $id)->whereNull('deleted_at')->first();
        if ($user === null) {
            abort(404, 'User not found.');
        }
        // Guard: don't let an admin deactivate their own account.
        if ((int) $id === (int) auth()->id()) {
            return $this->redirect('/admin/users', ['error' => 'You cannot change your own account status.']);
        }
        $new = (int) ($user['is_active'] ?? 0) === 1 ? 0 : 1;
        QueryBuilder::table('users')->where('id', (int) $id)->update(['is_active' => $new, 'updated_at' => date('Y-m-d H:i:s')]);
        AuditLogger::log('user.' . ($new ? 'activated' : 'deactivated'), 'user', (int) $id, ($new ? 'Activated' : 'Deactivated') . ' user ' . ($user['uid'] ?? ''));
        return $this->redirect('/admin/users', ['success' => 'User ' . ($new ? 'activated' : 'deactivated') . '.']);
    }
}
