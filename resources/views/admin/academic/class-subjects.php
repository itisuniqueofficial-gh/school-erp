<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $classes */
/** @var array<int,array<string,mixed>> $subjects */
/** @var array<int,array<string,mixed>> $allocations */
/** @var array{class_id:int} $selected */
/** @var array<string,string> $types */
/** @var array{classes_without_subjects:array<int,string>,classes_without_mandatory:array<int,string>} $health */
$typeBadge = ['core' => 'badge-green', 'language' => 'badge-green', 'optional' => 'badge-amber', 'elective' => 'badge-amber', 'extra' => 'badge-slate', 'practical' => 'badge-blue'];
$tabs = ['years' => 'Years', 'classes' => 'Classes', 'sections' => 'Sections', 'subjects' => 'Subjects', 'class-subjects' => 'Class Subjects'];
$active = 'class-subjects';
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('main'); ?>
<?php if (!empty($health['classes_without_subjects']) || !empty($health['classes_without_mandatory'])): ?>
<div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
    <i class="fa-solid fa-heart-pulse"></i> <b>Academic setup health:</b>
    <?php if (!empty($health['classes_without_subjects'])): ?><div>Classes without any subjects: <?= e(implode(', ', $health['classes_without_subjects'])) ?></div><?php endif; ?>
    <?php if (!empty($health['classes_without_mandatory'])): ?><div>Classes with no mandatory subject: <?= e(implode(', ', $health['classes_without_mandatory'])) ?></div><?php endif; ?>
</div>
<?php endif; ?>
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
                <thead><tr><th>Subject</th><th>Type</th><th>Max</th><th>Pass</th><th>Order</th><th class="text-right">Action</th></tr></thead>
                <tbody>
                <?php if (empty($allocations)): ?><tr><td colspan="6" class="py-8 text-center text-slate-400">No subjects allocated to this class yet.</td></tr>
                <?php else: foreach ($allocations as $a): ?>
                    <tr>
                        <td class="font-medium text-slate-800"><?= e((string) $a['subject_name']) ?> <code class="ml-1 text-xs text-slate-400"><?= e((string) $a['subject_code']) ?></code></td>
                        <td>
                            <span class="badge <?= $typeBadge[$a['subject_type'] ?? 'core'] ?? 'badge-slate' ?>"><?= e($types[$a['subject_type'] ?? 'core'] ?? ucfirst((string) ($a['subject_type'] ?? 'core'))) ?></span>
                            <?php if ((int) ($a['is_mandatory'] ?? 1) === 1): ?><span class="ml-1 text-xs font-semibold text-slate-500">Mandatory</span><?php else: ?><span class="ml-1 text-xs text-slate-400">Optional</span><?php endif; ?>
                        </td>
                        <td><?= e((string) $a['full_marks']) ?></td>
                        <td><?= e((string) $a['pass_marks']) ?></td>
                        <td class="whitespace-nowrap">
                            <?php if (auth()->can('academic.manage')): ?>
                            <form method="POST" action="<?= e(url('/admin/academic/class-subjects/' . $a['id'] . '/move')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="direction" value="up"><button class="text-slate-500 hover:text-slate-800" title="Move up"><i class="fa-solid fa-arrow-up"></i></button></form>
                            <form method="POST" action="<?= e(url('/admin/academic/class-subjects/' . $a['id'] . '/move')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="direction" value="down"><button class="ml-1 text-slate-500 hover:text-slate-800" title="Move down"><i class="fa-solid fa-arrow-down"></i></button></form>
                            <?php endif; ?>
                        </td>
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
                <div class="flex-1"><label class="form-label" for="subject_type">Type</label>
                    <select id="subject_type" name="subject_type" class="form-select">
                        <?php foreach ($types as $k => $lbl): ?><option value="<?= e($k) ?>"><?= e($lbl) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end pb-1"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_mandatory" value="1" checked class="rounded border-slate-300"> Mandatory</label></div>
            </div>
            <div class="flex gap-2">
                <div><label class="form-label" for="full_marks">Max marks</label><input id="full_marks" name="full_marks" type="number" class="form-input" value="100"></div>
                <div><label class="form-label" for="pass_marks">Pass marks</label><input id="pass_marks" name="pass_marks" type="number" class="form-input" value="33"></div>
            </div>
            <button class="btn btn-primary w-full justify-center"><i class="fa-solid fa-plus"></i> Allocate</button>
        </form>
        <hr class="my-4 border-slate-100">
        <h3 class="text-sm font-semibold text-slate-800">Copy from another class</h3>
        <form method="POST" action="<?= e(url('/admin/academic/class-subjects/copy')) ?>" class="mt-2 space-y-2" data-loading>
            <?= csrf_field() ?>
            <input type="hidden" name="class_id" value="<?= $selected['class_id'] ?>">
            <select name="from_class_id" class="form-select" required>
                <option value="">Source class</option>
                <?php foreach ($classes as $c): if ((int) $c['id'] === $selected['class_id']) continue; ?><option value="<?= (int) $c['id'] ?>"><?= e((string) $c['name']) ?></option><?php endforeach; ?>
            </select>
            <button class="btn btn-secondary w-full justify-center" data-confirm="Copy the subject structure from the selected class into this class?"><i class="fa-solid fa-copy"></i> Copy structure</button>
        </form>
    </div>
    <?php endif; ?>
</div>
<?php else: ?>
    <div class="card p-8 text-center text-slate-400">Select a class to manage its subject allocations.</div>
<?php endif; ?>
<?php $this->endSection(); ?>
