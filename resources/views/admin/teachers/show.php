<?php
/** @var \Core\View\View $this */
/** @var array<string,mixed> $teacher */
/** @var array<int,array<string,mixed>> $qualifications */
/** @var array<int,array<string,mixed>> $subjects */
$name = $teacher['first_name'] . ' ' . $teacher['last_name'];
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('breadcrumb'); ?><a href="<?= e(url('/admin/teachers')) ?>" class="hover:text-brand-600">Teachers</a> / Profile<?php $this->endSection(); ?>
<?php $this->section('page_actions'); ?>
<?php if (auth()->can('teachers.update')): ?><a href="<?= e(url('/admin/teachers/' . $teacher['id'] . '/edit')) ?>" class="btn btn-secondary"><i class="fa-solid fa-pen"></i> Edit</a><?php endif; ?>
<?php $this->endSection(); ?>
<?php $this->section('main'); ?>
<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="card p-6 text-center">
        <span class="mx-auto flex h-28 w-28 items-center justify-center rounded-full bg-emerald-600 text-3xl font-bold text-white"><?= e(strtoupper(substr((string) $teacher['first_name'], 0, 1))) ?></span>
        <h2 class="mt-4 text-lg font-bold text-slate-900"><?= e($name) ?></h2>
        <p class="text-sm text-slate-500"><?= e((string) ($teacher['designation'] ?? 'Teacher')) ?> · <?= e((string) $teacher['employee_code']) ?></p>
        <span class="badge <?= $teacher['status'] === 'active' ? 'badge-green' : 'badge-slate' ?> mt-2"><?= e(ucfirst(str_replace('_',' ',(string) $teacher['status']))) ?></span>
    </div>
    <div class="card p-6 lg:col-span-2">
        <h2 class="mb-4 font-semibold text-slate-800">Details</h2>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
            <?php foreach ([
                'Email' => $teacher['email'] ?? '—', 'Phone' => $teacher['phone'] ?? '—',
                'Department' => $teacher['department_name'] ?? '—', 'Qualification' => $teacher['qualification'] ?? '—',
                'Experience' => ($teacher['experience_years'] ?? 0) . ' yrs', 'Joining Date' => $teacher['joining_date'] ?? '—',
            ] as $l => $val): ?>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-400"><?= e($l) ?></dt><dd class="text-sm text-slate-700"><?= e((string) $val) ?></dd></div>
            <?php endforeach; ?>
        </dl>
    </div>
    <div class="card p-6">
        <h2 class="mb-4 font-semibold text-slate-800">Qualifications</h2>
        <?php if (empty($qualifications)): ?><p class="text-sm text-slate-400">None recorded.</p><?php else: ?>
        <ul class="space-y-2"><?php foreach ($qualifications as $q): ?><li class="text-sm text-slate-700"><?= e((string) $q['degree']) ?> <span class="text-slate-400">(<?= e((string) ($q['year'] ?? '')) ?>)</span></li><?php endforeach; ?></ul>
        <?php endif; ?>
    </div>
    <div class="card p-6 lg:col-span-2">
        <h2 class="mb-4 font-semibold text-slate-800">Subjects &amp; classes</h2>
        <?php if (empty($subjects)): ?><p class="text-sm text-slate-400">No allocations.</p><?php else: ?>
        <div class="flex flex-wrap gap-2"><?php foreach ($subjects as $s): ?><span class="badge badge-blue"><?= e((string) ($s['subject_name'] ?? '')) ?> — <?= e((string) ($s['class_name'] ?? '')) ?></span><?php endforeach; ?></div>
        <?php endif; ?>
    </div>
</div>
<?php $this->endSection(); ?>
