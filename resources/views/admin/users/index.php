<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $users */
/** @var array<int,array<string,mixed>> $roles */
/** @var array{search:string,role:string,status:string} $filters */
/** @var array<string,int> $meta */
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('breadcrumb'); ?>System / User Management<?php $this->endSection(); ?>
<?php $this->section('main'); ?>

<form method="GET" class="card mb-4 p-4">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
        <input type="search" name="search" value="<?= e($filters['search']) ?>" placeholder="Search name, email, User ID…" class="form-input">
        <select name="role" class="form-select">
            <option value="">All roles</option>
            <?php foreach ($roles as $r): ?><option value="<?= e((string) $r['slug']) ?>" <?= $filters['role'] === $r['slug'] ? 'selected' : '' ?>><?= e((string) $r['name']) ?></option><?php endforeach; ?>
        </select>
        <select name="status" class="form-select">
            <option value="">All statuses</option>
            <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
        <button class="btn btn-primary justify-center"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
    </div>
</form>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="data-table table-cards">
            <thead><tr><th>User ID</th><th>Name</th><th>Email</th><th>Roles</th><th>Status</th><th>Last Login</th><th>Created</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
            <?php if (empty($users)): ?>
                <tr><td colspan="8" class="py-8 text-center text-slate-400">No users match these filters.</td></tr>
            <?php else: foreach ($users as $u): $active = (int) ($u['is_active'] ?? 0) === 1; ?>
                <tr>
                    <td data-label="User ID">
                        <span class="font-mono text-sm"><?= e((string) ($u['uid'] ?? '—')) ?></span>
                        <?php if (!empty($u['uid'])): ?><button type="button" data-copy="<?= e((string) $u['uid']) ?>" class="ml-1 text-brand-600 hover:text-brand-800" aria-label="Copy User ID"><i class="fa-solid fa-copy"></i></button><?php endif; ?>
                    </td>
                    <td data-label="Name" class="font-medium text-slate-800"><?= e((string) $u['name']) ?></td>
                    <td data-label="Email"><?= e((string) ($u['email'] ?? '—')) ?></td>
                    <td data-label="Roles"><?php foreach (array_filter(array_map('trim', explode(',', (string) ($u['role_names'] ?? '')))) as $rn): ?><span class="badge badge-slate mr-1"><?= e($rn) ?></span><?php endforeach; ?></td>
                    <td data-label="Status"><span class="badge <?= $active ? 'badge-green' : 'badge-red' ?>"><?= $active ? 'Active' : 'Inactive' ?></span></td>
                    <td data-label="Last Login" class="text-slate-500"><?= e((string) ($u['last_login_at'] ?? 'Never')) ?></td>
                    <td data-label="Created" class="text-slate-500"><?= e((string) ($u['created_at'] ?? '')) ?></td>
                    <td data-label="" class="text-right">
                        <?php if (auth()->can('users.manage')): ?>
                        <form method="POST" action="<?= e(url('/admin/users/' . $u['id'] . '/toggle')) ?>" class="inline" data-confirm="<?= $active ? 'Deactivate' : 'Activate' ?> this user?">
                            <?= csrf_field() ?>
                            <button class="<?= $active ? 'text-red-600' : 'text-green-600' ?> hover:underline"><i class="fa-solid <?= $active ? 'fa-user-slash' : 'fa-user-check' ?>"></i> <?= $active ? 'Deactivate' : 'Activate' ?></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?php $this->include('partials.pagination', ['meta' => $meta, 'baseQuery' => $filters]); ?>
</div>
<?php $this->endSection(); ?>
