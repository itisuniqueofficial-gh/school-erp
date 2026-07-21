<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Manages roles and their permission assignments (RBAC administration).
 */
final class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('admin.roles.index', [
            'title'       => 'Roles & Access',
            'roles'       => Role::withPermissionCounts(),
            'permissions' => Permission::groupedByModule(),
        ]);
    }

    public function store(Request $request): Response
    {
        $data = $this->validate($request, [
            'name'        => 'required|string|max:120',
            'slug'        => 'required|string|max:80|regex:/^[a-z0-9_]+$/|unique:roles,slug',
            'description' => 'nullable|string|max:255',
        ], [
            'slug' => 'Slug must be lowercase letters, numbers and underscores, and unique.',
        ]);

        $roleId = Role::create([
            'name'        => $data['name'],
            'slug'        => $data['slug'],
            'description' => $data['description'] ?? null,
            'is_system'   => 0,
        ]);

        $permissionIds = array_map('intval', (array) $request->input('permissions', []));
        Role::syncPermissions($roleId, $permissionIds);

        AuditLogger::log('role.created', 'role', $roleId, 'Created role ' . $data['slug']);

        return $this->redirect('/admin/roles', ['success' => 'Role created successfully.']);
    }

    public function update(Request $request, string $id): Response
    {
        $roleId = (int) $id;
        $role = Role::find($roleId);
        if ($role === null) {
            abort(404, 'Role not found.');
        }

        $data = $this->validate($request, [
            'name'        => 'required|string|max:120',
            'description' => 'nullable|string|max:255',
        ]);

        Role::updateById($roleId, [
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        $permissionIds = array_map('intval', (array) $request->input('permissions', []));
        Role::syncPermissions($roleId, $permissionIds);

        AuditLogger::log('role.updated', 'role', $roleId, 'Updated role ' . ($role['slug'] ?? ''));

        return $this->redirect('/admin/roles', ['success' => 'Role updated successfully.']);
    }

    public function destroy(Request $request, string $id): Response
    {
        $roleId = (int) $id;
        $role = Role::find($roleId);
        if ($role === null) {
            abort(404, 'Role not found.');
        }

        if ((int) ($role['is_system'] ?? 0) === 1) {
            return $this->redirect('/admin/roles', ['error' => 'System roles cannot be deleted.']);
        }

        Role::deleteById($roleId);
        AuditLogger::log('role.deleted', 'role', $roleId, 'Deleted role ' . ($role['slug'] ?? ''));

        return $this->redirect('/admin/roles', ['success' => 'Role deleted.']);
    }
}
