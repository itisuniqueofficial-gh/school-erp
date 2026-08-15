<?php

declare(strict_types=1);

/**
 * Accessibility static-audit guard.
 *
 * Not a substitute for real screen-reader/keyboard testing, but a fast
 * regression guard that fails the build if a view reintroduces:
 *   - an icon-only <button>/<a> with no accessible name (aria-label/title), or
 *   - an <img> with no alt attribute.
 * Prefer-native-semantics is a manual concern; this catches the common misses.
 */

$root = dirname(__DIR__);
$viewsDir = $root . '/resources/views';

$pass = 0; $fail = 0;
$check = static function (string $l, bool $c) use (&$pass, &$fail): void {
    if ($c) { $pass++; } else { $fail++; echo "FAIL  {$l}\n"; }
};

/** @return string[] all view files */
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsDir, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->isFile() && str_ends_with($f->getFilename(), '.php')) {
        $files[] = $f->getPathname();
    }
}
$check('view files discovered', count($files) > 20);

$iconOnlyBtn = 0; $iconOnlyBtnFiles = [];
$imgNoAlt = 0; $imgNoAltFiles = [];

foreach ($files as $file) {
    $src = (string) file_get_contents($file);
    $rel = str_replace($root . '/', '', $file);
    // Neutralize embedded PHP blocks so short-echo tags (which contain a '>')
    // do not break HTML tag-boundary detection. Replace each block with a token.
    $src = preg_replace(['/<\?php.*?\?\x3e/s', '/<\?=.*?\?\x3e/s'], 'X', $src) ?? $src;

    // Icon-only <button>/<a>: content is only <i ...></i> and whitespace, and the
    // opening tag has no aria-label / aria-labelledby / title.
    if (preg_match_all('#<(button|a)\b([^>]*)>\s*(?:<i\b[^>]*>\s*</i>\s*)+</\1>#i', $src, $m, PREG_SET_ORDER)) {
        foreach ($m as $match) {
            $attrs = $match[2];
            if (!preg_match('/\baria-label\s*=/i', $attrs)
                && !preg_match('/\baria-labelledby\s*=/i', $attrs)
                && !preg_match('/\btitle\s*=/i', $attrs)) {
                $iconOnlyBtn++;
                $iconOnlyBtnFiles[$rel] = true;
            }
        }
    }

    // <img> without an alt attribute (alt="" is allowed for decorative).
    if (preg_match_all('#<img\b([^>]*)>#i', $src, $m2, PREG_SET_ORDER)) {
        foreach ($m2 as $match) {
            if (!preg_match('/\balt\s*=/i', $match[1])) {
                $imgNoAlt++;
                $imgNoAltFiles[$rel] = true;
            }
        }
    }
}

if ($iconOnlyBtn > 0) {
    echo '  icon-only controls without an accessible name in: ' . implode(', ', array_keys($iconOnlyBtnFiles)) . "\n";
}
if ($imgNoAlt > 0) {
    echo '  <img> without alt in: ' . implode(', ', array_keys($imgNoAltFiles)) . "\n";
}

$check('no icon-only button/link without an accessible name', $iconOnlyBtn === 0);
$check('no <img> without an alt attribute', $imgNoAlt === 0);

// Skip link + main landmark present in the app layout.
$app = (string) file_get_contents($viewsDir . '/layouts/app.php');
$check('app layout has a skip-to-content link', str_contains($app, 'skip-link') && str_contains($app, '#main-content'));
$check('app layout main is a focus target', (bool) preg_match('/<main[^>]*id="main-content"[^>]*tabindex="-1"/', $app));

// html has a lang attribute.
$base = (string) file_get_contents($viewsDir . '/layouts/base.php');
$check('html element declares lang', (bool) preg_match('/<html[^>]*lang=/', $base));

echo "PASS: {$pass}   FAIL: {$fail}\n";
exit($fail === 0 ? 0 : 1);
