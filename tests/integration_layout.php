<?php

declare(strict_types=1);

/**
 * Responsive-layout regression guard.
 *
 * Locks the global full-width content conventions so a page cannot reintroduce
 * the "narrow form on a huge empty area" problem:
 *   - No view wraps content in a narrow fixed max-width CARD (card + max-w-*).
 *   - The app shell <main> stays fluid (no max-width cap).
 *   - No fixed-pixel layout widths in views (w-[NNNpx] / min-w-[NNNpx]).
 *   - The responsive-table CSS fallback is present.
 * Intentionally-narrow layouts (print/A4, auth, modals) do NOT use the
 * `card max-w-*` wrapper pattern, so they are unaffected.
 */

$root = dirname(__DIR__);
$viewsDir = $root . '/resources/views';
$pass = 0; $fail = 0;
$check = static function (string $l, bool $c) use (&$pass, &$fail): void {
    if ($c) { $pass++; } else { $fail++; echo "FAIL  {$l}\n"; }
};

$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsDir, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) { $files[] = $f->getPathname(); }
}

// 1. No narrow-card wrapper antipattern (a card capped to a small width leaves
//    the rest of the content area empty).
$narrowCards = []; $fixedPx = [];
foreach ($files as $file) {
    $src = (string) file_get_contents($file);
    $rel = str_replace($root . '/', '', $file);
    if (preg_match('/class="card [^"]*max-w-(sm|md|lg|xl|2xl|3xl)\b/', $src)) {
        $narrowCards[] = $rel;
    }
    if (preg_match('/\bw-\[[0-9]+px\]|\bmin-w-\[[0-9]+px\]|style="[^"]*width:\s*[0-9]+px/', $src)) {
        $fixedPx[] = $rel;
    }
}
if ($narrowCards) { echo '  narrow card wrappers in: ' . implode(', ', $narrowCards) . "\n"; }
if ($fixedPx) { echo '  fixed-px widths in: ' . implode(', ', $fixedPx) . "\n"; }
$check('no view uses a narrow fixed-width card wrapper', $narrowCards === []);
$check('no view uses fixed-pixel layout widths', $fixedPx === []);

// 2. The app shell main content is fluid (no max-width cap).
$app = (string) file_get_contents($viewsDir . '/layouts/app.php');
if (preg_match('/<main[^>]*>/', $app, $m)) {
    $check('app <main> has no max-width cap (fluid content)', !str_contains($m[0], 'max-w-'));
} else {
    $check('app layout has a <main> element', false);
}

// 3. Responsive-table fallback exists.
$css = (string) file_get_contents($root . '/public/assets/css/app.css');
$check('responsive-table CSS fallback present', str_contains($css, '.data-table:not(.table-cards)') && str_contains($css, 'overflow-x'));

// 4. Global overflow guard present (defensive, not a substitute for real fixes).
$check('global min-width:0 guard present (flex/grid children can shrink)', (bool) preg_match('/\*,\s*\*::before,\s*\*::after\s*\{\s*min-width:\s*0/', $css));

// 5. Canonical action-control system exists and is used by list views.
$check('.action-group defined in CSS', str_contains($css, '.action-group'));
$check('.action-link defined in CSS', str_contains($css, '.action-link'));
$check('.action-icon defined in CSS', str_contains($css, '.action-icon'));
$actionViews = ['admin/certificates/index.php', 'admin/students/index.php', 'admin/teachers/index.php', 'admin/staff/index.php', 'admin/hall-tickets/index.php'];
$missing = [];
foreach ($actionViews as $v) {
    if (!str_contains((string) file_get_contents($viewsDir . '/' . $v), 'action-group')) { $missing[] = $v; }
}
if ($missing) { echo '  views not using action-group: ' . implode(', ', $missing) . "\n"; }
$check('key list views use the canonical action-group', $missing === []);

echo "PASS: {$pass}   FAIL: {$fail}\n";
exit($fail === 0 ? 0 : 1);
