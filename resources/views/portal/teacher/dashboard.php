<?php
/** @var \Core\View\View $this */
/** @var array<string,mixed>|null $staff */
/** @var array<int,array<string,mixed>> $assignments */
/** @var array<int,array<string,mixed>> $classTeacherOf */
/** @var int $subjectCount */
/** @var int $sectionCount */
/** @var int $studentCount */
/** @var array<int,array<string,mixed>> $pendingAttendance */
/** @var array<int,array<string,mixed>> $pendingMarks */
/** @var array<int,array<string,mixed>> $upcomingExams */
$name = trim((string) (($staff['first_name'] ?? '') . ' ' . ($staff['last_name'] ?? ''))) ?: 'Teacher';
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('main'); ?>
<div class="card mb-6 bg-gradient-to-br from-emerald-600 to-emerald-800 p-6 text-white">
    <h2 class="text-xl font-bold">Welcome, <?= e($name) ?> 👋</h2>
    <p class="mt-1 text-emerald-100"><?= e((string) ($staff['designation'] ?? 'Teacher')) ?> · <?= e((string) ($staff['public_id'] ?? $staff['employee_code'] ?? '')) ?></p>
</div>

<div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
    <div class="card p-5"><p class="text-xs uppercase tracking-wide text-slate-400">My Sections</p><p class="mt-1 text-2xl font-bold text-slate-900"><?= (int) $sectionCount ?></p></div>
    <div class="card p-5"><p class="text-xs uppercase tracking-wide text-slate-400">My Subjects</p><p class="mt-1 text-2xl font-bold text-slate-900"><?= (int) $subjectCount ?></p></div>
    <div class="card p-5"><p class="text-xs uppercase tracking-wide text-slate-400">My Students</p><p class="mt-1 text-2xl font-bold text-slate-900"><?= (int) $studentCount ?></p></div>
    <div class="card p-5"><p class="text-xs uppercase tracking-wide text-slate-400">Class Teacher Of</p><p class="mt-1 text-2xl font-bold text-slate-900"><?= count($classTeacherOf) ?></p></div>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    <div class="card p-5">
        <h2 class="mb-4 font-semibold text-slate-800">My teaching assignments</h2>
        <?php if (empty($assignments)): ?>
            <p class="text-sm text-slate-400">No assignments yet. Ask an administrator to assign you to a class/section/subject.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Class</th><th>Section</th><th>Subject</th><th>Role</th></tr></thead>
                <tbody>
                <?php foreach ($assignments as $a): ?>
                    <tr>
                        <td><?= e((string) ($a['class_name'] ?? '—')) ?></td>
                        <td><?= e((string) ($a['section_name'] ?? '—')) ?></td>
                        <td class="font-medium text-slate-800"><?= e((string) ($a['subject_name'] ?? '—')) ?></td>
                        <td><span class="badge badge-slate"><?= e(str_replace('_', ' ', (string) $a['role'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <div class="mt-4 flex gap-2">
            <a href="<?= e(url('/teacher/attendance')) ?>" class="btn btn-primary"><i class="fa-solid fa-clipboard-check"></i> Attendance</a>
            <a href="<?= e(url('/teacher/marks')) ?>" class="btn btn-secondary"><i class="fa-solid fa-pen-to-square"></i> Marks</a>
        </div>
    </div>

    <div class="space-y-6">
        <div class="card p-5">
            <h2 class="mb-3 font-semibold text-slate-800">Attendance pending today</h2>
            <?php if (empty($pendingAttendance)): ?>
                <p class="text-sm text-emerald-600"><i class="fa-solid fa-circle-check"></i> All class-teacher attendance is complete.</p>
            <?php else: foreach ($pendingAttendance as $p): ?>
                <a href="<?= e(url('/teacher/attendance?class_id=' . $p['class_id'] . '&section_id=' . $p['section_id'] . '&date=' . date('Y-m-d'))) ?>" class="mb-2 flex items-center justify-between rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm">
                    <span class="font-medium text-amber-800"><?= e((string) ($p['class_name'] . ' - ' . $p['section_name'])) ?></span>
                    <span class="text-amber-700"><?= (int) $p['marked'] ?>/<?= (int) $p['expected'] ?> marked</span>
                </a>
            <?php endforeach; endif; ?>
        </div>

        <div class="card p-5">
            <h2 class="mb-3 font-semibold text-slate-800">Pending marks entry</h2>
            <?php if (empty($pendingMarks)): ?>
                <p class="text-sm text-emerald-600"><i class="fa-solid fa-circle-check"></i> No pending marks.</p>
            <?php else: foreach ($pendingMarks as $m): ?>
                <div class="mb-2 flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50 px-3 py-2 text-sm">
                    <span class="font-medium text-slate-700"><?= e((string) (($m['exam_name'] ?? '') . ' · ' . ($m['class_name'] ?? '') . ' · ' . ($m['subject_name'] ?? ''))) ?></span>
                    <span class="badge badge-slate"><?= e((string) $m['status']) ?></span>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <div class="card p-5">
            <h2 class="mb-3 font-semibold text-slate-800">Upcoming exams</h2>
            <?php if (empty($upcomingExams)): ?>
                <p class="text-sm text-slate-400">No upcoming exams.</p>
            <?php else: foreach ($upcomingExams as $ex): ?>
                <div class="mb-2 flex items-center justify-between text-sm">
                    <span class="text-slate-700"><?= e((string) $ex['name']) ?></span>
                    <span class="text-slate-400"><?= e((string) ($ex['start_date'] ?? 'TBD')) ?></span>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>
<?php $this->endSection(); ?>
