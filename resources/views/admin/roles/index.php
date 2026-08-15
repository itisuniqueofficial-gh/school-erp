<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $roles */
/** @var array<string,array<int,array<string,mixed>>> $permissions */
?>
<?php $this->extend('layouts.app'); ?>

<?php $this->section('main'); ?>
<div class="grid gap-6 lg:grid-cols-3">
    <!-- Roles list -->
    <div class="lg:col-span-2">
        <div class="card overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="font-semibold text-slate-800">Roles</h2>
                <p class="text-sm text-slate-500">Roles determine what users can access.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Role</th><th>Slug</th><th>Users</th><th>Permissions</th><th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roles as $role): ?>
                        <tr>
                            <td class="font-medium text-slate-800"><?= e((string) $role['name']) ?></td>
                            <td><code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs"><?= e((string) $role['slug']) ?></code></td>
                            <td><?= (int) ($role['user_count'] ?? 0) ?></td>
                            <td><span class="badge badge-blue"><?= (int) ($role['permission_count'] ?? 0) ?></span></td>
                            <td class="text-right">
                                <div class="action-group action-end">
                                <?php if ((int) ($role['is_system'] ?? 0) === 1): ?>
                                    <span class="badge badge-slate">System</span>
                                <?php else: ?>
                                    <form method="POST" action="<?= e(url('/admin/roles/' . $role['id'])) ?>" class="inline">
                                        <?= csrf_field() ?><?= method_field('DELETE') ?>
                                        <button type="submit" data-confirm="Delete role '<?= e((string) $role['name']) ?>'?"
                                                class="action-icon action-danger" aria-label="Delete role">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Create role -->
    <div>
        <div class="card p-5">
            <h2 class="font-semibold text-slate-800">Create a role</h2>
            <form method="POST" action="<?= e(url('/admin/roles')) ?>" class="mt-4 space-y-4" data-loading>
                <?= csrf_field() ?>
                <div>
                    <label class="form-label required" for="name">Name</label>
                    <input id="name" name="name" class="form-input" value="<?= e((string) old('name')) ?>" required>
                </div>
                <div>
                    <label class="form-label required" for="slug">Slug</label>
                    <input id="slug" name="slug" class="form-input" placeholder="e.g. lab_assistant" value="<?= e((string) old('slug')) ?>" required>
                </div>
                <div>
                    <label class="form-label" for="description">Description</label>
                    <input id="description" name="description" class="form-input" value="<?= e((string) old('description')) ?>">
                </div>

                <div>
                    <p class="form-label">Permissions</p>
                    <div class="max-h-72 space-y-3 overflow-y-auto rounded-lg border border-slate-200 p-3">
                        <?php foreach ($permissions as $module => $perms): ?>
                        <fieldset>
                            <legend class="text-xs font-semibold uppercase tracking-wide text-slate-400"><?= e($module) ?></legend>
                            <div class="mt-1 space-y-1">
                                <?php foreach ($perms as $perm): ?>
                                <label class="flex items-center gap-2 text-sm text-slate-600">
                                    <input type="checkbox" name="permissions[]" value="<?= (int) $perm['id'] ?>"
                                           class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                    <?= e((string) $perm['slug']) ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-full justify-center">
                    <i class="fa-solid fa-plus"></i> Create role
                </button>
            </form>
        </div>
    </div>
</div>
<?php $this->endSection(); ?>
