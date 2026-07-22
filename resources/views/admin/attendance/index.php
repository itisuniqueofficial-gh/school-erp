<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $classes */
/** @var array<int,array<string,mixed>> $sections */
/** @var array<int,array<string,mixed>> $students */
/** @var array<int,array<string,mixed>> $existing */
/** @var string[] $statuses */
/** @var array{class_id:int,section_id:int,date:string} $selected */
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('page_actions'); ?>
<a href="<?= e(url('/admin/attendance/report')) ?>" class="btn btn-secondary"><i class="fa-solid fa-chart-column"></i> Monthly report</a>
<?php $this->endSection(); ?>
<?php $this->section('main'); ?>
<form method="GET" class="card mb-4 p-4">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
        <select name="class_id" class="form-select" data-cascade="class" data-target-section="#att_section" required>
            <option value="">Select class</option>
            <?php foreach ($classes as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $selected['class_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e((string) $c['name']) ?></option><?php endforeach; ?>
        </select>
        <select name="section_id" id="att_section" class="form-select" data-placeholder="Select section" required>
            <option value="">Select section</option>
            <?php foreach ($sections as $s): if ($selected['class_id'] && (int) $s['class_id'] !== $selected['class_id']) continue; ?><option value="<?= (int) $s['id'] ?>" <?= $selected['section_id'] === (int) $s['id'] ? 'selected' : '' ?>><?= e((string) $s['name']) ?></option><?php endforeach; ?>
        </select>
        <input type="date" name="date" value="<?= e($selected['date']) ?>" class="form-input">
        <button class="btn btn-primary justify-center"><i class="fa-solid fa-magnifying-glass"></i> Load students</button>
    </div>
</form>

<?php if (!empty($students)): ?>
<form method="POST" action="<?= e(url('/admin/attendance')) ?>" class="card overflow-hidden" data-loading>
    <?= csrf_field() ?>
    <input type="hidden" name="class_id" value="<?= $selected['class_id'] ?>">
    <input type="hidden" name="section_id" value="<?= $selected['section_id'] ?>">
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
            <thead><tr><th>Roll</th><th>Admission</th><th>Name</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($students as $st):
                $cur = $existing[(int) $st['id']]['status'] ?? 'present';
            ?>
                <tr>
                    <td><?= e((string) ($st['roll_no'] ?? '—')) ?></td>
                    <td class="font-mono text-xs"><?= e((string) $st['admission_no']) ?></td>
                    <td class="font-medium text-slate-800"><?= e((string) ($st['first_name'] . ' ' . $st['last_name'])) ?></td>
                    <td>
                        <select name="status[<?= (int) $st['id'] ?>]" class="form-select att-status w-40">
                            <?php foreach ($statuses as $s): ?><option value="<?= $s ?>" <?= $cur === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option><?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="border-t border-slate-200 px-4 py-3 text-right">
        <button class="btn btn-primary"><i class="fa-solid fa-check"></i> Save attendance</button>
    </div>
</form>
<?php elseif ($selected['class_id']): ?>
    <div class="card p-8 text-center text-slate-400">No active students in this class/section.</div>
<?php else: ?>
    <div class="card p-8 text-center text-slate-400">Select a class and section to mark attendance.</div>
<?php endif; ?>
<?php $this->endSection(); ?>

<?php $this->section('page_scripts'); ?>
<script>
$(function(){ $('[data-mark-all]').on('click', function(){ $('.att-status').val($(this).data('mark-all')); }); });
</script>
<?php $this->endSection(); ?>
