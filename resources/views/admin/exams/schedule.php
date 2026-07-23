<?php
/** @var \Core\View\View $this */
/** @var array<string,mixed> $exam */
/** @var array<int,array<string,mixed>> $classes */
/** @var int $classId */
/** @var array<int,array<string,mixed>> $subjects */
/** @var array<int,array<string,mixed>> $current */
/** @var array<int,array<string,mixed>> $conflicts */
/** @var array<int,array<string,mixed>> $otherExams */
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('breadcrumb'); ?><a href="<?= e(url('/admin/exams')) ?>" class="hover:text-brand-600">Examinations</a> / <?= e((string) $exam['name']) ?> / Schedule<?php $this->endSection(); ?>
<?php $this->section('main'); ?>

<div class="card mb-5 p-5">
    <form method="GET" action="<?= e(url('/admin/exams/' . $exam['id'] . '/schedule')) ?>" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-semibold text-slate-500">Class</label>
            <select name="class_id" class="form-input" onchange="this.form.submit()">
                <option value="">Select a class</option>
                <?php foreach ($classes as $cl): ?>
                    <option value="<?= (int) $cl['id'] ?>" <?= $classId === (int) $cl['id'] ? 'selected' : '' ?>><?= e((string) $cl['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn btn-secondary"><i class="fa-solid fa-list-check"></i> Load subjects</button>
        <?php if ($classId): ?>
            <a href="<?= e(url('/admin/hall-tickets/generate?exam_id=' . $exam['id'] . '&class_id=' . $classId . '&type=hall_ticket')) ?>" class="btn btn-primary ml-auto"><i class="fa-solid fa-ticket"></i> Generate Hall Tickets</a>
        <?php endif; ?>
    </form>
</div>

<?php if ($classId): ?>
    <?php if (!empty($conflicts)): ?>
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <i class="fa-solid fa-triangle-exclamation"></i> <b>Schedule conflict:</b>
            <?php foreach ($conflicts as $c): ?>
                <div><?= e((string) $c['slot']) ?> — <?= e(implode(', ', $c['subjects'])) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($subjects)): ?>
        <div class="card p-6 text-center text-slate-400">No subjects assigned to this class. Assign subjects in Academic → Class Subjects first.</div>
    <?php else: ?>
    <div class="card overflow-hidden">
        <form method="POST" action="<?= e(url('/admin/exams/' . $exam['id'] . '/schedule')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="class_id" value="<?= $classId ?>">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                <h2 class="font-semibold text-slate-800">Subject schedule</h2>
                <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save schedule</button>
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Include</th><th>Subject</th><th>Date</th><th>Start</th><th>End</th><th>Max</th><th>Pass</th></tr></thead>
                    <tbody>
                    <?php foreach ($subjects as $s): $sid = (int) $s['subject_id']; $cur = $current[$sid] ?? null; ?>
                        <tr>
                            <td><input type="checkbox" name="subjects[<?= $sid ?>][include]" value="1" <?= $cur ? 'checked' : '' ?> class="rounded border-slate-300"></td>
                            <td class="font-medium text-slate-800"><?= e((string) $s['name']) ?> <span class="text-xs text-slate-400"><?= e((string) $s['code']) ?></span></td>
                            <td><input type="date" name="subjects[<?= $sid ?>][date]" value="<?= e((string) ($cur['exam_date'] ?? '')) ?>" class="form-input"></td>
                            <td><input type="time" name="subjects[<?= $sid ?>][start]" value="<?= e(substr((string) ($cur['start_time'] ?? ''), 0, 5)) ?>" class="form-input"></td>
                            <td><input type="time" name="subjects[<?= $sid ?>][end]" value="<?= e(substr((string) ($cur['end_time'] ?? ''), 0, 5)) ?>" class="form-input"></td>
                            <td><input type="number" name="subjects[<?= $sid ?>][full_marks]" value="<?= e((string) ($cur['full_marks'] ?? $s['full_marks'] ?? 100)) ?>" class="form-input w-20" min="0"></td>
                            <td><input type="number" name="subjects[<?= $sid ?>][pass_marks]" value="<?= e((string) ($cur['pass_marks'] ?? $s['pass_marks'] ?? 33)) ?>" class="form-input w-20" min="0"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-400">Tick a subject to include it in this exam. Un-ticking removes its schedule row (marks for that subject are affected). Re-saving updates rows — no duplicates are created.</p>
        </form>
    </div>

    <div class="card mt-4 p-5">
        <h3 class="mb-2 font-semibold text-slate-800">Copy schedule from another exam</h3>
        <form method="POST" action="<?= e(url('/admin/exams/' . $exam['id'] . '/schedule/copy')) ?>" class="flex flex-wrap items-end gap-3">
            <?= csrf_field() ?>
            <input type="hidden" name="class_id" value="<?= $classId ?>">
            <div>
                <label class="block text-xs font-semibold text-slate-500">Source exam</label>
                <select name="from_exam_id" class="form-input" required>
                    <option value="">Select</option>
                    <?php foreach ($otherExams as $oe): ?><option value="<?= (int) $oe['id'] ?>"><?= e((string) $oe['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-secondary" data-confirm="Copy this class's subject schedule from the selected exam?"><i class="fa-solid fa-copy"></i> Copy</button>
        </form>
    </div>
    <?php endif; ?>
<?php endif; ?>
<?php $this->endSection(); ?>
