<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $assignments */
/** @var array<int,array<string,mixed>> $exams */
/** @var array<int,array<string,mixed>> $students */
/** @var array<int,array<string,mixed>> $existing */
/** @var array<string,mixed>|null $schedule */
/** @var string $status */
/** @var bool $locked */
/** @var bool $authorized */
/** @var array{exam_id:int,class_id:int,section_id:int,subject_id:int} $selected */
$full = (float) ($schedule['full_marks'] ?? 100);
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('main'); ?>

<form method="GET" class="card mb-4 p-4">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        <select name="exam_id" class="form-select" required>
            <option value="">Select exam</option>
            <?php foreach ($exams as $ex): ?><option value="<?= (int) $ex['id'] ?>" <?= $selected['exam_id'] === (int) $ex['id'] ? 'selected' : '' ?>><?= e((string) $ex['name']) ?></option><?php endforeach; ?>
        </select>
        <select name="assignment_pick" class="form-select" onchange="var v=this.value.split('-');document.getElementById('m_class').value=v[0]||'';document.getElementById('m_section').value=v[1]||'';document.getElementById('m_subject').value=v[2]||'';" required>
            <option value="">Select class · section · subject</option>
            <?php foreach ($assignments as $a):
                $key = $a['class_id'] . '-' . $a['section_id'] . '-' . $a['subject_id'];
                $cur = $selected['class_id'] . '-' . $selected['section_id'] . '-' . $selected['subject_id'];
            ?>
                <option value="<?= e((string) $key) ?>" <?= $cur === $key ? 'selected' : '' ?>><?= e((string) (($a['class_name'] ?? '') . ' - ' . ($a['section_name'] ?? '') . ' · ' . ($a['subject_name'] ?? ''))) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="hidden" name="class_id" id="m_class" value="<?= $selected['class_id'] ?>">
        <input type="hidden" name="section_id" id="m_section" value="<?= $selected['section_id'] ?>">
        <input type="hidden" name="subject_id" id="m_subject" value="<?= $selected['subject_id'] ?>">
        <button class="btn btn-primary justify-center"><i class="fa-solid fa-magnifying-glass"></i> Load students</button>
    </div>
    <?php if (empty($assignments)): ?><p class="mt-3 text-sm text-amber-600">You have no subject assignments yet.</p><?php endif; ?>
</form>

<?php if ($selected['exam_id'] && $selected['subject_id'] && !$authorized): ?>
    <div class="card p-8 text-center text-red-500"><i class="fa-solid fa-lock"></i> You are not assigned to this subject for that class/section.</div>
<?php elseif (!empty($students)): ?>
<div class="mb-3 flex flex-wrap items-center justify-between gap-2">
    <span class="badge <?= $locked ? 'badge-red' : 'badge-slate' ?>">Status: <?= e($status) ?></span>
    <span class="text-sm text-slate-500">Max marks: <?= e((string) $full) ?></span>
</div>
<form method="POST" action="<?= e(url('/teacher/marks')) ?>" class="card overflow-hidden" data-loading>
    <?= csrf_field() ?>
    <input type="hidden" name="exam_id" value="<?= $selected['exam_id'] ?>">
    <input type="hidden" name="class_id" value="<?= $selected['class_id'] ?>">
    <input type="hidden" name="section_id" value="<?= $selected['section_id'] ?>">
    <input type="hidden" name="subject_id" value="<?= $selected['subject_id'] ?>">
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Roll</th><th>Student ID</th><th>Name</th><th>Marks (/<?= e((string) $full) ?>)</th></tr></thead>
            <tbody>
            <?php foreach ($students as $st):
                $cur = $existing[(int) $st['id']]['marks_obtained'] ?? '';
            ?>
                <tr>
                    <td><?= e((string) ($st['roll_no'] ?? '—')) ?></td>
                    <td class="font-mono text-xs"><?= e((string) ($st['public_id'] ?? $st['admission_no'])) ?></td>
                    <td class="font-medium text-slate-800"><?= e((string) ($st['first_name'] . ' ' . $st['last_name'])) ?></td>
                    <td>
                        <input type="number" step="0.01" min="0" max="<?= e((string) $full) ?>" name="marks[<?= (int) $st['id'] ?>]" value="<?= e((string) $cur) ?>" class="form-input w-28" <?= $locked ? 'disabled' : '' ?>>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (!$locked): ?>
    <div class="flex items-center justify-end gap-2 border-t border-slate-200 px-4 py-3">
        <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk"></i> Save draft</button>
    </div>
    <?php else: ?>
    <div class="border-t border-slate-200 px-4 py-3 text-right text-sm text-slate-500">Marks are locked and cannot be edited.</div>
    <?php endif; ?>
</form>
<?php if (!$locked && in_array($status, ['draft', 'correction_required'], true)): ?>
<form method="POST" action="<?= e(url('/teacher/marks/submit')) ?>" class="mt-3 text-right" data-confirm="Submit these marks for approval? You will not be able to edit after submitting.">
    <?= csrf_field() ?>
    <input type="hidden" name="exam_id" value="<?= $selected['exam_id'] ?>">
    <input type="hidden" name="class_id" value="<?= $selected['class_id'] ?>">
    <input type="hidden" name="section_id" value="<?= $selected['section_id'] ?>">
    <input type="hidden" name="subject_id" value="<?= $selected['subject_id'] ?>">
    <button class="btn btn-secondary"><i class="fa-solid fa-paper-plane"></i> Submit for approval</button>
</form>
<?php endif; ?>
<?php elseif ($selected['exam_id']): ?>
    <div class="card p-8 text-center text-slate-400">No students found for that class/section.</div>
<?php else: ?>
    <div class="card p-8 text-center text-slate-400">Select an exam and one of your class/subject assignments.</div>
<?php endif; ?>
<?php $this->endSection(); ?>
