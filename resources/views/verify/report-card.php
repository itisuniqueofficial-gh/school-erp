<?php
/** @var string $code */
/** @var array<string,mixed>|null $result */
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Card Verification · <?= e((string) ($appName ?? config('app.name'))) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="flex min-h-full items-center justify-center bg-slate-50 px-6 py-10 text-slate-700">
    <main class="w-full max-w-md">
        <div class="mb-6 text-center">
            <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-600 text-2xl text-white"><i class="fa-solid fa-certificate"></i></span>
            <h1 class="mt-3 text-lg font-bold text-slate-900"><?= e((string) ($appName ?? config('app.name'))) ?></h1>
            <p class="text-sm text-slate-500">Report Card Verification</p>
        </div>

        <?php if ($result === null): ?>
            <div class="rounded-xl border border-red-200 bg-red-50 p-6 text-center">
                <i class="fa-solid fa-circle-xmark text-3xl text-red-500"></i>
                <h2 class="mt-3 font-semibold text-slate-800">Not verified</h2>
                <p class="mt-1 text-sm text-slate-500">No report card matches <span class="font-mono"><?= e($code) ?></span>. It may be invalid or withdrawn.</p>
            </div>
        <?php else: ?>
            <div class="rounded-xl border border-green-200 bg-white p-6 shadow-sm">
                <div class="mb-4 flex items-center gap-2 rounded-lg bg-green-50 px-3 py-2 text-sm text-green-800">
                    <i class="fa-solid fa-circle-check"></i> Authentic report card issued by this institution.
                </div>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-400">Report Card ID</dt><dd class="font-mono font-semibold text-slate-800"><?= e((string) $result['rc_uid']) ?> <span class="text-slate-400">v<?= (int) $result['version'] ?></span></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">Student</dt><dd class="font-medium text-slate-800"><?= e((string) $result['student_name']) ?></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">Student ID</dt><dd class="font-mono text-slate-700"><?= e((string) $result['student_uid']) ?></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">Academic Year</dt><dd class="text-slate-700"><?= e((string) $result['academic_year']) ?: '—' ?></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">Class</dt><dd class="text-slate-700"><?= e((string) $result['class_name']) ?: '—' ?></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">Examination</dt><dd class="text-slate-700"><?= e((string) $result['exam_name']) ?: '—' ?></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">Result</dt><dd><span class="rounded px-2 py-0.5 text-xs font-semibold <?= $result['result'] === 'pass' ? 'bg-green-100 text-green-700' : ($result['result'] === 'fail' ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-600') ?>"><?= e(ucfirst((string) $result['result'])) ?></span></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">Status</dt><dd class="text-slate-700"><?= !empty($result['published']) ? 'Published' : 'Draft' ?></dd></div>
                </dl>
            </div>
            <p class="mt-4 text-center text-xs text-slate-400">Only non-sensitive information is shown for privacy. Marks and grades are not disclosed here.</p>
        <?php endif; ?>
    </main>
</body>
</html>
