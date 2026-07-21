<?php
/** @var \Core\View\View $this */
/** @var array<string,mixed>|null $staff */
/** @var array<int,array<string,mixed>> $classes */
/** @var array<int,array<string,mixed>> $periods */
$name = trim((string) (($staff['first_name'] ?? '') . ' ' . ($staff['last_name'] ?? ''))) ?: 'Teacher';
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('main'); ?>
<div class="card mb-6 bg-gradient-to-br from-emerald-600 to-emerald-800 p-6 text-white">
    <h2 class="text-xl font-bold">Welcome, <?= e($name) ?> 👋</h2>
    <p class="mt-1 text-emerald-100"><?= e((string) ($staff['designation'] ?? 'Teacher')) ?></p>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    <div class="card p-5">
        <h2 class="mb-4 font-semibold text-slate-800">Today's schedule</h2>
        <?php if (empty($periods)): ?>
            <p class="text-sm text-slate-400">No periods scheduled for today.</p>
        <?php else: ?>
        <ul class="space-y-2">
            <?php foreach ($periods as $p): ?>
            <li class="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-sm">
                <span class="font-medium text-slate-700"><?= e((string) ($p['subject_name'] ?? '—')) ?> · <?= e((string) ($p['class_name'] ?? '')) ?> <?= e((string) ($p['section_name'] ?? '')) ?></span>
                <span class="text-slate-500"><?= e((string) ($p['start_time'] ?? '')) ?>–<?= e((string) ($p['end_time'] ?? '')) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <div class="card p-5">
        <h2 class="mb-4 font-semibold text-slate-800">My classes &amp; subjects</h2>
        <?php if (empty($classes)): ?>
            <p class="text-sm text-slate-400">No class allocations yet.</p>
        <?php else: ?>
        <div class="flex flex-wrap gap-2">
            <?php foreach ($classes as $c): ?>
            <span class="badge badge-blue"><?= e((string) ($c['subject_name'] ?? '')) ?> — <?= e((string) ($c['class_name'] ?? '')) ?> <?= e((string) ($c['section_name'] ?? '')) ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <a href="<?= e(url('/teacher/attendance')) ?>" class="btn btn-primary mt-4"><i class="fa-solid fa-clipboard-check"></i> Mark attendance</a>
    </div>
</div>
<?php $this->endSection(); ?>
