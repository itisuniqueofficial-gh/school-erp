<?php
/** @var \Core\View\View $this */
/** @var array<int,array<string,mixed>> $checks */
/** @var string $status */
/** @var array<string,int> $counts */
/** @var array<string,mixed>|null $currentYear */
$statusBadge = ['healthy' => 'badge-green', 'warning' => 'badge-amber', 'critical' => 'badge-red'][$status] ?? 'badge-slate';
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('main'); ?>

<div class="card mb-6 flex flex-wrap items-center justify-between gap-3 p-6">
    <div>
        <p class="text-xs uppercase tracking-wide text-slate-400">Current Academic Year</p>
        <p class="text-2xl font-bold text-slate-900"><?= e((string) ($currentYear['name'] ?? 'Not set')) ?>
            <?php if (!empty($currentYear['public_id'])): ?><span class="ml-2 align-middle text-sm font-normal text-slate-400"><?= e((string) $currentYear['public_id']) ?></span><?php endif; ?>
        </p>
    </div>
    <div class="flex items-center gap-2">
        <span class="text-sm text-slate-500">Setup health:</span>
        <span class="badge <?= $statusBadge ?>"><?= e(ucfirst($status)) ?></span>
        <a href="<?= e(url('/admin/academic/years')) ?>" class="btn btn-secondary btn-sm"><i class="fa-solid fa-calendar"></i> Manage years</a>
    </div>
</div>

<!-- Entity counts -->
<div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-5">
    <?php
    $cards = [
        'Academic Years' => ['academic_years', '/admin/academic/years', 'fa-calendar-days'],
        'Classes' => ['classes', '/admin/academic/classes', 'fa-school'],
        'Sections' => ['sections', '/admin/academic/sections', 'fa-layer-group'],
        'Subjects' => ['subjects', '/admin/academic/subjects', 'fa-book'],
        'Class Subjects' => ['class_subjects', '/admin/academic/class-subjects', 'fa-diagram-project'],
        'Teachers' => ['teachers', '/admin/teachers', 'fa-chalkboard-user'],
        'Teacher Assignments' => ['teacher_assignments', '/admin/teachers', 'fa-link'],
        'Students' => ['students', '/admin/students', 'fa-user-graduate'],
        'Enrollments (current)' => ['enrollments', '/admin/students', 'fa-id-card'],
    ];
    foreach ($cards as $label => [$key, $link, $icon]): ?>
    <a href="<?= e(url($link)) ?>" class="card p-4 transition hover:shadow-md">
        <p class="text-xs uppercase tracking-wide text-slate-400"><i class="fa-solid <?= $icon ?> mr-1"></i><?= e($label) ?></p>
        <p class="mt-1 text-2xl font-bold text-slate-900"><?= (int) ($counts[$key] ?? 0) ?></p>
    </a>
    <?php endforeach; ?>
</div>

<!-- Configuration health -->
<div class="card p-6">
    <h2 class="mb-4 font-semibold text-slate-800">Configuration health</h2>
    <ul class="divide-y divide-slate-100">
        <?php foreach ($checks as $c):
            $ok = (int) $c['count'] === 0;
            $sev = $c['severity'];
            $icon = $ok ? 'fa-circle-check text-green-500' : ($sev === 'critical' ? 'fa-circle-exclamation text-red-500' : 'fa-triangle-exclamation text-amber-500');
        ?>
        <li class="flex items-center justify-between py-3">
            <span class="flex items-center gap-3 text-sm">
                <i class="fa-solid <?= $icon ?>"></i>
                <span class="text-slate-700"><?= e((string) $c['label']) ?></span>
                <?php if (!$ok): ?><span class="badge <?= $sev === 'critical' ? 'badge-red' : 'badge-amber' ?>"><?= (int) $c['count'] ?></span><?php endif; ?>
            </span>
            <?php if (!$ok): ?>
                <a href="<?= e(url((string) $c['link'])) ?>" class="btn btn-secondary btn-sm">Fix now</a>
            <?php else: ?>
                <span class="text-xs text-green-600">OK</span>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
</div>

<!-- Quick actions -->
<div class="mt-6 card p-6">
    <h2 class="mb-4 font-semibold text-slate-800">Quick actions</h2>
    <div class="flex flex-wrap gap-2">
        <a href="<?= e(url('/admin/academic/years')) ?>" class="btn btn-secondary"><i class="fa-solid fa-plus"></i> Academic Year</a>
        <a href="<?= e(url('/admin/academic/classes')) ?>" class="btn btn-secondary"><i class="fa-solid fa-plus"></i> Class</a>
        <a href="<?= e(url('/admin/academic/sections')) ?>" class="btn btn-secondary"><i class="fa-solid fa-plus"></i> Section</a>
        <a href="<?= e(url('/admin/academic/subjects')) ?>" class="btn btn-secondary"><i class="fa-solid fa-plus"></i> Subject</a>
        <a href="<?= e(url('/admin/academic/class-subjects')) ?>" class="btn btn-secondary"><i class="fa-solid fa-diagram-project"></i> Allocate Subjects</a>
        <a href="<?= e(url('/admin/teachers')) ?>" class="btn btn-secondary"><i class="fa-solid fa-chalkboard-user"></i> Assign Teachers</a>
        <a href="<?= e(url('/admin/students/create')) ?>" class="btn btn-secondary"><i class="fa-solid fa-user-plus"></i> Add Student</a>
    </div>
</div>
<?php $this->endSection(); ?>
