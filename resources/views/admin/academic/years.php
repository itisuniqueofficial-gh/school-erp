<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $years */
$tabs = ['years' => 'Years', 'classes' => 'Classes', 'sections' => 'Sections', 'subjects' => 'Subjects'];
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
                <thead><tr><th>Name</th><th>Start</th><th>End</th><th>Current</th></tr></thead>
                <tbody>
                <?php if (empty($years)): ?><tr><td colspan="4" class="py-8 text-center text-slate-400">No years defined.</td></tr>
                <?php else: foreach ($years as $y): ?>
                    <tr>
                        <td class="font-medium text-slate-800"><?= e((string) $y['name']) ?></td>
                        <td><?= e((string) $y['start_date']) ?></td>
                        <td><?= e((string) $y['end_date']) ?></td>
                        <td><?= !empty($y['is_current']) ? '<span class="badge badge-green">Current</span>' : '' ?></td>
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
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
