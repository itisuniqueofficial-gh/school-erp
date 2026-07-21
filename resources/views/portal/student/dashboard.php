<?php
/** @var \Core\View\View $this */
/** @var array<string,mixed>|null $student */
/** @var array{present:int,total:int,rate:float} $attendance */
/** @var float $feesDue */
$currency = (string) config('app.currency', '₹');
$name = trim((string) (($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''))) ?: 'Student';
?>
<?php $this->extend('layouts.app'); ?>

<?php $this->section('main'); ?>
<div class="card mb-6 bg-gradient-to-br from-brand-600 to-brand-800 p-6 text-white">
    <h2 class="text-xl font-bold">Welcome, <?= e($name) ?> 👋</h2>
    <p class="mt-1 text-brand-100">Admission No: <?= e((string) ($student['admission_no'] ?? '—')) ?> · Roll: <?= e((string) ($student['roll_no'] ?? '—')) ?></p>
</div>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div class="card p-5" data-animate-card>
        <p class="text-sm text-slate-500">Attendance</p>
        <p class="mt-1 text-3xl font-bold text-slate-900"><?= e((string) $attendance['rate']) ?>%</p>
        <p class="text-xs text-slate-400"><?= (int) $attendance['present'] ?> / <?= (int) $attendance['total'] ?> days present</p>
    </div>
    <div class="card p-5" data-animate-card>
        <p class="text-sm text-slate-500">Outstanding fees</p>
        <p class="mt-1 text-3xl font-bold text-slate-900"><?= e($currency) ?><?= number_format($feesDue, 2) ?></p>
        <a href="<?= e(url('/student/fees')) ?>" class="text-xs font-medium text-brand-600">View invoices →</a>
    </div>
    <div class="card p-5" data-animate-card>
        <p class="text-sm text-slate-500">Quick links</p>
        <div class="mt-2 flex flex-wrap gap-2">
            <a href="<?= e(url('/student/results')) ?>" class="badge badge-blue">Results</a>
            <a href="<?= e(url('/student/timetable')) ?>" class="badge badge-slate">Timetable</a>
            <a href="<?= e(url('/student/attendance')) ?>" class="badge badge-green">Attendance</a>
        </div>
    </div>
</div>
<?php $this->endSection(); ?>
