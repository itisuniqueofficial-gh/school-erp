<?php
/** @var array<string,mixed> $doc */
/** @var string $downloadUrl */
/** @var string $backUrl */
/** @var bool $canRevoke */
$b = $doc['branding']; $s = $doc['student'];
$meta = array_filter([$b['address'], $b['phone'], $b['email']]);
$extra = array_filter([
    $b['board'] ? 'Board: ' . $b['board'] : '',
    $b['affiliation'] ? 'Affiliation: ' . $b['affiliation'] : '',
    $b['udise'] ? 'UDISE: ' . $b['udise'] : '',
]);
$revoked = $doc['status'] === 'revoked';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e((string) $doc['title']) ?> · <?= e((string) $doc['cert_uid']) ?></title>
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
    <div class="no-print sticky top-0 z-10 flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-white px-4 py-3 shadow-sm">
        <a href="<?= e($backUrl) ?>" class="text-sm text-slate-600 hover:underline"><i class="fa-solid fa-arrow-left"></i> Back</a>
        <div class="flex flex-wrap gap-2">
            <a href="<?= e($downloadUrl) ?>" class="inline-flex items-center gap-1 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700"><i class="fa-solid fa-file-arrow-down"></i> Download PDF</a>
            <button onclick="window.print()" class="inline-flex items-center gap-1 rounded-lg bg-slate-700 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"><i class="fa-solid fa-print"></i> Print</button>
            <a href="<?= e((string) $doc['verify_url']) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"><i class="fa-solid fa-qrcode"></i> Verify</a>
            <?php if (!empty($canRevoke) && !$revoked): ?>
            <form method="POST" action="<?= e(url('/admin/certificates/' . $doc['cert_uid'] . '/revoke')) ?>" data-confirm="Revoke this certificate? It stays on record but will show as revoked on verification.">
                <?= csrf_field() ?><button class="inline-flex items-center gap-1 rounded-lg border border-red-300 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50"><i class="fa-solid fa-ban"></i> Revoke</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="sheet mx-auto my-6 bg-white p-10 shadow-lg">
        <div class="h-full border-2 border-brand-700 p-8">
            <?php if ($revoked): ?>
                <div class="mb-4 rounded bg-red-50 px-4 py-2 text-center text-sm font-bold text-red-700 no-print">This certificate has been REVOKED and is no longer valid.</div>
            <?php endif; ?>
            <header class="text-center">
                <h1 class="text-3xl font-extrabold text-brand-700"><?= e((string) $b['name']) ?></h1>
                <?php if ($meta): ?><p class="mt-1 text-xs text-slate-500"><?= e(implode('  |  ', $meta)) ?></p><?php endif; ?>
                <?php if ($extra): ?><p class="text-xs text-slate-500"><?= e(implode('  |  ', $extra)) ?></p><?php endif; ?>
            </header>

            <h2 class="mx-auto mt-8 w-max border-b-2 border-brand-700 pb-1 text-2xl font-bold uppercase tracking-wide text-slate-800"><?= e((string) $doc['title']) ?></h2>

            <div class="mt-6 flex justify-between text-sm text-slate-500">
                <span>Ref: <?= e((string) ($doc['reference_no'] !== '' ? $doc['reference_no'] : $doc['cert_uid'])) ?></span>
                <span>Date: <?= e((string) $doc['issued_on']) ?></span>
            </div>

            <p class="mt-6 text-justify text-lg leading-relaxed text-slate-800"><?= e((string) $doc['body']) ?></p>

            <dl class="mt-8 grid grid-cols-2 gap-x-6 gap-y-1 text-sm">
                <?php foreach ([
                    'Student Name' => $s['name'], 'Admission No' => $s['admission_no'],
                    'Class' => $s['class'] ?: '—', 'Academic Year' => $s['year'] ?: '—',
                ] as $k => $v): ?>
                    <div class="flex gap-2"><dt class="w-32 shrink-0 font-semibold text-slate-500"><?= e((string) $k) ?></dt><dd>: <?= e((string) $v) ?></dd></div>
                <?php endforeach; ?>
            </dl>

            <div class="mt-20 flex items-end justify-between">
                <div id="cert-qr"></div>
                <div class="text-center text-sm text-slate-600">
                    <div class="border-t border-slate-400 px-8 pt-1">Principal / Authorised Signatory</div>
                </div>
            </div>

            <footer class="mt-8 border-t border-slate-200 pt-3 text-center text-xs text-slate-500">
                <p class="font-bold text-brand-700">Certificate ID: <?= e((string) $doc['cert_uid']) ?></p>
                <p>Verify authenticity at <span class="break-all text-brand-600"><?= e((string) $doc['verify_url']) ?></span></p>
            </footer>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
        new QRCode(document.getElementById('cert-qr'), { text: <?= json_encode((string) $doc['verify_url']) ?>, width: 96, height: 96, correctLevel: QRCode.CorrectLevel.M });
    </script>
</body>
</html>
