<?php

declare(strict_types=1);

/**
 * Bulk student actions test (activate/deactivate/archive/restore semantics).
 *
 *   php bin/console migrate:fresh && php bin/console seed
 *   php tests/integration_bulk.php
 */

use App\Models\Student;
use Core\Database\QueryBuilder;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

// Seed a few disposable students.
$ids = [];
for ($i = 1; $i <= 3; $i++) {
    $ids[] = (int) Student::create([
        'uid' => (string) (200000000000 + random_int(100000, 999999) + $i),
        'admission_no' => 'BULK-' . uniqid() . $i,
        'first_name' => 'Bulk', 'last_name' => 'Test' . $i,
        'gender' => 'other', 'status' => 'active',
    ]);
}
$now = fn () => date('Y-m-d H:i:s');
$statusOf = fn (int $id) => (string) QueryBuilder::table('students')->where('id', $id)->value('status');
$delOf = fn (int $id) => QueryBuilder::table('students')->where('id', $id)->value('deleted_at');

// ---------------------------------------------------------------------------
section('Bulk deactivate / activate');
$n = QueryBuilder::table('students')->whereIn('id', $ids)->update(['status' => 'inactive', 'updated_at' => $now()]);
check('deactivate updated all selected', $n === count($ids));
check('all statuses are inactive', array_reduce($ids, fn ($c, $id) => $c && $statusOf($id) === 'inactive', true));
QueryBuilder::table('students')->whereIn('id', $ids)->update(['status' => 'active', 'updated_at' => $now()]);
check('activate restores active status', array_reduce($ids, fn ($c, $id) => $c && $statusOf($id) === 'active', true));

// ---------------------------------------------------------------------------
section('Bulk archive (soft-delete) / restore');
$a = QueryBuilder::table('students')->whereIn('id', $ids)->whereNull('deleted_at')->update(['deleted_at' => $now(), 'updated_at' => $now()]);
check('archive soft-deleted all selected', $a === count($ids));
check('archived rows have deleted_at set', array_reduce($ids, fn ($c, $id) => $c && !empty($delOf($id)), true));
check('archived students excluded from active list', (int) QueryBuilder::table('students')->whereIn('id', $ids)->whereNull('deleted_at')->count() === 0);
check('historical rows preserved (not hard-deleted)', (int) QueryBuilder::table('students')->whereIn('id', $ids)->count() === count($ids));
$r = QueryBuilder::table('students')->whereIn('id', $ids)->whereNotNull('deleted_at')->update(['deleted_at' => null, 'updated_at' => $now()]);
check('restore cleared deleted_at for all', $r === count($ids));
check('restored rows are active again in the list', (int) QueryBuilder::table('students')->whereIn('id', $ids)->whereNull('deleted_at')->count() === count($ids));

// ---------------------------------------------------------------------------
section('Idempotency / guards');
check('re-archiving already-archived affects 0 (whereNull guard)', (function () use ($ids, $now) {
    QueryBuilder::table('students')->whereIn('id', $ids)->update(['deleted_at' => $now()]);
    $again = QueryBuilder::table('students')->whereIn('id', $ids)->whereNull('deleted_at')->update(['deleted_at' => $now()]);
    QueryBuilder::table('students')->whereIn('id', $ids)->update(['deleted_at' => null]); // cleanup
    return $again === 0;
})());
check('empty id set changes nothing', (int) QueryBuilder::table('students')->whereIn('id', [0])->update(['status' => 'inactive']) === 0);

// cleanup
QueryBuilder::table('students')->whereIn('id', $ids)->delete();

echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll bulk-action checks passed.\033[0m\n";
exit(0);
