<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $sections */
/** @var array<int,array<string,mixed>> $classes */
$tabs = ['years' => 'Years', 'classes' => 'Classes', 'sections' => 'Sections', 'subjects' => 'Subjects', 'class-subjects' => 'Class Subjects'];
$active = 'sections';
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
                <thead><tr><th>Class</th><th>Section</th><th>Capacity</th></tr></thead>
                <tbody>
                <?php if (empty($sections)): ?><tr><td colspan="3" class="py-8 text-center text-slate-400">No sections.</td></tr>
                <?php else: foreach ($sections as $s): ?>
                    <tr><td><?= e((string) ($s['class_name'] ?? '')) ?></td><td class="font-medium text-slate-800"><?= e((string) $s['name']) ?></td><td><?= e((string) $s['capacity']) ?></td></tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if (auth()->can('academic.manage')): ?>
    <div class="card p-5">
        <h2 class="font-semibold text-slate-800">Add section</h2>
        <form method="POST" action="<?= e(url('/admin/academic/sections')) ?>" class="mt-4 space-y-3" data-loading>
            <?= csrf_field() ?>
            <div><label class="form-label required" for="class_id">Class</label><select id="class_id" name="class_id" class="form-select" required><option value="">Select</option><?php foreach($classes as $c):?><option value="<?=(int)$c['id']?>"><?=e((string)$c['name'])?></option><?php endforeach;?></select></div>
            <div><label class="form-label required" for="name">Section name</label><input id="name" name="name" class="form-input" placeholder="A" required></div>
            <div><label class="form-label" for="capacity">Capacity</label><input id="capacity" name="capacity" type="number" class="form-input" value="40"></div>
            <button class="btn btn-primary w-full justify-center"><i class="fa-solid fa-plus"></i> Add section</button>
        </form>
    </div>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
