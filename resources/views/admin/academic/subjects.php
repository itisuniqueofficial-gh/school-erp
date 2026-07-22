<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $subjects */
$tabs = ['years' => 'Years', 'classes' => 'Classes', 'sections' => 'Sections', 'subjects' => 'Subjects', 'class-subjects' => 'Class Subjects'];
$active = 'subjects';
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
                <thead><tr><th>Subject</th><th>Code</th><th>Type</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php if (empty($subjects)): ?><tr><td colspan="5" class="py-8 text-center text-slate-400">No subjects.</td></tr>
                <?php else: foreach ($subjects as $s): $arch = ($s['status'] ?? 'active') === 'archived'; ?>
                    <tr>
                        <td class="font-medium text-slate-800"><?= e((string) $s['name']) ?></td>
                        <td><code class="text-xs"><?= e((string) $s['code']) ?></code></td>
                        <td><span class="badge badge-slate"><?= e((string) $s['type']) ?></span></td>
                        <td><span class="badge <?= $arch ? 'badge-red' : 'badge-green' ?>"><?= e(ucfirst((string) ($s['status'] ?? 'active'))) ?></span></td>
                        <td class="text-right">
                            <?php if (auth()->can('academic.manage')): ?>
                            <details class="inline-block text-left">
                                <summary class="cursor-pointer text-brand-600 hover:underline"><i class="fa-solid fa-pen"></i> Edit</summary>
                                <form method="POST" action="<?= e(url('/admin/academic/subjects/' . $s['id'])) ?>" class="mt-2 space-y-2 rounded-lg border border-slate-200 p-3" data-loading>
                                    <?= csrf_field() ?><?= method_field('PUT') ?>
                                    <input name="name" class="form-input" value="<?= e((string) $s['name']) ?>" required>
                                    <input name="code" class="form-input" value="<?= e((string) $s['code']) ?>" required>
                                    <select name="type" class="form-select"><?php foreach (['theory','practical','both'] as $t): ?><option value="<?= $t ?>" <?= ($s['type'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option><?php endforeach; ?></select>
                                    <div class="flex gap-2"><input name="max_marks" type="number" class="form-input" value="<?= e((string) ($s['max_marks'] ?? 100)) ?>" placeholder="Max"><input name="pass_marks" type="number" class="form-input" value="<?= e((string) ($s['pass_marks'] ?? 33)) ?>" placeholder="Pass"></div>
                                    <select name="status" class="form-select"><option value="active" <?= !$arch ? 'selected' : '' ?>>Active</option><option value="archived" <?= $arch ? 'selected' : '' ?>>Archived</option></select>
                                    <button class="btn btn-primary btn-sm w-full justify-center">Save</button>
                                </form>
                            </details>
                            <form method="POST" action="<?= e(url('/admin/academic/subjects/' . $s['id'] . '/archive')) ?>" class="inline" data-confirm="<?= $arch ? 'Restore this subject?' : 'Archive this subject? Historical marks are preserved.' ?>">
                                <?= csrf_field() ?><button class="ml-2 text-amber-600 hover:underline"><i class="fa-solid fa-box-archive"></i> <?= $arch ? 'Restore' : 'Archive' ?></button>
                            </form>
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
        <h2 class="font-semibold text-slate-800">Add subject</h2>
        <form method="POST" action="<?= e(url('/admin/academic/subjects')) ?>" class="mt-4 space-y-3" data-loading>
            <?= csrf_field() ?>
            <div><label class="form-label required" for="name">Name</label><input id="name" name="name" class="form-input" required></div>
            <div><label class="form-label required" for="code">Code</label><input id="code" name="code" class="form-input" placeholder="MATH" required></div>
            <div><label class="form-label required" for="type">Type</label><select id="type" name="type" class="form-select" required><option value="theory">Theory</option><option value="practical">Practical</option><option value="both">Both</option></select></div>
            <button class="btn btn-primary w-full justify-center"><i class="fa-solid fa-plus"></i> Add subject</button>
        </form>
    </div>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
