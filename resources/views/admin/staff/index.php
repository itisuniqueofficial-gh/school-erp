<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $staff */
/** @var array<string,int> $meta */
/** @var array<string,mixed> $filters */
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('main'); ?>
<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <form method="GET" class="card mb-4 p-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <input type="text" name="search" value="<?= e((string) $filters['search']) ?>" placeholder="Search staff" class="form-input sm:col-span-2">
                <button class="btn btn-secondary justify-center"><i class="fa-solid fa-filter"></i> Filter</button>
            </div>
        </form>
        <div class="card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Designation</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                    <tbody>
                    <?php if (empty($staff)): ?>
                        <tr><td colspan="6" class="py-10 text-center text-slate-400">No staff found.</td></tr>
                    <?php else: foreach ($staff as $s): ?>
                        <tr>
                            <td class="font-mono text-xs"><?= e((string) $s['employee_code']) ?></td>
                            <td class="font-medium text-slate-800"><?= e((string) ($s['first_name'] . ' ' . $s['last_name'])) ?></td>
                            <td><span class="badge badge-slate"><?= e(str_replace('_', ' ', (string) $s['staff_type'])) ?></span></td>
                            <td><?= e((string) ($s['designation'] ?? '—')) ?></td>
                            <td><span class="badge <?= $s['status'] === 'active' ? 'badge-green' : 'badge-slate' ?>"><?= e(ucfirst(str_replace('_',' ',(string) $s['status']))) ?></span></td>
                            <td class="text-right">
                                <div class="action-group action-end">
                                <?php if (auth()->can('staff.delete')): ?>
                                <button type="button" data-delete-url="<?= e(url('/admin/staff/' . $s['id'])) ?>" data-confirm="Remove this staff member?" class="action-icon action-danger" aria-label="Remove staff member"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                                <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php $this->include('partials.pagination', ['meta' => $meta]); ?>
    </div>

    <?php if (auth()->can('staff.create')): ?>
    <div>
        <div class="card p-5">
            <h2 class="font-semibold text-slate-800">Add staff</h2>
            <form method="POST" action="<?= e(url('/admin/staff')) ?>" class="mt-4 space-y-3" data-loading>
                <?= csrf_field() ?>
                <div><label class="form-label required" for="first_name">First name</label><input id="first_name" name="first_name" class="form-input" required></div>
                <div><label class="form-label required" for="last_name">Last name</label><input id="last_name" name="last_name" class="form-input" required></div>
                <div><label class="form-label required" for="staff_type">Type</label><select id="staff_type" name="staff_type" class="form-select" required><option value="non_teaching">Non-teaching</option><option value="teaching">Teaching</option></select></div>
                <div><label class="form-label" for="designation">Designation</label><input id="designation" name="designation" class="form-input"></div>
                <div><label class="form-label" for="email">Email</label><input id="email" name="email" type="email" class="form-input"></div>
                <div><label class="form-label" for="basic_salary">Basic salary</label><input id="basic_salary" name="basic_salary" type="number" step="0.01" class="form-input"></div>
                <button class="btn btn-primary w-full justify-center"><i class="fa-solid fa-plus"></i> Add staff</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
<form id="deleteForm" method="POST" class="hidden"><?= csrf_field() ?><?= method_field('DELETE') ?></form>
<?php $this->endSection(); ?>
<?php $this->section('page_scripts'); ?>
<script>$(function(){ $('[data-delete-url]').on('click', function(){ if(confirm($(this).data('confirm')||'Delete?')){ const f=document.getElementById('deleteForm'); f.action=$(this).data('delete-url'); f.submit(); }}); });</script>
<?php $this->endSection(); ?>
