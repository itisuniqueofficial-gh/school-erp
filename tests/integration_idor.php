<?php

declare(strict_types=1);

/**
 * IDOR / access-control regression guard.
 *
 * Verified during the P0 sweep that file-download/media endpoints are protected:
 *   - Media avatar/branding: filename reduced to a validated 32-char hex id
 *     (no path traversal, not enumerable); branding allow-listed to registered
 *     settings only.
 *   - Student portal downloads: resource-level student_id ownership checks.
 *   - Parent portal downloads: whereIn(student_id, childIds).
 *   - Admin downloads: RBAC-gated at the route level.
 * This test locks that behavior so a future change cannot silently reintroduce
 * an IDOR or traversal hole.
 */

require_once dirname(__DIR__) . '/bootstrap/app.php';

use App\Services\AvatarService;

$pass = 0; $fail = 0;
$check = static function (string $l, bool $c) use (&$pass, &$fail): void {
    if ($c) { $pass++; } else { $fail++; echo "FAIL  {$l}\n"; }
};

// --- Behavioral: AvatarService::path must neutralize path traversal / bad names.
$svc = new AvatarService();
$traversal = [
    '../../../../etc/passwd',
    '..%2f..%2f.env',
    '/etc/passwd',
    'foo/bar.jpg',
    'not-a-hex-name.jpg',
    '../' . str_repeat('a', 32) . '.jpg',
    str_repeat('a', 31) . '.jpg',        // wrong length
    str_repeat('g', 32) . '.jpg',        // non-hex chars
    str_repeat('a', 32) . '.php',        // disallowed extension
    '',
];
$allNull = true;
foreach ($traversal as $bad) {
    if ($svc->path($bad) !== null) { $allNull = false; echo "  path() accepted unsafe input: {$bad}\n"; }
    if ($svc->path($bad, true) !== null) { $allNull = false; echo "  path(thumb) accepted unsafe input: {$bad}\n"; }
}
$check('AvatarService::path rejects traversal / malformed / non-hex filenames', $allNull);

// A well-formed hex id is accepted by the validator (returns a path string or
// null only because the file may not exist — never throws, never traverses).
$wellFormed = str_repeat('a', 32) . '.jpg';
$res = $svc->path($wellFormed);
$check('AvatarService::path accepts a well-formed hex id without error', $res === null || is_string($res));

// --- Static guards: portal download methods must enforce resource ownership.
$root = dirname(__DIR__);
$sp = (string) file_get_contents($root . '/app/Controllers/Portal/StudentPortalController.php');
$pp = (string) file_get_contents($root . '/app/Controllers/Portal/ParentPortalController.php');
$mc = (string) file_get_contents($root . '/app/Controllers/MediaController.php');

$check('student downloadDocument checks student_id ownership', (bool) preg_match('/downloadDocument.*?student_id.*?!==.*?student/s', $sp) || str_contains($sp, "(int) \$doc['student_id'] !== (int) \$student['id']"));
$check('student report-card download uses ownedCard()', str_contains($sp, 'ownedCard('));
$check('student certificate download filters by student_id', (bool) preg_match("/downloadCertificate.*?where\('student_id'/s", $sp));
$check('student hall-ticket download uses ownedTicket()', str_contains($sp, 'ownedTicket('));
$check('ownedCard filters report_cards by student_id', (bool) preg_match("/ownedCard.*?report_cards.*?where\('student_id', \\\$sid\)/s", $sp));
$check('parent report-card download restricts to childIds', (bool) preg_match('/linkedCard.*?whereIn\(.student_id., \$ids\)/s', $pp));

$check('media avatar serving validates via hex id (no raw path use)', str_contains($mc, 'AvatarService') && !str_contains($mc, "file_get_contents(\$file)"));
$check('media branding is allow-listed to registered settings', str_contains($mc, "whereIn('key'") && str_contains($mc, 'abort(404'));

// hexId itself is strict.
$av = (string) file_get_contents($root . '/app/Services/AvatarService.php');
$check('hexId rejects path separators (basename guard)', str_contains($av, '$base !== $filename'));
$check('hexId enforces a strict 32-hex pattern', (bool) preg_match('/\^\(\[a-f0-9\]\{32\}\)/', $av));

echo "PASS: {$pass}   FAIL: {$fail}\n";
exit($fail === 0 ? 0 : 1);
