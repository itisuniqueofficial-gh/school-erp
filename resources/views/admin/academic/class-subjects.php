<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $classes */
/** @var array<int,array<string,mixed>> $subjects */
/** @var array<int,array<string,mixed>> $allocations */
/** @var array{class_id:int} $selected */
$tabs = ['years' => 'Years', 'classes' => 'Classes', 'sections' => 'Sections', 'subjects' => 'Subjects', 'class-subjects' => 'Class Subjects'];
$active = 'class-subjects';
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('main'); ?>
<div class="mb-5 flex flex-wrap gap-1 border-b border-slate-200">
    <?php foreach ($tabs as $k => $label): ?>
        <a href="<?= e(url('/admin/academic/' . $k)) ?>" class="border-b-2 px-4 py-2 text-sm font-medium <?= $k === $active ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-800' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<form method="GET" class="card mb-4 p-4">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <select name="class_id" class="form-select" onchange="this.form.submit()" required>
            <option value="">Select a class</option>
            <?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $selected['class_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e((string) $c['name']) ?> <?= !empty($c['public_id']) ? '(' . e((string) $c['public_id']) . ')' : '' ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-primary justify-center"><i class="fa-solid fa-magnifying-glass"></i> Load allocations</button>
    </div>
</form>

<?php if ($selected['class_id']): ?>
<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <div class="card overflow-hidden">
            <table class="data-table">
                <thead><tr><th>Allocation ID</th><th>Subject</th><th>Code</th><th>Max</th><th>Pass</th><th class="text-right">Action</th></tr></thead>
                <tbody>
                <?php if (empty($allocations)): ?><tr><td colspan="6" class="py-8 text-center text-slate-400">No subjects allocated to this class yet.</td></tr>
                <?php else: foreach ($allocations as $a): ?>
                    <tr>
                        <td class="font-mono text-xs"><?= e((string) ($a['public_id'] ?? '—')) ?></td>
                        <td class="font-medium text-slate-800"><?= e((string) $a['subject_name']) ?></td>
                        <td><code class="text-xs"><?= e((string) $a['subject_code']) ?></code></td>
                        <td><?= e((string) $a['full_marks']) ?></td>
                        <td><?= e((string) $a['pass_marks']) ?></td>
                        <td class="text-right">
                            <?php if (auth()->can('academic.manage')): ?>
                            <form method="POST" action="<?= e(url('/admin/academic/class-subjects/' . $a['id'])) ?>" class="inline"><?= csrf_field() ?><?= method_field('DELETE') ?><button class="text-red-600 hover:text-red-800" data-confirm="Remove this subject allocation? (Historical marks are not affected.)"><i class="fa-solid fa-trash"></i></button></form>
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
        <h2 class="font-semibold text-slate-800">Allocate subject</h2>
        <p class="mb-3 mt-1 text-xs text-slate-400">Duplicate allocations are blocked automatically.</p>
        <form method="POST" action="<?= e(url('/admin/academic/class-subjects')) ?>" class="space-y-3" data-loading>
            <?= csrf_field() ?>
            <input type="hidden" name="class_id" value="<?= $selected['class_id'] ?>">
            <div><label class="form-label required" for="subject_id">Subject</label>
                <select id="subject_id" name="subject_id" class="form-select" required>
                    <option value="">Select subject</option>
                    <?php foreach ($subjects as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e((string) $s['name']) ?> (<?= e((string) $s['code']) ?>)</option><?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-2">
                <div><label class="form-label" for="full_marks">Max marks</label><input id="full_marks" name="full_marks" type="number" class="form-input" value="100"></div>
                <div><label class="form-label" for="pass_marks">Pass marks</label><input id="pass_marks" name="pass_marks" type="number" class="form-input" value="33"></div>
            </div>
            <button class="btn btn-primary w-full justify-center"><i class="fa-solid fa-plus"></i> Allocate</button>
        </form>
    </div>
    <?php endif; ?>
</div>
<?php else: ?>
    <div class="card p-8 text-center text-slate-400">Select a class to manage its subject allocations.</div>
<?php endif; ?>
<?php $this->endSection(); ?>
