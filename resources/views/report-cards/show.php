<?php
/** @var array<string,mixed> $doc */
/** @var string $downloadUrl */
/** @var string $backUrl */
$b = $doc['branding']; $s = $doc['student']; $t = $doc['totals']; $a = $doc['attendance']; $ex = $doc['exam'] ?? [];
$meta = array_filter([$b['address'], $b['phone'], $b['email'], $b['website']]);
$sub = array_filter([
    $b['board'] ? 'Board: ' . $b['board'] : '',
    $b['affiliation'] ? 'Affiliation: ' . $b['affiliation'] : '',
    $b['udise'] ? 'UDISE: ' . $b['udise'] : '',
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Card · <?= e((string) $s['name']) ?> · <?= e((string) $doc['rc_uid']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @page { size: A4; margin: 12mm; }
        @media print { .no-print { display: none !important; } body { background: #fff !important; } .sheet { box-shadow: none !important; margin: 0 !important; } }
        .sheet { width: 210mm; min-height: 297mm; }
        @media (max-width: 820px) { .sheet { width: 100%; min-height: auto; } }
    </style>
</head>
<body class="bg-slate-100 text-slate-800">
    <!-- Toolbar -->
    <div class="no-print sticky top-0 z-10 flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-white px-4 py-3 shadow-sm">
        <a href="<?= e($backUrl) ?>" class="text-sm text-slate-600 hover:underline"><i class="fa-solid fa-arrow-left"></i> Back</a>
        <div class="flex flex-wrap gap-2">
            <a href="<?= e($downloadUrl) ?>" class="inline-flex items-center gap-1 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700"><i class="fa-solid fa-file-arrow-down"></i> Download PDF</a>
            <button onclick="window.print()" class="inline-flex items-center gap-1 rounded-lg bg-slate-700 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"><i class="fa-solid fa-print"></i> Print</button>
            <a href="<?= e((string) $doc['verify_url']) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"><i class="fa-solid fa-qrcode"></i> Verify</a>
        </div>
    </div>

    <!-- Report card sheet -->
    <div class="sheet mx-auto my-6 bg-white p-8 shadow-lg">
        <!-- Header -->
        <header class="border-b-2 border-brand-700 pb-3 text-center">
            <h1 class="text-2xl font-extrabold text-brand-700"><?= e((string) $b['name']) ?></h1>
            <?php if ($meta): ?><p class="mt-1 text-xs text-slate-500"><?= e(implode('  |  ', $meta)) ?></p><?php endif; ?>
            <?php if ($sub): ?><p class="text-xs text-slate-500"><?= e(implode('  |  ', $sub)) ?></p><?php endif; ?>
        </header>
        <div class="my-3 rounded bg-brand-700 py-2 text-center text-sm font-bold uppercase tracking-wide text-white">
            Report Card &mdash; <?= e((string) ($ex['name'] ?? '')) ?> (<?= e((string) $s['year']) ?>)
        </div>

        <!-- Student info -->
        <section class="flex items-start justify-between gap-4">
            <div class="grid flex-1 grid-cols-1 gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                <?php
                $info = [
                    'Name' => $s['name'], 'User ID' => $s['uid'], 'Admission No' => $s['admission_no'],
                    'Roll No' => $s['roll_no'] ?: '—', 'Class' => trim($s['class'] . ($s['section'] !== '' ? ' - ' . $s['section'] : '')),
                    'Academic Year' => $s['year'], 'Father' => $s['father'] ?: '—', 'Mother' => $s['mother'] ?: '—',
                    'Date of Birth' => $s['dob'] ?: '—', 'Gender' => $s['gender'] ?: '—',
                ];
                foreach ($info as $k => $v): ?>
                    <div class="flex gap-2"><span class="w-28 shrink-0 font-semibold text-slate-500"><?= e((string) $k) ?></span><span class="text-slate-800">: <?= e((string) $v) ?></span></div>
                <?php endforeach; ?>
            </div>
            <?php if ($s['photo'] !== ''): ?>
                <img src="<?= e(base_url('/storage/uploads/' . ltrim((string) $s['photo'], '/'))) ?>" alt="Photo" class="h-28 w-24 rounded border border-slate-200 object-cover" onerror="this.style.display='none'">
            <?php endif; ?>
        </section>

        <!-- Marks table -->
        <section class="mt-5 overflow-x-auto">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="bg-slate-100 text-slate-700">
                        <th class="border border-slate-300 px-2 py-1.5 text-left">Subject</th>
                        <th class="border border-slate-300 px-2 py-1.5">Code</th>
                        <th class="border border-slate-300 px-2 py-1.5">Max</th>
                        <th class="border border-slate-300 px-2 py-1.5">Obtained</th>
                        <th class="border border-slate-300 px-2 py-1.5">%</th>
                        <th class="border border-slate-300 px-2 py-1.5">Grade</th>
                        <th class="border border-slate-300 px-2 py-1.5">Result</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($doc['subjects'] as $row): ?>
                        <tr>
                            <td class="border border-slate-300 px-2 py-1.5"><?= e((string) $row['subject']) ?></td>
                            <td class="border border-slate-300 px-2 py-1.5 text-center text-slate-500"><?= e((string) $row['code']) ?></td>
                            <td class="border border-slate-300 px-2 py-1.5 text-center"><?= e((string) $row['max']) ?></td>
                            <td class="border border-slate-300 px-2 py-1.5 text-center font-medium"><?= e((string) $row['obtained']) ?></td>
                            <td class="border border-slate-300 px-2 py-1.5 text-center"><?= e((string) $row['percentage']) ?>%</td>
                            <td class="border border-slate-300 px-2 py-1.5 text-center"><?= e((string) $row['grade']) ?></td>
                            <td class="border border-slate-300 px-2 py-1.5 text-center"><?= e((string) $row['result']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="bg-slate-50 font-bold">
                        <td class="border border-slate-300 px-2 py-1.5 text-right" colspan="2">Grand Total</td>
                        <td class="border border-slate-300 px-2 py-1.5 text-center"><?= e((string) $t['max']) ?></td>
                        <td class="border border-slate-300 px-2 py-1.5 text-center"><?= e((string) $t['obtained']) ?></td>
                        <td class="border border-slate-300 px-2 py-1.5 text-center"><?= e((string) $t['percentage']) ?>%</td>
                        <td class="border border-slate-300 px-2 py-1.5 text-center" colspan="2"><?= e((string) $t['grade']) ?></td>
                    </tr>
                </tbody>
            </table>
        </section>

        <!-- Result + attendance -->
        <section class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div class="rounded border border-slate-200">
                <div class="rounded-t bg-brand-700 px-3 py-1.5 text-sm font-bold text-white">Result Summary</div>
                <dl class="divide-y divide-slate-100 text-sm">
                    <?php foreach ([
                        'Percentage' => $t['percentage'] . '%', 'Overall Grade' => $t['grade'], 'GPA' => $t['gpa'],
                        'Division' => $t['division'], 'Result' => $t['result'], 'Promotion Status' => $t['promotion'],
                    ] as $k => $v): ?>
                        <div class="flex justify-between px-3 py-1.5"><dt class="font-semibold text-slate-500"><?= e((string) $k) ?></dt><dd class="font-medium <?= $k === 'Result' ? ($t['result'] === 'PASSED' ? 'text-green-600' : 'text-red-600') : 'text-slate-800' ?>"><?= e((string) $v) ?></dd></div>
                    <?php endforeach; ?>
                </dl>
            </div>
            <div class="rounded border border-slate-200">
                <div class="rounded-t bg-brand-700 px-3 py-1.5 text-sm font-bold text-white">Attendance (<?= e((string) $s['year']) ?>)</div>
                <dl class="divide-y divide-slate-100 text-sm">
                    <?php foreach ([
                        'Working Days' => (int) ($a['totalClasses'] ?? 0), 'Present' => (int) ($a['present'] ?? 0),
                        'Absent' => (int) ($a['absent'] ?? 0), 'Late' => (int) ($a['late'] ?? 0),
                        'Leave' => (int) ($a['excused'] ?? $a['leave'] ?? 0), 'Attendance %' => ($a['attendancePercentage'] ?? 0) . '%',
                    ] as $k => $v): ?>
                        <div class="flex justify-between px-3 py-1.5"><dt class="font-semibold text-slate-500"><?= e((string) $k) ?></dt><dd class="font-medium text-slate-800"><?= e((string) $v) ?></dd></div>
                    <?php endforeach; ?>
                </dl>
            </div>
        </section>

        <!-- Signatures + verification -->
        <section class="mt-10 grid grid-cols-3 gap-4 text-center text-xs text-slate-500">
            <div class="border-t border-slate-400 pt-1">Class Teacher</div>
            <div class="border-t border-slate-400 pt-1">Principal</div>
            <div class="border-t border-slate-400 pt-1">Parent / Guardian</div>
        </section>

        <footer class="mt-6 flex items-center justify-between border-t border-slate-200 pt-3">
            <div class="text-xs text-slate-500">
                <p class="font-bold text-slate-700">Report Card ID: <?= e((string) $doc['rc_uid']) ?> (v<?= (int) $doc['version'] ?>)</p>
                <p>Scan the QR or visit the link to verify authenticity:</p>
                <p class="break-all text-brand-600"><?= e((string) $doc['verify_url']) ?></p>
            </div>
            <div id="rc-qr" class="shrink-0"></div>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
        new QRCode(document.getElementById('rc-qr'), { text: <?= json_encode((string) $doc['verify_url']) ?>, width: 96, height: 96, correctLevel: QRCode.CorrectLevel.M });
    </script>
</body>
</html>
