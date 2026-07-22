<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $assignments */
/** @var array<string,mixed> $filters */
/** @var array<int,array<string,mixed>> $years */
/** @var array<int,array<string,mixed>> $classes */
/** @var array<int,array<string,mixed>> $sections */
/** @var array<int,array<string,mixed>> $subjects */
/** @var array<int,array<string,mixed>> $teachers */
/** @var int $currentYearId */
/** @var array<string,int> $stats */
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('breadcrumb'); ?>Academics / Teaching Assignments<?php $this->endSection(); ?>
<?php $this->section('main'); ?>

<div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
    <div class="card p-5"><p class="text-xs uppercase tracking-wide text-slate-400">Total Assignments</p><p class="mt-1 text-2xl font-bold text-slate-900"><?= (int) $stats['total'] ?></p></div>
    <div class="card p-5"><p class="text-xs uppercase tracking-wide text-slate-400">Current Year</p><p class="mt-1 text-2xl font-bold text-slate-900"><?= (int) $stats['current'] ?></p></div>
    <div class="card p-5"><p class="text-xs uppercase tracking-wide text-slate-400">Subjects w/o Teacher</p><p class="mt-1 text-2xl font-bold <?= $stats['subjects_no_teacher'] > 0 ? 'text-amber-600' : 'text-slate-900' ?>"><?= (int) $stats['subjects_no_teacher'] ?></p></div>
    <div class="card p-5"><p class="text-xs uppercase tracking-wide text-slate-400">Teacher Conflicts</p><p class="mt-1 text-2xl font-bold <?= $stats['conflicts'] > 0 ? 'text-red-600' : 'text-slate-900' ?>"><?= (int) $stats['conflicts'] ?></p></div>
</div>

