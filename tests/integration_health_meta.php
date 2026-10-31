<?php

declare(strict_types=1);

/**
 * ERP Health "why it matters" regression test.
 *
 * Every integrity check surfaced on the dashboard must carry a plain-language
 * explanation (why it matters) and a recommended action label, a valid
 * severity, and a fix link. Detection stays read-only (idempotent).
 */

require_once dirname(__DIR__) . '/bootstrap/app.php';

use App\Services\IntegrityService;

$pass = 0; $fail = 0;
$check = static function (string $l, bool $c) use (&$pass, &$fail): void {
    if ($c) { $pass++; } else { $fail++; echo "FAIL  {$l}\n"; }
};

$svc = new IntegrityService();
$checks = $svc->checks();
$check('checks() returns items', is_array($checks) && count($checks) > 0);

$validSeverity = ['critical', 'warning', 'info'];
$missingWhy = []; $missingAction = []; $badSeverity = [];
foreach ($checks as $c) {
    $key = (string) ($c['key'] ?? '?');
    foreach (['key', 'label', 'count', 'severity', 'link', 'why', 'action'] as $field) {
        if (!array_key_exists($field, $c)) { $missingWhy[] = "{$key}:{$field}"; }
    }
    if (trim((string) ($c['why'] ?? '')) === '') { $missingWhy[] = $key; }
    if (trim((string) ($c['action'] ?? '')) === '') { $missingAction[] = $key; }
    if (!in_array((string) ($c['severity'] ?? ''), $validSeverity, true)) { $badSeverity[] = $key; }
}
if ($missingWhy) { echo '  missing why: ' . implode(', ', $missingWhy) . "\n"; }
if ($missingAction) { echo '  missing action: ' . implode(', ', $missingAction) . "\n"; }
if ($badSeverity) { echo '  bad severity: ' . implode(', ', $badSeverity) . "\n"; }

$check('every check has a non-empty "why it matters"', $missingWhy === []);
$check('every check has a recommended action label', $missingAction === []);
$check('every check has a valid severity', $badSeverity === []);
$check('every check has a fix link', array_reduce($checks, static fn ($ok, $c) => $ok && trim((string) ($c['link'] ?? '')) !== '', true));

// attention() (dashboard feed) carries the same enriched fields.
$attention = $svc->attention(6);
$attentionOk = true;
foreach ($attention as $a) {
    if (!array_key_exists('why', $a) || !array_key_exists('action', $a)) { $attentionOk = false; }
}
$check('attention() items include why + action', $attentionOk);
$check('attention() is capped to the limit', count($attention) <= 6);

// Read-only / idempotent: two runs yield the same shape and counts.
$again = $svc->checks();
$check('checks() is stable across runs (read-only)', count($again) === count($checks));

echo "PASS: {$pass}   FAIL: {$fail}\n";
exit($fail === 0 ? 0 : 1);
