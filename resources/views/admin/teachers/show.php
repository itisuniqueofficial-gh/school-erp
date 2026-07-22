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
        <?php if (!empty($teacher['uid'])): ?>
        <p class="mt-1 flex items-center justify-center gap-2 text-sm">
            <span class="font-mono text-slate-700"><?= e((string) $teacher['uid']) ?></span>
            <button type="button" data-copy="<?= e((string) $teacher['uid']) ?>" class="text-brand-600 hover:text-brand-800" title="Copy Teacher ID" aria-label="Copy Teacher ID"><i class="fa-solid fa-copy"></i></button>
        </p>
        <?php endif; ?>
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

    <!-- Teaching assignments (year → class → section → subject) -->
    <div class="card p-6 lg:col-span-3" id="assignments">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="font-semibold text-slate-800">Teaching Assignments</h2>
            <a href="<?= e(url('/admin/teaching-assignments?staff_id=' . $teacher['id'])) ?>" class="text-sm text-brand-600 hover:underline"><i class="fa-solid fa-arrow-up-right-from-square"></i> View all in module</a>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table table-cards">
                <thead><tr><th>Year</th><th>Class</th><th>Section</th><th>Subject</th><th>Role</th><th class="text-right">Action</th></tr></thead>
                <tbody>
                <?php if (empty($assignments)): ?>
                    <tr><td colspan="6" class="py-6 text-center text-slate-400">No teaching assignments yet.</td></tr>
                <?php else: foreach ($assignments as $a): ?>
                    <tr>
                        <td data-label="Year"><?= e((string) ($a['year_name'] ?? '—')) ?></td>
                        <td data-label="Class"><?= e((string) ($a['class_name'] ?? '—')) ?></td>
                        <td data-label="Section"><?= e((string) ($a['section_name'] ?? '—')) ?></td>
                        <td data-label="Subject" class="font-medium text-slate-800"><?= e((string) ($a['subject_name'] ?? '—')) ?></td>
                        <td data-label="Role"><span class="badge badge-slate"><?= e(str_replace('_', ' ', (string) $a['role'])) ?></span></td>
                        <td data-label="" class="text-right">
                            <?php if (auth()->can('teachers.update')): ?>
                            <details class="inline-block text-left align-middle">
                                <summary class="cursor-pointer text-brand-600 hover:underline"><i class="fa-solid fa-right-left"></i> Change</summary>
                                <form method="POST" action="<?= e(url('/admin/teachers/' . $teacher['id'] . '/assignments/' . $a['id'] . '/reassign')) ?>" class="mt-2 space-y-2 rounded-lg border border-slate-200 p-3 text-left" data-loading>
                                    <?= csrf_field() ?>
                                    <select name="new_staff_id" class="form-select" required>
                                        <option value="">Select new teacher</option>
                                        <?php foreach (($otherTeachers ?? []) as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e((string) ($t['first_name'] . ' ' . $t['last_name'])) ?></option><?php endforeach; ?>
                                    </select>
                                    <input type="text" name="reason" class="form-input" placeholder="Reason for change" required>
                                    <input type="date" name="effective_date" class="form-input" value="<?= e(date('Y-m-d')) ?>">
                                    <button class="btn btn-primary btn-sm w-full justify-center">Reassign</button>
                                </form>
                            </details>
                            <form method="POST" action="<?= e(url('/admin/teachers/' . $teacher['id'] . '/assignments/' . $a['id'])) ?>" class="inline">
                                <?= csrf_field() ?><?= method_field('DELETE') ?>
                                <button class="ml-2 text-red-600 hover:text-red-800" data-confirm="Remove this assignment?" aria-label="Remove assignment"><i class="fa-solid fa-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if (auth()->can('teachers.update')): ?>
        <form method="POST" action="<?= e(url('/admin/teachers/' . $teacher['id'] . '/assignments')) ?>" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6" data-loading>
            <?= csrf_field() ?>
            <select name="academic_year_id" class="form-select" required><option value="">Year</option><?php foreach($years as $y):?><option value="<?=(int)$y['id']?>"><?=e((string)$y['name'])?></option><?php endforeach;?></select>
            <select name="class_id" id="ta_class" data-cascade="class" data-target-section="#ta_section" data-target-subject="#ta_subject" class="form-select" required><option value="">Class</option><?php foreach($classes as $c):?><option value="<?=(int)$c['id']?>"><?=e((string)$c['name'])?></option><?php endforeach;?></select>
            <select name="section_id" id="ta_section" class="form-select" required><option value="">Section</option><?php foreach($sections as $sec):?><option value="<?=(int)$sec['id']?>"><?=e((string)($sec['class_name'].' - '.$sec['name']))?></option><?php endforeach;?></select>
            <select name="subject_id" id="ta_subject" class="form-select" required><option value="">Subject</option><?php foreach($allSubjects as $su):?><option value="<?=(int)$su['id']?>"><?=e((string)$su['name'])?></option><?php endforeach;?></select>
            <select name="role" class="form-select"><option value="subject_teacher">Subject Teacher</option><option value="assistant_teacher">Assistant</option></select>
            <button class="btn btn-primary justify-center"><i class="fa-solid fa-plus"></i> Assign</button>
        </form>
        <?php endif; ?>
    </div>

    <!-- Assignment ownership history -->
    <div class="card p-6 lg:col-span-3">
        <h2 class="mb-4 font-semibold text-slate-800">Assignment History</h2>
        <div class="overflow-x-auto">
            <table class="data-table table-cards">
                <thead><tr><th>Class</th><th>Section</th><th>Subject</th><th>Role</th><th>Start</th><th>End</th><th>Status</th><th>Reason</th></tr></thead>
                <tbody>
                <?php if (empty($assignmentHistory)): ?>
                    <tr><td colspan="8" class="py-6 text-center text-slate-400">No assignment history yet.</td></tr>
                <?php else: foreach ($assignmentHistory as $h): ?>
                    <tr>
                        <td data-label="Class"><?= e((string) ($h['class_name'] ?? '—')) ?></td>
                        <td data-label="Section"><?= e((string) ($h['section_name'] ?? '—')) ?></td>
                        <td data-label="Subject"><?= e((string) ($h['subject_name'] ?? '—')) ?></td>
                        <td data-label="Role"><?= e(str_replace('_', ' ', (string) $h['role'])) ?></td>
                        <td data-label="Start"><?= e((string) $h['start_date']) ?></td>
                        <td data-label="End"><?= e((string) ($h['end_date'] ?? '—')) ?></td>
                        <td data-label="Status"><span class="badge <?= ($h['status'] ?? '') === 'active' ? 'badge-green' : 'badge-slate' ?>"><?= e((string) $h['status']) ?></span></td>
                        <td data-label="Reason" class="text-slate-500"><?= e((string) ($h['reason'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php $this->endSection(); ?>
