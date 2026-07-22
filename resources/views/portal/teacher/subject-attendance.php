<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $assignments */
/** @var array<int,array<string,mixed>> $students */
/** @var array<int,array<string,mixed>> $existing */
/** @var string[] $statuses */
/** @var bool $editable */
/** @var string $lockReason */
/** @var bool $authorized */
/** @var array{class_id:int,section_id:int,subject_id:int,date:string} $selected */
$curKey = $selected['class_id'] . '-' . $selected['section_id'] . '-' . $selected['subject_id'];
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('main'); ?>

<form method="GET" class="card mb-4 p-4">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <select name="assignment_pick" class="form-select" onchange="var v=this.value.split('-');document.getElementById('sa_class').value=v[0]||'';document.getElementById('sa_section').value=v[1]||'';document.getElementById('sa_subject').value=v[2]||'';" required>
            <option value="">Select class · section · subject</option>
            <?php foreach ($assignments as $a):
                $key = $a['class_id'] . '-' . $a['section_id'] . '-' . $a['subject_id']; ?>
                <option value="<?= e((string) $key) ?>" <?= $curKey === $key ? 'selected' : '' ?>><?= e((string) (($a['class_name'] ?? '') . ' - ' . ($a['section_name'] ?? '') . ' · ' . ($a['subject_name'] ?? ''))) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="hidden" name="class_id" id="sa_class" value="<?= $selected['class_id'] ?>">
        <input type="hidden" name="section_id" id="sa_section" value="<?= $selected['section_id'] ?>">
        <input type="hidden" name="subject_id" id="sa_subject" value="<?= $selected['subject_id'] ?>">
        <input type="date" name="date" value="<?= e($selected['date']) ?>" class="form-input">
        <button class="btn btn-primary justify-center"><i class="fa-solid fa-magnifying-glass"></i> Load students</button>
    </div>
    <?php if (empty($assignments)): ?><p class="mt-3 text-sm text-amber-600">You have no subject assignments yet.</p><?php endif; ?>
</form>

<?php if ($selected['subject_id'] && !$authorized): ?>
    <div class="card p-8 text-center text-red-500"><i class="fa-solid fa-lock"></i> You are not assigned to this subject for that class/section.</div>
<?php elseif (!empty($students)): ?>
    <?php if (!$editable): ?>
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"><i class="fa-solid fa-lock"></i> <?= e($lockReason) ?></div>
    <?php endif; ?>
<form method="POST" action="<?= e(url('/teacher/subject-attendance')) ?>" class="card overflow-hidden" data-loading>
    <?= csrf_field() ?>
    <input type="hidden" name="class_id" value="<?= $selected['class_id'] ?>">
    <input type="hidden" name="section_id" value="<?= $selected['section_id'] ?>">
    <input type="hidden" name="subject_id" value="<?= $selected['subject_id'] ?>">
    <input type="hidden" name="date" value="<?= e($selected['date']) ?>">
    <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
        <p class="text-sm text-slate-600"><?= count($students) ?> students · <?= e($selected['date']) ?></p>
        <div class="flex gap-2 text-xs">
            <button type="button" class="badge badge-green" data-mark-all="present">All present</button>
            <button type="button" class="badge badge-red" data-mark-all="absent">All absent</button>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Roll</th><th>Student ID</th><th>Name</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($students as $st): $cur = $existing[(int) $st['id']]['status'] ?? 'present'; ?>
                <tr>
                    <td><?= e((string) ($st['roll_no'] ?? '—')) ?></td>
                    <td class="font-mono text-xs"><?= e((string) ($st['public_id'] ?? $st['admission_no'])) ?></td>
                    <td class="font-medium text-slate-800"><?= e((string) ($st['first_name'] . ' ' . $st['last_name'])) ?></td>
                    <td>
                        <select name="status[<?= (int) $st['id'] ?>]" class="form-select att-status w-40" <?= $editable ? '' : 'disabled' ?>>
                            <?php foreach ($statuses as $s): ?><option value="<?= $s ?>" <?= $cur === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($editable): ?>
    <div class="border-t border-slate-200 px-4 py-3 text-right"><button class="btn btn-primary"><i class="fa-solid fa-check"></i> Save subject attendance</button></div>
    <?php endif; ?>
</form>
<?php elseif ($selected['subject_id']): ?>
    <div class="card p-8 text-center text-slate-400">No active students in this class/section.</div>
<?php else: ?>
    <div class="card p-8 text-center text-slate-400">Select one of your class/section/subject assignments.</div>
<?php endif; ?>
<?php $this->endSection(); ?>

<?php $this->section('page_scripts'); ?>
<script>
$(function(){ $('[data-mark-all]').on('click', function(){ $('.att-status:not(:disabled)').val($(this).data('mark-all')); }); });
</script>
<?php $this->endSection(); ?>
