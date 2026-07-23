<?php

declare(strict_types=1);

/**
 * "Needs your attention" dashboard assistant test.
 *
 *   APP_TESTING=1 php bin/console migrate:fresh && APP_TESTING=1 php bin/console seed
 *   APP_TESTING=1 php tests/integration_assistant.php
 */

use App\Services\IntegrityService;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }

$svc = new IntegrityService();
$items = $svc->attention(6);

echo "\n== attention() shape & ordering ==\n";
check('returns an array', is_array($items));
check('at most 6 items', count($items) <= 6);
$okShape = true;
foreach ($items as $i) {
    if (!isset($i['label'], $i['count'], $i['severity'], $i['link'])) { $okShape = false; }
    if ((int) ($i['count'] ?? 0) <= 0) { $okShape = false; } // only actionable items
}
check('every item has label/count/severity/link and count>0', $okShape);

// severity ordering: critical before warning before info
$weight = ['critical' => 0, 'warning' => 1, 'info' => 2];
$sorted = true;
for ($k = 1; $k < count($items); $k++) {
    if (($weight[$items[$k - 1]['severity']] ?? 3) > ($weight[$items[$k]['severity']] ?? 3)) { $sorted = false; }
}
check('items ordered by severity (most severe first)', $sorted);
check('links are app paths (start with /)', array_reduce($items, fn ($c, $i) => $c && str_starts_with((string) $i['link'], '/'), true));

echo "\nPASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "Failures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "All assistant checks passed.\n";
exit(0);
