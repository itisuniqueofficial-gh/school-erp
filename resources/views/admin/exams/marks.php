<?php
/** @var \Core\View\View $this */
/** @var array<string,mixed> $exam */
/** @var array<int,array<string,mixed>> $classes */
/** @var array<int,array<string,mixed>> $subjects */
/** @var array<int,array<string,mixed>> $students */
/** @var array<int,array<string,mixed>> $existing */
/** @var array{class_id:int,subject_id:int} $selected */
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('breadcrumb'); ?><a href="<?= e(url('/admin/exams')) ?>" class="hover:text-brand-600">Exams</a> / Marks<?php $this->endSection(); ?>
<?php $this->section('main'); ?>
<form method="GET" class="card mb-4 p-4">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <select name="class_id" class="form-select" required><option value="">Class</option><?php foreach($classes as $c):?><option value="<?=(int)$c['id']?>" <?=$selected['class_id']===(int)$c['id']?'selected':''?>><?=e((string)$c['name'])?></option><?php endforeach;?></select>
        <select name="subject_id" class="form-select" required><option value="">Subject</option><?php foreach($subjects as $s):?><option value="<?=(int)$s['id']?>" <?=$selected['subject_id']===(int)$s['id']?'selected':''?>><?=e((string)$s['name'])?></option><?php endforeach;?></select>
        <button class="btn btn-primary justify-center"><i class="fa-solid fa-magnifying-glass"></i> Load</button>
    </div>
</form>

<?php if (!empty($students)): ?>
<form method="POST" action="<?= e(url('/admin/exams/' . $exam['id'] . '/marks')) ?>" class="card overflow-hidden" data-loading>
    <?= csrf_field() ?>
    <input type="hidden" name="class_id" value="<?= $selected['class_id'] ?>">
    <input type="hidden" name="subject_id" value="<?= $selected['subject_id'] ?>">
    <table class="data-table">
        <thead><tr><th>Roll</th><th>Name</th><th>Marks (out of 100)</th></tr></thead>
        <tbody>
        <?php foreach ($students as $st): $m = $existing[(int) $st['id']]['marks_obtained'] ?? ''; ?>
            <tr>
                <td><?= e((string) ($st['roll_no'] ?? '—')) ?></td>
                <td class="font-medium text-slate-800"><?= e((string) ($st['first_name'] . ' ' . $st['last_name'])) ?></td>
                <td><input type="number" step="0.01" min="0" max="100" name="marks[<?= (int) $st['id'] ?>]" value="<?= e((string) $m) ?>" class="form-input w-32"></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="border-t border-slate-200 px-4 py-3 text-right"><button class="btn btn-primary"><i class="fa-solid fa-check"></i> Save marks</button></div>
</form>
<?php elseif ($selected['class_id']): ?>
    <div class="card p-8 text-center text-slate-400">No students in this class.</div>
<?php else: ?>
    <div class="card p-8 text-center text-slate-400">Select class and subject to enter marks.</div>
<?php endif; ?>
<?php $this->endSection(); ?>
