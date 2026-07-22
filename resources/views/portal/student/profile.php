<?php
/** @var \Core\View\View $this */
/** @var array<string,mixed>|null $student */
/** @var string $userUid */
/** @var array<int,array<string,mixed>> $guardians */
/** @var array<int,array<string,mixed>> $enrollments */
/** @var array<string,mixed> $attendance */
/** @var array<int,array<string,mixed>> $subjectAttendance */
/** @var array<int,array<string,mixed>> $documents */
/** @var array<string,string> $documentTypes */
/** @var string $aadhaarMasked */
/** @var array{percent:int,missing:array<int,string>} $completion */
$s = $student ?? [];
$name = trim((string) (($s['first_name'] ?? '') . ' ' . ($s['last_name'] ?? ''))) ?: 'Student';
$usesSections = (int) ($s['uses_sections'] ?? 1) === 1;
$idRow = static function (string $label, ?string $val, bool $copy = false): string {
    $v = $val !== null && $val !== '' ? $val : '—';
    $btn = ($copy && $v !== '—') ? ' <button type="button" data-copy="' . e($v) . '" class="text-brand-600 hover:text-brand-800" aria-label="Copy ' . e($label) . '"><i class="fa-solid fa-copy"></i></button>' : '';
    return '<div><dt class="text-xs font-medium uppercase tracking-wide text-slate-400">' . e($label) . '</dt><dd class="text-sm text-slate-700 font-mono">' . e($v) . $btn . '</dd></div>';
};
?>
<?php $this->extend('layouts.app'); ?>
<?php $this->section('main'); ?>

