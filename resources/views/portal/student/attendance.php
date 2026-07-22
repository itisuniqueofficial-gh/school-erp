<?php
/** @var \Core\View\View $this */
/** @var array<string,mixed>|null $student */
/** @var array<string,mixed> $summary */
/** @var array<string,float> $monthly */
/** @var array<int,array<string,mixed>> $subjects */
/** @var array<int,array<string,mixed>> $records */
/** @var array<int,array<string,mixed>> $years */
/** @var int $selectedYear */
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('page_actions'); ?>
<?php if (count($years) > 1): ?>
<form method="GET" class="flex items-center gap-2">
    <label class="text-xs text-slate-500">Academic Year</label>
    <select name="year" class="form-select" onchange="this.form.submit()">
        <?php foreach ($years as $y): ?><option value="<?= (int) $y['id'] ?>" <?= $selectedYear === (int) $y['id'] ? 'selected' : '' ?>><?= e((string) $y['name']) ?></option><?php endforeach; ?>
    </select>
</form>
<?php endif; ?>
<?php $this->endSection(); ?>
<?php $this->section('main'); ?>

<?php if (!$summary['hasData']): ?>
    <div class="card p-8 text-center text-slate-400">No attendance records are available for the selected academic year.</div>
<?php else: ?>
<div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
    <div class="card p-4 text-center"><p class="text-2xl font-bold text-slate-900"><?= number_format($summary['attendancePercentage'], 2) ?>%</p><p class="text-xs text-slate-500">Attendance</p></div>
    <div class="card p-4 text-center"><p class="text-2xl font-bold text-slate-900"><?= (int) $summary['totalClasses'] ?></p><p class="text-xs text-slate-500">Total Classes</p></div>
    <div class="card p-4 text-center"><p class="text-2xl font-bold text-green-600"><?= (int) $summary['present'] ?></p><p class="text-xs text-slate-500">Present</p></div>
    <div class="card p-4 text-center"><p class="text-2xl font-bold text-red-600"><?= (int) $summary['absent'] ?></p><p class="text-xs text-slate-500">Absent</p></div>
    <div class="card p-4 text-center"><p class="text-2xl font-bold text-amber-600"><?= (int) $summary['late'] ?></p><p class="text-xs text-slate-500">Late</p></div>
    <div class="card p-4 text-center"><p class="text-2xl font-bold text-blue-600"><?= (int) $summary['excused'] ?></p><p class="text-xs text-slate-500">Excused</p></div>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    <div class="card p-5">
        <h2 class="mb-3 font-semibold text-slate-800">Monthly trend</h2>
        <?php if (empty($monthly)): ?><p class="text-sm text-slate-400">Not enough data.</p><?php else: ?>
        <div class="space-y-2">
            <?php foreach ($monthly as $ym => $pct): ?>
            <div>
                <div class="flex justify-between text-xs text-slate-500"><span><?= e(date('M Y', strtotime($ym . '-01'))) ?></span><span><?= number_format($pct, 2) ?>%</span></div>
                <div class="mt-1 h-2 w-full rounded-full bg-slate-100"><div class="h-2 rounded-full bg-brand-500" style="width: <?= max(0, min(100, $pct)) ?>%"></div></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="card p-5">
        <h2 class="mb-3 font-semibold text-slate-800">Subject-wise attendance</h2>
        <?php if (empty($subjects)): ?><p class="text-sm text-slate-400">No subject attendance recorded.</p><?php else: ?>
        <div class="space-y-2">
            <?php foreach ($subjects as $sa): ?>
            <div>
                <div class="flex justify-between text-xs text-slate-500"><span><?= e((string) $sa['subject_name']) ?></span><span><?= number_format($sa['percentage'], 2) ?>% (<?= (int) $sa['present'] ?>/<?= (int) $sa['total'] ?>)</span></div>
                <div class="mt-1 h-2 w-full rounded-full bg-slate-100"><div class="h-2 rounded-full bg-emerald-500" style="width: <?= max(0, min(100, $sa['percentage'])) ?>%"></div></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="mt-6 card overflow-hidden">
    <div class="border-b border-slate-200 px-4 py-3"><h2 class="font-semibold text-slate-800">Attendance history</h2></div>
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Date</th><th>Status</th><th>Remarks</th></tr></thead>
            <tbody>
            <?php foreach ($records as $r): $badge = in_array($r['status'], ['present','late','half_day'], true) ? 'badge-green' : ($r['status'] === 'absent' ? 'badge-red' : 'badge-slate'); ?>
                <tr>
                    <td><?= e((string) $r['date']) ?></td>
                    <td><span class="badge <?= $badge ?>"><?= e(ucfirst(str_replace('_', ' ', (string) $r['status']))) ?></span></td>
                    <td class="text-slate-500"><?= e((string) ($r['remarks'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php $this->endSection(); ?>
