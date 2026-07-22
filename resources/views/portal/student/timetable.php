<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $periods */
$days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
$byDay = [];
foreach (($periods ?? []) as $p) { $byDay[(int) $p['day_of_week']][] = $p; }
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('main'); ?>
<?php if (empty($periods)): ?>
<div class="card p-8 text-center">
    <i class="fa-solid fa-calendar-days mb-3 text-4xl text-slate-300"></i>
    <p class="text-slate-500">Your class timetable will appear here once published by the school.</p>
</div>
<?php else: ?>
<div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
    <?php foreach ($days as $num => $label): if (empty($byDay[$num])) continue; ?>
    <div class="card p-5">
        <h2 class="mb-3 font-semibold text-slate-800"><?= e($label) ?></h2>
        <ul class="space-y-2">
            <?php foreach ($byDay[$num] as $p): ?>
            <li class="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-sm">
                <span class="font-medium text-slate-700"><?= e((string) ($p['subject_name'] ?? '—')) ?><?php if (!empty($p['room_name'])): ?> <span class="text-slate-400">· <?= e((string) $p['room_name']) ?></span><?php endif; ?></span>
                <span class="text-slate-500"><?= e((string) ($p['start_time'] ?? '')) ?>–<?= e((string) ($p['end_time'] ?? '')) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php $this->endSection(); ?>
