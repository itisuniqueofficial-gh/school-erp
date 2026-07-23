<?php
/** @var string $code */
/** @var array<string,mixed>|null $result */
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hall Ticket Verification · <?= e((string) ($appName ?? config('app.name'))) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="flex min-h-full items-center justify-center bg-slate-50 px-6 py-10 text-slate-700">
    <main class="w-full max-w-md">
        <div class="mb-6 text-center">
            <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-600 text-2xl text-white"><i class="fa-solid fa-ticket"></i></span>
            <h1 class="mt-3 text-lg font-bold text-slate-900"><?= e((string) ($appName ?? config('app.name'))) ?></h1>
            <p class="text-sm text-slate-500">Hall Ticket Verification</p>
        </div>

        <?php if ($result === null): ?>
            <div class="rounded-xl border border-red-200 bg-red-50 p-6 text-center">
                <i class="fa-solid fa-circle-xmark text-3xl text-red-500"></i>
                <h2 class="mt-3 font-semibold text-slate-800">Not verified</h2>
                <p class="mt-1 text-sm text-slate-500">No hall ticket matches <span class="font-mono"><?= e($code) ?></span>.</p>
            </div>
        <?php elseif (empty($result['valid'])): ?>
            <div class="rounded-xl border border-amber-200 bg-white p-6 shadow-sm">
                <div class="mb-4 flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800"><i class="fa-solid fa-triangle-exclamation"></i> This hall ticket is <strong><?= e((string) $result['status']) ?></strong> and is not valid.</div>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-400">Hall Ticket ID</dt><dd class="font-mono font-semibold text-slate-800"><?= e((string) $result['ht_uid']) ?></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">Student</dt><dd class="font-medium text-slate-800"><?= e((string) $result['student_name']) ?></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">Status</dt><dd class="font-semibold text-red-600"><?= e(ucfirst((string) $result['status'])) ?></dd></div>
                </dl>
            </div>
        <?php else: ?>
            <div class="rounded-xl border border-green-200 bg-white p-6 shadow-sm">
                <div class="mb-4 flex items-center gap-2 rounded-lg bg-green-50 px-3 py-2 text-sm text-green-800"><i class="fa-solid fa-circle-check"></i> Valid hall ticket issued by this institution.</div>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-400">Hall Ticket ID</dt><dd class="font-mono font-semibold text-slate-800"><?= e((string) $result['ht_uid']) ?></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">Type</dt><dd class="text-slate-700"><?= e((string) $result['type']) ?></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">Student</dt><dd class="font-medium text-slate-800"><?= e((string) $result['student_name']) ?></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">Student ID</dt><dd class="font-mono text-slate-700"><?= e((string) $result['student_uid']) ?></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">Class</dt><dd class="text-slate-700"><?= e(trim((string) $result['class_name'] . ' ' . (string) $result['section'])) ?: '—' ?></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">Examination</dt><dd class="text-slate-700"><?= e((string) $result['exam_name']) ?: '—' ?></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">Academic Year</dt><dd class="text-slate-700"><?= e((string) $result['academic_year']) ?: '—' ?></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">Issued On</dt><dd class="text-slate-700"><?= e((string) $result['issued_on']) ?></dd></div>
                </dl>
            </div>
            <p class="mt-4 text-center text-xs text-slate-400">Only non-sensitive information is shown for privacy.</p>
        <?php endif; ?>
    </main>
</body>
</html>
