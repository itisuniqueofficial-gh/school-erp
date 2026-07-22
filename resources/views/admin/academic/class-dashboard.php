<?php
/** @var \Core\View\View $this */
/** @var array<string,mixed> $class */
/** @var array<int,array<string,mixed>> $sections */
/** @var array<int,array<string,mixed>> $subjects */
/** @var array<int,array<string,mixed>> $teachers */
/** @var int $studentCount */
$archived = ($class['status'] ?? 'active') === 'archived';
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('breadcrumb'); ?><a href="<?= e(url('/admin/academic/classes')) ?>" class="hover:text-brand-600">Classes</a> / <?= e((string) $class['name']) ?><?php $this->endSection(); ?>
<?php $this->section('main'); ?>
<div class="mb-4 flex flex-wrap items-center gap-3">
    <h2 class="text-lg font-bold text-slate-900"><?= e((string) $class['name']) ?></h2>
    <span class="badge <?= $archived ? 'badge-red' : 'badge-green' ?>"><?= e(ucfirst((string) ($class['status'] ?? 'active'))) ?></span>
    <?php if (!empty($class['code'])): ?><span class="badge badge-slate"><?= e((string) $class['code']) ?></span><?php endif; ?>
</div>

<div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
    <div class="card p-5"><p class="text-xs uppercase tracking-wide text-slate-400">Students</p><p class="mt-1 text-2xl font-bold text-slate-900"><?= (int) $studentCount ?></p></div>
    <div class="card p-5"><p class="text-xs uppercase tracking-wide text-slate-400">Sections</p><p class="mt-1 text-2xl font-bold text-slate-900"><?= count($sections) ?></p></div>
    <div class="card p-5"><p class="text-xs uppercase tracking-wide text-slate-400">Subjects</p><p class="mt-1 text-2xl font-bold text-slate-900"><?= count($subjects) ?></p></div>
    <div class="card p-5"><p class="text-xs uppercase tracking-wide text-slate-400">Teachers</p><p class="mt-1 text-2xl font-bold text-slate-900"><?= count($teachers) ?></p></div>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="card p-5">
        <h2 class="mb-3 font-semibold text-slate-800">Sections</h2>
        <?php if (empty($sections)): ?><p class="text-sm text-slate-400">None.</p><?php else: ?>
        <div class="flex flex-wrap gap-2"><?php foreach ($sections as $s): ?><span class="badge badge-blue"><?= e((string) $s['name']) ?></span><?php endforeach; ?></div>
        <?php endif; ?>
    </div>
    <div class="card p-5">
        <h2 class="mb-3 font-semibold text-slate-800">Subjects</h2>
        <?php if (empty($subjects)): ?><p class="text-sm text-slate-400">None allocated.</p><?php else: ?>
        <div class="flex flex-wrap gap-2"><?php foreach ($subjects as $s): ?><span class="badge badge-slate"><?= e((string) $s['name']) ?></span><?php endforeach; ?></div>
        <?php endif; ?>
    </div>
    <div class="card p-5">
        <h2 class="mb-3 font-semibold text-slate-800">Teachers</h2>
        <?php if (empty($teachers)): ?><p class="text-sm text-slate-400">No assignments.</p><?php else: ?>
        <ul class="space-y-1 text-sm text-slate-700">
            <?php foreach ($teachers as $t): ?><li><?= e((string) $t['name']) ?> <span class="text-slate-400">— <?= e((string) ($t['subject_name'] ?? '')) ?> (<?= e((string) ($t['section_name'] ?? '')) ?>)</span></li><?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>

<?php if (auth()->can('academic.manage')): ?>
<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
    <div class="card p-5">
        <h2 class="mb-4 font-semibold text-slate-800">Edit class</h2>
        <form method="POST" action="<?= e(url('/admin/academic/classes/' . $class['id'])) ?>" class="space-y-3" data-loading>
            <?= csrf_field() ?><?= method_field('PUT') ?>
            <div><label class="form-label required" for="name">Name</label><input id="name" name="name" class="form-input" value="<?= e((string) $class['name']) ?>" required></div>
            <div><label class="form-label" for="code">Code</label><input id="code" name="code" class="form-input" value="<?= e((string) ($class['code'] ?? '')) ?>"></div>
            <div><label class="form-label required" for="numeric_level">Numeric level</label><input id="numeric_level" name="numeric_level" type="number" class="form-input" value="<?= e((string) $class['numeric_level']) ?>" required></div>
            <div><label class="form-label" for="description">Description</label><input id="description" name="description" class="form-input" value="<?= e((string) ($class['description'] ?? '')) ?>"></div>
            <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="uses_sections" value="1" <?= (int) ($class['uses_sections'] ?? 1) === 1 ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600"> This class uses sections</label>
            <div><label class="form-label required" for="status">Status</label><select id="status" name="status" class="form-select"><option value="active" <?= !$archived ? 'selected' : '' ?>>Active</option><option value="archived" <?= $archived ? 'selected' : '' ?>>Archived</option></select></div>
            <button class="btn btn-primary"><i class="fa-solid fa-check"></i> Save changes</button>
        </form>
    </div>
    <div class="card p-5">
        <h2 class="mb-2 font-semibold text-slate-800"><?= $archived ? 'Restore' : 'Archive' ?> class</h2>
        <p class="mb-4 text-sm text-slate-500">Archiving keeps all historical records (attendance, marks, enrollments) intact but hides the class from active workflows. Classes are never physically deleted.</p>
        <form method="POST" action="<?= e(url('/admin/academic/classes/' . $class['id'] . '/archive')) ?>" data-confirm="<?= $archived ? 'Restore this class to active?' : 'Archive this class? Historical data is preserved.' ?>">
            <?= csrf_field() ?>
            <button class="btn <?= $archived ? 'btn-primary' : 'btn-secondary' ?>"><i class="fa-solid fa-box-archive"></i> <?= $archived ? 'Restore class' : 'Archive class' ?></button>
        </form>
        <?php $deps = $dependencies ?? ['total' => 0, 'items' => []]; ?>
        <div class="mt-4 border-t border-slate-100 pt-4">
            <?php if (($deps['total'] ?? 0) > 0): ?>
                <p class="text-sm text-slate-500">This class has dependent records and <strong>cannot be permanently deleted</strong>:</p>
                <ul class="mt-1 text-sm text-slate-600">
                    <?php foreach ($deps['items'] as $label => $n): ?><li><i class="fa-solid fa-circle-dot mr-1 text-[6px] align-middle text-slate-300"></i> <?= (int) $n ?> <?= e(strtolower($label)) ?></li><?php endforeach; ?>
                </ul>
                <button class="btn btn-secondary mt-3" disabled title="Archive instead"><i class="fa-solid fa-trash"></i> Delete (blocked)</button>
            <?php else: ?>
                <p class="text-sm text-slate-500">This class has no dependent records and can be safely deleted.</p>
                <form method="POST" action="<?= e(url('/admin/academic/classes/' . $class['id'])) ?>" class="mt-3" data-confirm="Permanently delete this class? This cannot be undone.">
                    <?= csrf_field() ?><?= method_field('DELETE') ?>
                    <button class="btn btn-secondary text-red-600"><i class="fa-solid fa-trash"></i> Delete class</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>
<?php $this->endSection(); ?>