<!-- Filters (dependent) -->
<form method="GET" class="card mb-4 p-4">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <select name="academic_year_id" class="form-select"><option value="">All years</option><?php foreach ($years as $y): ?><option value="<?= (int) $y['id'] ?>" <?= $filters['academic_year_id'] === (int) $y['id'] ? 'selected' : '' ?>><?= e((string) $y['name']) ?></option><?php endforeach; ?></select>
        <select name="class_id" class="form-select" data-cascade="class" data-target-section="#f_section"><option value="">All classes</option><?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $filters['class_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e((string) $c['name']) ?></option><?php endforeach; ?></select>
        <select name="section_id" id="f_section" class="form-select" data-placeholder="All sections"><option value="">All sections</option><?php foreach ($sections as $s): if ($filters['class_id'] && (int) $s['class_id'] !== $filters['class_id']) continue; ?><option value="<?= (int) $s['id'] ?>" <?= $filters['section_id'] === (int) $s['id'] ? 'selected' : '' ?>><?= e((string) $s['name']) ?></option><?php endforeach; ?></select>
        <select name="subject_id" class="form-select"><option value="">All subjects</option><?php foreach ($subjects as $s): ?><option value="<?= (int) $s['id'] ?>" <?= $filters['subject_id'] === (int) $s['id'] ? 'selected' : '' ?>><?= e((string) $s['name']) ?></option><?php endforeach; ?></select>
        <select name="staff_id" class="form-select"><option value="">All teachers</option><?php foreach ($teachers as $t): ?><option value="<?= (int) $t['id'] ?>" <?= $filters['staff_id'] === (int) $t['id'] ? 'selected' : '' ?>><?= e((string) ($t['first_name'] . ' ' . $t['last_name'])) ?></option><?php endforeach; ?></select>
        <button class="btn btn-primary justify-center"><i class="fa-solid fa-filter"></i> Filter</button>
    </div>
</form>

<!-- Create (cascading) -->
<?php if (auth()->can('teachers.update')): ?>
<details class="card mb-4 p-4">
    <summary class="cursor-pointer font-semibold text-slate-800"><i class="fa-solid fa-plus"></i> New Assignment</summary>
    <form method="POST" action="<?= e(url('/admin/teaching-assignments')) ?>" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3 lg:grid-cols-6" data-loading>
        <?= csrf_field() ?>
        <select name="academic_year_id" class="form-select" required><option value="">Year *</option><?php foreach ($years as $y): ?><option value="<?= (int) $y['id'] ?>" <?= (int) $y['id'] === $currentYearId ? 'selected' : '' ?>><?= e((string) $y['name']) ?></option><?php endforeach; ?></select>
        <select name="class_id" id="ta_new_class" class="form-select" data-cascade="class" data-target-section="#ta_new_section" data-target-subject="#ta_new_subject" required><option value="">Class *</option><?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e((string) $c['name']) ?></option><?php endforeach; ?></select>
        <select name="section_id" id="ta_new_section" class="form-select" data-placeholder="Section *" required><option value="">Select class first</option></select>
        <select name="subject_id" id="ta_new_subject" class="form-select" data-placeholder="Subject *" required><option value="">Select class first</option></select>
        <select name="staff_id" class="form-select" required><option value="">Teacher *</option><?php foreach ($teachers as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e((string) ($t['first_name'] . ' ' . $t['last_name'])) ?></option><?php endforeach; ?></select>
        <div class="flex gap-2">
            <select name="role" class="form-select"><option value="subject_teacher">Subject Teacher</option><option value="assistant_teacher">Assistant</option></select>
            <button class="btn btn-primary"><i class="fa-solid fa-check"></i></button>
        </div>
    </form>
</details>
<?php endif; ?>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="data-table table-cards">
            <thead><tr><th>ID</th><th>Year</th><th>Class</th><th>Section</th><th>Subject</th><th>Teacher</th><th>Role</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
            <?php if (empty($assignments)): ?>
                <tr><td colspan="8" class="py-8 text-center text-slate-400">No assignments match these filters.</td></tr>
            <?php else: foreach ($assignments as $a): ?>
                <tr>
                    <td data-label="ID" class="font-mono text-xs">TA-<?= sprintf('%04d', (int) $a['id']) ?></td>
                    <td data-label="Year"><?= e((string) ($a['year_name'] ?? '—')) ?></td>
                    <td data-label="Class"><?= e((string) ($a['class_name'] ?? '—')) ?></td>
                    <td data-label="Section"><?= e((string) ($a['section_name'] ?? '—')) ?></td>
                    <td data-label="Subject" class="font-medium text-slate-800"><?= e((string) ($a['subject_name'] ?? '—')) ?> <span class="text-xs text-slate-400"><?= e((string) ($a['subject_code'] ?? '')) ?></span></td>
                    <td data-label="Teacher"><?= e((string) trim(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''))) ?> <span class="text-xs text-slate-400"><?= e((string) ($a['teacher_pid'] ?? '')) ?></span></td>
                    <td data-label="Role"><span class="badge badge-slate"><?= e(str_replace('_', ' ', (string) $a['role'])) ?></span></td>
                    <td data-label="" class="text-right">
                        <?php if (auth()->can('teachers.update')): ?>
                        <details class="inline-block text-left align-middle">
                            <summary class="cursor-pointer text-brand-600 hover:underline"><i class="fa-solid fa-right-left"></i> Change</summary>
                            <form method="POST" action="<?= e(url('/admin/teaching-assignments/' . $a['id'] . '/reassign')) ?>" class="mt-2 space-y-2 rounded-lg border border-slate-200 p-3" data-loading>
                                <?= csrf_field() ?>
                                <select name="new_staff_id" class="form-select" required><option value="">New teacher</option><?php foreach ($teachers as $t): if ((int) $t['id'] === (int) $a['staff_id']) continue; ?><option value="<?= (int) $t['id'] ?>"><?= e((string) ($t['first_name'] . ' ' . $t['last_name'])) ?></option><?php endforeach; ?></select>
                                <input type="text" name="reason" class="form-input" placeholder="Reason" required>
                                <input type="date" name="effective_date" class="form-input" value="<?= e(date('Y-m-d')) ?>">
                                <button class="btn btn-primary btn-sm w-full justify-center">Reassign</button>
                            </form>
                        </details>
                        <form method="POST" action="<?= e(url('/admin/teaching-assignments/' . $a['id'])) ?>" class="inline"><?= csrf_field() ?><?= method_field('DELETE') ?><button class="ml-2 text-red-600 hover:text-red-800" data-confirm="End/remove this assignment?"><i class="fa-solid fa-trash"></i></button></form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
