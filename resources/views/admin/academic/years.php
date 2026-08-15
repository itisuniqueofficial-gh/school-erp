<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $years */
$tabs = ['years' => 'Years', 'classes' => 'Classes', 'sections' => 'Sections', 'subjects' => 'Subjects', 'class-subjects' => 'Class Subjects'];
$active = 'years';
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('main'); ?>
<div class="mb-5 flex flex-wrap gap-1 border-b border-slate-200">
    <?php foreach ($tabs as $k => $label): ?>
        <a href="<?= e(url('/admin/academic/' . $k)) ?>" class="border-b-2 px-4 py-2 text-sm font-medium <?= $k === $active ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <div class="card overflow-hidden">
            <table class="data-table">
                <thead><tr><th>ID</th><th>Name</th><th>Start</th><th>End</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php if (empty($years)): ?><tr><td colspan="6" class="py-8 text-center text-slate-400">No years defined.</td></tr>
                <?php else: foreach ($years as $y): $cur = !empty($y['is_current']); $arch = ($y['status'] ?? 'active') === 'archived'; ?>
                    <tr>
                        <td class="font-mono text-xs"><?= e((string) ($y['public_id'] ?? '—')) ?></td>
                        <td class="font-medium text-slate-800"><?= e((string) $y['name']) ?> <?= $cur ? '<span class="badge badge-green ml-1">Current</span>' : '' ?></td>
                        <td><?= e((string) $y['start_date']) ?></td>
                        <td><?= e((string) $y['end_date']) ?></td>
                        <td><span class="badge <?= $arch ? 'badge-red' : 'badge-slate' ?>"><?= e(ucfirst((string) ($y['status'] ?? 'active'))) ?></span></td>
                        <td class="text-right">
                            <?php if (auth()->can('academic.manage')): ?>
                            <?php if (!$cur): ?>
                            <form method="POST" action="<?= e(url('/admin/academic/years/' . $y['id'] . '/activate')) ?>" class="inline" data-confirm="Make <?= e((string) $y['name']) ?> the current academic year?"><?= csrf_field() ?><button class="action-link text-green-600" aria-label="Activate year"><i class="fa-solid fa-circle-check"></i> Activate</button></form>
                            <?php endif; ?>
                            <details class="inline-block text-left align-middle">
                                <summary class="action-link ml-2 cursor-pointer text-brand-600"><i class="fa-solid fa-pen"></i> Edit</summary>
                                <form method="POST" action="<?= e(url('/admin/academic/years/' . $y['id'])) ?>" class="mt-2 space-y-2 rounded-lg border border-slate-200 p-3" data-loading>
                                    <?= csrf_field() ?><?= method_field('PUT') ?>
                                    <input name="name" class="form-input" value="<?= e((string) $y['name']) ?>" required>
                                    <input name="start_date" type="date" class="form-input" value="<?= e((string) $y['start_date']) ?>" required>
                                    <input name="end_date" type="date" class="form-input" value="<?= e((string) $y['end_date']) ?>" required>
                                    <select name="status" class="form-select"><option value="active" <?= !$arch ? 'selected' : '' ?>>Active</option><option value="archived" <?= $arch ? 'selected' : '' ?>>Archived</option></select>
                                    <button class="btn btn-primary btn-sm w-full justify-center">Save</button>
                                </form>
                            </details>
                            <?php if (!$cur): ?>
                            <form method="POST" action="<?= e(url('/admin/academic/years/' . $y['id'] . '/archive')) ?>" class="inline" data-confirm="<?= $arch ? 'Restore this year?' : 'Archive this year? Historical records are preserved.' ?>"><?= csrf_field() ?><button class="action-link ml-2 text-amber-600"><i class="fa-solid fa-box-archive"></i> <?= $arch ? 'Restore' : 'Archive' ?></button></form>
                            <form method="POST" action="<?= e(url('/admin/academic/years/' . $y['id'])) ?>" class="inline" data-confirm="Permanently delete this academic year? Only allowed when it has no enrollments, assignments or exams."><?= csrf_field() ?><?= method_field('DELETE') ?><button class="action-link ml-2 text-red-600"><i class="fa-solid fa-trash"></i> Delete</button></form>
                            <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if (auth()->can('academic.manage')): ?>
    <div class="card p-5">
        <h2 class="font-semibold text-slate-800">Add academic year</h2>
        <form method="POST" action="<?= e(url('/admin/academic/years')) ?>" class="mt-4 space-y-3" data-loading>
            <?= csrf_field() ?>
            <div><label class="form-label required" for="name">Name</label><input id="name" name="name" class="form-input" placeholder="2026-2027" required></div>
            <div><label class="form-label required" for="start_date">Start date</label><input id="start_date" name="start_date" type="date" class="form-input" required></div>
            <div><label class="form-label required" for="end_date">End date</label><input id="end_date" name="end_date" type="date" class="form-input" required></div>
            <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="is_current" value="1" class="rounded border-slate-300 text-brand-600"> Set as current</label>
            <button class="btn btn-primary w-full justify-center"><i class="fa-solid fa-plus"></i> Add year</button>
        </form>
    </div>

    <div class="card p-5">
        <h2 class="font-semibold text-slate-800">Copy previous year setup</h2>
        <p class="mb-3 mt-1 text-xs text-slate-400">Copies teacher &amp; class-teacher assignments into another year. Students, attendance, marks and fees are never copied.</p>
        <form method="POST" action="<?= e(url('/admin/academic/years/copy')) ?>" class="space-y-3" data-loading data-confirm="Copy the selected year's teaching setup into the target year?">
            <?= csrf_field() ?>
            <div><label class="form-label required" for="from_year_id">Copy from</label>
                <select id="from_year_id" name="from_year_id" class="form-select" required><option value="">Source year</option><?php foreach ($years as $y): ?><option value="<?= (int) $y['id'] ?>"><?= e((string) $y['name']) ?></option><?php endforeach; ?></select></div>
            <div><label class="form-label required" for="to_year_id">Copy to</label>
                <select id="to_year_id" name="to_year_id" class="form-select" required><option value="">Target year</option><?php foreach ($years as $y): ?><option value="<?= (int) $y['id'] ?>"><?= e((string) $y['name']) ?></option><?php endforeach; ?></select></div>
            <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="copy_teachers" value="1" checked class="rounded border-slate-300 text-brand-600"> Copy teacher assignments</label>
            <button class="btn btn-secondary w-full justify-center"><i class="fa-solid fa-copy"></i> Copy setup</button>
        </form>
    </div>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
