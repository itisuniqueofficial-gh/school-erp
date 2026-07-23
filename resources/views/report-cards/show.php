<?php
/** @var array<string,mixed> $doc */
/** @var string $downloadUrl */
/** @var string $backUrl */
$inlineUrl = $downloadUrl . '?inline=1';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Card · <?= e((string) $doc['student']['name']) ?> · <?= e((string) $doc['rc_uid']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="h-full bg-slate-200">
    <div class="sticky top-0 z-10 flex flex-wrap items-center justify-between gap-2 border-b border-slate-300 bg-white px-4 py-3 shadow-sm">
        <div>
            <a href="<?= e($backUrl) ?>" class="text-sm text-slate-600 hover:underline"><i class="fa-solid fa-arrow-left"></i> Back</a>
            <span class="ml-3 text-sm font-semibold text-slate-800">Report Card &mdash; <?= e((string) $doc['student']['name']) ?></span>
            <span class="ml-2 font-mono text-xs text-slate-400"><?= e((string) $doc['rc_uid']) ?> v<?= (int) $doc['version'] ?></span>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="<?= e($downloadUrl) ?>" class="inline-flex items-center gap-1 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700"><i class="fa-solid fa-file-arrow-down"></i> Download PDF</a>
            <a href="<?= e($inlineUrl) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-lg bg-slate-700 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"><i class="fa-solid fa-print"></i> Open / Print</a>
            <a href="<?= e((string) $doc['verify_url']) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"><i class="fa-solid fa-qrcode"></i> Verify</a>
        </div>
    </div>

    <!-- The preview IS the generated PDF, so it is always identical to the download. -->
    <div class="mx-auto max-w-4xl px-2 py-4 sm:px-4">
        <div class="overflow-hidden rounded-lg bg-white shadow-lg" style="aspect-ratio: 1 / 1.414;">
            <iframe src="<?= e($inlineUrl) ?>" title="Report card preview" class="h-full w-full" style="border:0;"></iframe>
        </div>
        <p class="mt-3 text-center text-xs text-slate-500">
            Live preview of the exact PDF. If it does not display on your device,
            <a href="<?= e($inlineUrl) ?>" target="_blank" rel="noopener" class="text-brand-600 underline">open it in a new tab</a>
            or use <b>Download PDF</b>.
        </p>
    </div>
</body>
</html>