<!-- Header -->
<div class="card mb-6 p-6">
    <div class="flex flex-col items-center gap-4 sm:flex-row sm:items-start">
        <?php if (!empty($s['photo'])): ?>
            <img src="<?= e(asset('storage/uploads/students/' . $s['photo'])) ?>" alt="<?= e($name) ?>" class="h-24 w-24 flex-none rounded-full object-cover">
        <?php else: ?>
            <span class="flex h-24 w-24 flex-none items-center justify-center rounded-full bg-brand-600 text-3xl font-bold text-white"><?= e(strtoupper(substr($name, 0, 1))) ?></span>
        <?php endif; ?>
        <div class="min-w-0 flex-1 text-center sm:text-left">
            <h2 class="text-xl font-bold text-slate-900"><?= e($name) ?></h2>
            <p class="text-sm text-slate-500">
                <?= e((string) ($s['class_name'] ?? '—')) ?><?php if ($usesSections && !empty($s['section_name'])): ?> · Section <?= e((string) $s['section_name']) ?><?php endif; ?>
                · Roll <?= e((string) ($s['roll_no'] ?? '—')) ?> · <?= e((string) ($s['year_name'] ?? '')) ?>
            </p>
            <span class="badge <?= ($s['status'] ?? '') === 'active' ? 'badge-green' : 'badge-slate' ?> mt-2"><?= e(ucfirst((string) ($s['status'] ?? '—'))) ?></span>
        </div>
        <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-left">
            <?= $idRow('Student ID', (string) ($s['uid'] ?? ''), true) ?>
            <?= $idRow('User ID', $userUid, true) ?>
            <?= $idRow('Admission No', (string) ($s['admission_no'] ?? ''), true) ?>
            <?= $idRow('Public Code', (string) ($s['public_id'] ?? '')) ?>
        </dl>
    </div>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <!-- Personal -->
    <div class="card p-6 lg:col-span-2">
        <h2 class="mb-4 font-semibold text-slate-800">Personal Information</h2>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
            <?php foreach ([
                'Date of Birth' => $s['dob'] ?? '—', 'Gender' => ucfirst((string) ($s['gender'] ?? '—')),
                'Blood Group' => $s['blood_group'] ?? '—', 'Nationality' => $s['nationality'] ?? '—',
                'Category' => $s['category'] ?? '—', 'Religion' => $s['religion'] ?? '—',
            ] as $l => $v): ?>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-400"><?= e($l) ?></dt><dd class="text-sm text-slate-700"><?= e((string) $v) ?></dd></div>
            <?php endforeach; ?>
        </dl>
        <h3 class="mb-2 mt-5 text-sm font-semibold text-slate-700">Government &amp; Academic IDs</h3>
        <dl class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
            <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-400">Aadhaar</dt><dd class="text-sm text-slate-700 font-mono"><?= e($aadhaarMasked) ?></dd></div>
            <?php foreach (['ABC ID' => 'abc_id', 'APAAR ID' => 'apaar_id', 'PEN ID' => 'pen_id'] as $l => $c): ?>
                <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-400"><?= e($l) ?></dt><dd class="text-sm text-slate-700"><?= e((string) ($s[$c] ?? '—')) ?></dd></div>
            <?php endforeach; ?>
        </dl>
    </div>

    <!-- Attendance -->
    <div class="card p-6">
        <h2 class="mb-4 font-semibold text-slate-800">Attendance</h2>
        <?php if ($attendance['hasData']): ?>
            <p class="text-3xl font-bold text-slate-900"><?= number_format($attendance['attendancePercentage'], 2) ?>%</p>
            <p class="mb-3 text-xs text-slate-400"><?= (int) ($attendance['present'] + $attendance['late'] + $attendance['half_day']) ?> / <?= (int) $attendance['totalClasses'] ?> classes attended</p>
            <dl class="space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Present</dt><dd class="font-medium"><?= (int) $attendance['present'] ?></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Absent</dt><dd class="font-medium"><?= (int) $attendance['absent'] ?></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Late</dt><dd class="font-medium"><?= (int) $attendance['late'] ?></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Excused</dt><dd class="font-medium"><?= (int) $attendance['excused'] ?></dd></div>
            </dl>
            <a href="<?= e(url('/student/attendance')) ?>" class="mt-3 inline-block text-sm text-brand-600 hover:underline">Full attendance →</a>
        <?php else: ?>
            <p class="text-sm text-slate-400">No attendance records are available for the selected academic year.</p>
        <?php endif; ?>
    </div>

    <!-- Guardians -->
    <div class="card p-6 lg:col-span-2">
        <h2 class="mb-4 font-semibold text-slate-800">Parents &amp; Guardians</h2>
        <?php if (empty($guardians)): ?><p class="text-sm text-slate-400">No guardian information recorded.</p><?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($guardians as $g): ?>
            <li class="flex items-center justify-between py-2">
                <div><p class="text-sm font-medium text-slate-800"><?= e((string) $g['name']) ?> <?php if (!empty($g['is_primary'])): ?><span class="badge badge-blue ml-1">Primary</span><?php endif; ?></p>
                    <p class="text-xs text-slate-500"><?= e(ucfirst((string) $g['relation'])) ?> · <?= e((string) ($g['phone'] ?? '—')) ?> · <?= e((string) ($g['email'] ?? '—')) ?></p></div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>

    <!-- Contact / Address -->
    <div class="card p-6">
        <h2 class="mb-4 font-semibold text-slate-800">Contact</h2>
        <dl class="space-y-2 text-sm">
            <div><dt class="text-xs uppercase tracking-wide text-slate-400">Email</dt><dd class="text-slate-700"><?= e((string) ($s['email'] ?? '—')) ?></dd></div>
            <div><dt class="text-xs uppercase tracking-wide text-slate-400">Phone</dt><dd class="text-slate-700"><?= e((string) ($s['phone'] ?? '—')) ?></dd></div>
            <div><dt class="text-xs uppercase tracking-wide text-slate-400">Address</dt><dd class="text-slate-700"><?= e((string) ($s['address'] ?? '—')) ?></dd></div>
        </dl>
    </div>

    <!-- Academic history -->
    <div class="card p-6 lg:col-span-2">
        <h2 class="mb-4 font-semibold text-slate-800">Academic Enrollment History</h2>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Academic Year</th><th>Class</th><th>Section</th><th>Roll No</th><th>Status</th></tr></thead>
                <tbody>
                <?php if (empty($enrollments)): ?><tr><td colspan="5" class="py-6 text-center text-slate-400">No enrollment history.</td></tr>
                <?php else: foreach ($enrollments as $en): ?>
                    <tr>
                        <td><?= e((string) ($en['year_name'] ?? '—')) ?></td>
                        <td><?= e((string) ($en['class_name'] ?? '—')) ?></td>
                        <td><?= e((string) ($en['section_name'] ?? 'N/A')) ?></td>
                        <td><?= e((string) ($en['roll_no'] ?? '—')) ?></td>
                        <td><span class="badge <?= ($en['status'] ?? '') === 'active' ? 'badge-green' : 'badge-slate' ?>"><?= e(ucfirst((string) $en['status'])) ?></span></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Profile completion -->
    <div class="card p-6">
        <h2 class="mb-2 font-semibold text-slate-800">Document Completion</h2>
        <p class="text-3xl font-bold text-slate-900"><?= (int) $completion['percent'] ?>%</p>
        <?php if (!empty($completion['missing'])): ?>
            <p class="mt-2 text-xs font-medium text-slate-500">Missing:</p>
            <ul class="mt-1 space-y-1 text-sm text-amber-700">
                <?php foreach ($completion['missing'] as $m): ?><li><i class="fa-solid fa-triangle-exclamation mr-1"></i><?= e($m) ?></li><?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <a href="<?= e(url('/student/documents')) ?>" class="mt-3 inline-block btn btn-secondary btn-sm"><i class="fa-solid fa-upload"></i> Manage documents</a>
    </div>

    <!-- Subject attendance -->
    <?php if (!empty($subjectAttendance)): ?>
    <div class="card p-6 lg:col-span-3">
        <h2 class="mb-4 font-semibold text-slate-800">Subject-wise Attendance</h2>
        <div class="flex flex-wrap gap-3">
            <?php foreach ($subjectAttendance as $sa): ?>
            <div class="rounded-lg border border-slate-200 px-4 py-2 text-sm">
                <span class="font-medium text-slate-800"><?= e((string) $sa['subject_name']) ?></span>
                <span class="ml-2 text-slate-500"><?= number_format($sa['percentage'], 2) ?>% (<?= (int) $sa['present'] ?>/<?= (int) $sa['total'] ?>)</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
