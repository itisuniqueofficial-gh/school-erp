<?php
/** @var \Core\View\View $this */
/** @var array<string,mixed>|null $student */
/** @var array<string,mixed> $attendance */
/** @var float $feesDue */
/** @var array<int,array<string,mixed>> $upcomingExams */
/** @var array<int,array<string,mixed>> $recentMarks */
/** @var array{percent:int,missing:array<int,string>} $docCompletion */
/** @var array<int,array<string,mixed>> $years */
/** @var int $selectedYear */
$currency = (string) config('app.currency', '₹');
$name = trim((string) (($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''))) ?: 'Student';
$usesSections = (int) ($student['uses_sections'] ?? 1) === 1;
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
<div class="card mb-6 flex flex-col gap-4 bg-gradient-to-br from-brand-600 to-brand-800 p-6 text-white sm:flex-row sm:items-center">
    <?php if (!empty($student['photo'])): ?>
        <img src="<?= e(asset('storage/uploads/students/' . $student['photo'])) ?>" alt="<?= e($name) ?>" class="h-16 w-16 flex-none rounded-full object-cover ring-2 ring-white/40">
    <?php else: ?>
        <span class="flex h-16 w-16 flex-none items-center justify-center rounded-full bg-white/20 text-2xl font-bold"><?= e(strtoupper(substr($name, 0, 1))) ?></span>
    <?php endif; ?>
    <div class="min-w-0">
        <h2 class="text-xl font-bold">Welcome, <?= e($name) ?> 👋</h2>
        <p class="mt-1 text-sm text-brand-100">
            <?= e((string) ($student['class_name'] ?? '—')) ?><?php if ($usesSections && !empty($student['section_name'])): ?> · Section <?= e((string) $student['section_name']) ?><?php endif; ?>
            · Roll <?= e((string) ($student['roll_no'] ?? '—')) ?> · <?= e((string) ($student['year_name'] ?? '')) ?>
        </p>
        <?php if (!empty($student['uid'])): ?><p class="mt-1 text-xs text-brand-100">Student ID: <span class="font-mono"><?= e((string) $student['uid']) ?></span></p><?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <div class="card p-5" data-animate-card>
        <p class="text-sm text-slate-500">Attendance</p>
        <?php if ($attendance['hasData']): ?>
            <p class="mt-1 text-3xl font-bold text-slate-900"><?= number_format($attendance['attendancePercentage'], 2) ?>%</p>
            <p class="text-xs text-slate-400"><?= (int) ($attendance['present'] + $attendance['late'] + $attendance['half_day']) ?> / <?= (int) $attendance['totalClasses'] ?> classes attended</p>
        <?php else: ?>
            <p class="mt-1 text-lg font-semibold text-slate-400">No records</p>
            <p class="text-xs text-slate-400">for this academic year</p>
        <?php endif; ?>
    </div>
    <div class="card p-5" data-animate-card>
        <p class="text-sm text-slate-500">Outstanding fees</p>
        <p class="mt-1 text-3xl font-bold text-slate-900"><?= e($currency) ?><?= number_format($feesDue, 2) ?></p>
        <a href="<?= e(url('/student/fees')) ?>" class="text-xs font-medium text-brand-600">View invoices →</a>
    </div>
    <div class="card p-5" data-animate-card>
        <p class="text-sm text-slate-500">Documents</p>
        <p class="mt-1 text-3xl font-bold text-slate-900"><?= (int) $docCompletion['percent'] ?>%</p>
        <a href="<?= e(url('/student/documents')) ?>" class="text-xs font-medium text-brand-600">Complete profile →</a>
    </div>
    <div class="card p-5" data-animate-card>
        <p class="text-sm text-slate-500">Upcoming exams</p>
        <p class="mt-1 text-3xl font-bold text-slate-900"><?= count($upcomingExams) ?></p>
        <a href="<?= e(url('/student/results')) ?>" class="text-xs font-medium text-brand-600">View results →</a>
    </div>
</div>

<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
    <div class="card p-5">
        <h2 class="mb-3 font-semibold text-slate-800">Attendance summary</h2>
        <?php if ($attendance['hasData']): ?>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-lg bg-slate-50 p-3 text-center"><p class="text-lg font-bold text-slate-900"><?= (int) $attendance['present'] ?></p><p class="text-xs text-slate-500">Present</p></div>
            <div class="rounded-lg bg-slate-50 p-3 text-center"><p class="text-lg font-bold text-red-600"><?= (int) $attendance['absent'] ?></p><p class="text-xs text-slate-500">Absent</p></div>
            <div class="rounded-lg bg-slate-50 p-3 text-center"><p class="text-lg font-bold text-amber-600"><?= (int) $attendance['late'] ?></p><p class="text-xs text-slate-500">Late</p></div>
            <div class="rounded-lg bg-slate-50 p-3 text-center"><p class="text-lg font-bold text-blue-600"><?= (int) $attendance['excused'] ?></p><p class="text-xs text-slate-500">Excused</p></div>
        </div>
        <?php else: ?>
            <p class="text-sm text-slate-400">No attendance records are available for the selected academic year.</p>
        <?php endif; ?>
    </div>
    <div class="card p-5">
        <h2 class="mb-3 font-semibold text-slate-800">Recent results</h2>
        <?php if (empty($recentMarks)): ?><p class="text-sm text-slate-400">No results published yet.</p><?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($recentMarks as $m): ?>
            <li class="flex items-center justify-between py-2 text-sm">
                <span class="text-slate-700"><?= e((string) ($m['exam_name'] ?? 'Exam')) ?></span>
                <span class="font-medium text-slate-900"><?= e((string) ($m['percentage'] ?? '0')) ?>% <span class="badge <?= ($m['result'] ?? '') === 'pass' ? 'badge-green' : 'badge-red' ?>"><?= e(ucfirst((string) ($m['result'] ?? ''))) ?></span></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>

<div class="mt-6 card p-5">
    <h2 class="mb-3 font-semibold text-slate-800">Quick actions</h2>
    <div class="flex flex-wrap gap-2">
        <a href="<?= e(url('/student/profile')) ?>" class="btn btn-secondary"><i class="fa-solid fa-id-card"></i> My Profile</a>
        <a href="<?= e(url('/student/attendance')) ?>" class="btn btn-secondary"><i class="fa-solid fa-clipboard-check"></i> Attendance</a>
        <a href="<?= e(url('/student/documents')) ?>" class="btn btn-secondary"><i class="fa-solid fa-folder-open"></i> Documents</a>
        <a href="<?= e(url('/student/results')) ?>" class="btn btn-secondary"><i class="fa-solid fa-award"></i> Results</a>
        <a href="<?= e(url('/student/timetable')) ?>" class="btn btn-secondary"><i class="fa-solid fa-calendar-days"></i> Timetable</a>
    </div>
</div>
<?php $this->endSection(); ?>
