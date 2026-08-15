<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $classes */
$tabs = ['years' => 'Years', 'classes' => 'Classes', 'sections' => 'Sections', 'subjects' => 'Subjects', 'class-subjects' => 'Class Subjects'];
$active = 'classes';
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
                <thead><tr><th>Class</th><th>Level</th><th>Status</th><th class="text-right">Manage</th></tr></thead>
                <tbody>
                <?php if (empty($classes)): ?><tr><td colspan="4" class="py-8 text-center text-slate-400">No classes.</td></tr>
                <?php else: foreach ($classes as $c): $arch = ($c['status'] ?? 'active') === 'archived'; ?>
                    <tr>
                        <td class="font-medium text-slate-800"><?= e((string) $c['name']) ?><?php if (!empty($c['code'])): ?> <span class="text-xs text-slate-400">(<?= e((string) $c['code']) ?>)</span><?php endif; ?></td>
                        <td><?= e((string) $c['numeric_level']) ?></td>
                        <td><span class="badge <?= $arch ? 'badge-red' : 'badge-green' ?>"><?= e(ucfirst((string) ($c['status'] ?? 'active'))) ?></span></td>
                        <td class="text-right"><div class="action-group action-end"><a href="<?= e(url('/admin/academic/classes/' . $c['id'])) ?>" class="action-link text-brand-600" aria-label="Manage class"><i class="fa-solid fa-gear"></i> Manage</a></div></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if (auth()->can('academic.manage')): ?>
    <div class="card p-5">
        <h2 class="font-semibold text-slate-800">Add class</h2>
        <form method="POST" action="<?= e(url('/admin/academic/classes')) ?>" class="mt-4 space-y-3" data-loading>
            <?= csrf_field() ?>
            <div><label class="form-label required" for="name">Name</label><input id="name" name="name" class="form-input" placeholder="Class 6" required></div>
            <div><label class="form-label required" for="numeric_level">Numeric level</label><input id="numeric_level" name="numeric_level" type="number" class="form-input" required></div>
            <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="uses_sections" value="1" checked class="rounded border-slate-300 text-brand-600"> This class uses sections</label>
            <button class="btn btn-primary w-full justify-center"><i class="fa-solid fa-plus"></i> Add class</button>
        </form>
    </div>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
