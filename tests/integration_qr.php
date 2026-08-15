<?php

declare(strict_types=1);

/**
 * QR-code architecture regression guard.
 *
 * Fails if the duplicate-QR bug is reintroduced. Canonical architecture:
 *   - Web UI QR: ONE client-side initializer (ERP.initQRCodes in app.js),
 *     idempotent via the data-qr-init guard, loading qrcodejs exactly once.
 *   - PDF/print QR: server-side QrService (bundled phpqrcode) via PdfDocument.
 * There must be no second [data-qr] initializer and no view rendering the same
 * logical QR container twice.
 */

$root = dirname(__DIR__);
$pass = 0; $fail = 0;
$check = static function (string $l, bool $c) use (&$pass, &$fail): void {
    if ($c) { $pass++; } else { $fail++; echo "FAIL  {$l}\n"; }
};

$js = (string) file_get_contents($root . '/public/assets/js/app.js');

// Exactly one client-side QR construction site.
$ctorCount = preg_match_all('/new\s+(?:window\.)?QRCode\s*\(/', $js);
$check("app.js constructs QRCode exactly once (found {$ctorCount})", $ctorCount === 1);

// The qrcodejs library is referenced exactly once (single lazy include).
$libCount = substr_count($js, 'qrcode.min.js');
$check("app.js references the qrcodejs CDN exactly once (found {$libCount})", $libCount === 1);

// The canonical initializer exists and is idempotent.
$check('canonical ERP.initQRCodes initializer exists', str_contains($js, 'ERP.initQRCodes'));
$check('client QR init is guarded against re-run (data-qr-init)', str_contains($js, 'data-qr-init'));
$check('client QR init only targets un-initialized nodes', str_contains($js, '[data-qr]:not([data-qr-init])'));
$check('client QR init clears container before render (no stacking)', (bool) preg_match('/el\.innerHTML\s*=\s*[\'"]{2}/', $js));

// No legacy jQuery [data-qr] handler remains.
$check('no legacy $(\'[data-qr]\') initializer remains', !str_contains($js, "\$('[data-qr]')") && !str_contains($js, '$("[data-qr]")'));

// Every view has at most ONE [data-qr] container (one logical QR per page/record).
$viewsDir = $root . '/resources/views';
$dupViews = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsDir, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if (!$f->isFile() || !str_ends_with($f->getFilename(), '.php')) { continue; }
    $src = (string) file_get_contents($f->getPathname());
    // Count literal data-qr= attributes (a QR container), not passing mentions.
    $n = preg_match_all('/\sdata-qr\s*=/', $src);
    if ($n > 1) { $dupViews[str_replace($root . '/', '', $f->getPathname())] = $n; }
}
if ($dupViews) {
    foreach ($dupViews as $v => $n) { echo "  {$v}: {$n} data-qr containers\n"; }
}
$check('no view declares more than one data-qr container', $dupViews === []);

// Server-side QR service is intact (PDF/print path unchanged).
$check('QrService (server-side phpqrcode) present', is_file($root . '/app/Services/QrService.php'));
$check('bundled phpqrcode library present', is_file($root . '/lib/phpqrcode/phpqrcode.php'));
$check('PdfDocument uses server-side QrService for verification band', str_contains((string) file_get_contents($root . '/app/Services/Pdf/PdfDocument.php'), 'QrService'));

echo "PASS: {$pass}   FAIL: {$fail}\n";
exit($fail === 0 ? 0 : 1);
