<?php

declare(strict_types=1);

/**
 * Zero-data-loss / safe-deploy test.
 *
 *   php bin/console migrate:fresh && php bin/console seed
 *   php tests/integration_safedeploy.php
 */

use Core\Database\QueryBuilder;
use Database\Migrator;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

// ---------------------------------------------------------------------------
section('Migrations are additive (no destructive SQL)');
$dir = dirname(__DIR__) . '/database/migrations';
$destructive = [];
foreach (glob($dir . '/*.sql') as $f) {
    $sql = strtoupper((string) file_get_contents($f));
    // DROP INDEX is a safe (non-data) operation; DROP TABLE/TRUNCATE/DELETE are not.
    if (preg_match('/\b(DROP\s+TABLE|TRUNCATE|DELETE\s+FROM|DROP\s+DATABASE)\b/', $sql)) {
        $destructive[] = basename($f);
    }
}
check('No migration drops/truncates/deletes data', $destructive === [] ? true : (print_r($destructive, true) && false));

// ---------------------------------------------------------------------------
section('Re-running migrations is a pending-only no-op (data preserved)');
$usersBefore   = (int) QueryBuilder::table('users')->count();
$studentsBefore = (int) QueryBuilder::table('students')->count();
$adminHash = (string) QueryBuilder::table('users')->where('email', 'superadmin@school.test')->value('password');
$adminUid  = (string) QueryBuilder::table('users')->where('email', 'superadmin@school.test')->value('uid');

$ran = (new Migrator())->migrate(); // everything already applied by fresh+seed
check('migrate() applies ZERO new migrations when up to date', $ran === []);

$usersAfter    = (int) QueryBuilder::table('users')->count();
$studentsAfter = (int) QueryBuilder::table('students')->count();
check('user count unchanged', $usersAfter === $usersBefore && $usersBefore > 0);
check('student count unchanged', $studentsAfter === $studentsBefore);
check('admin password hash unchanged (passwords remain valid)', (string) QueryBuilder::table('users')->where('email', 'superadmin@school.test')->value('password') === $adminHash);
check('admin 12-digit uid NOT regenerated', (string) QueryBuilder::table('users')->where('email', 'superadmin@school.test')->value('uid') === $adminUid);

// ---------------------------------------------------------------------------
section('Migration ledger tracks applied migrations (upgrade detects version)');
$status = (new Migrator())->status();
check('applied list is non-empty', count($status['applied']) > 0);
check('no pending migrations after a fresh install', $status['pending'] === []);
check('every migration file is recorded as applied', count($status['applied']) === count(glob($dir . '/*.sql')));

// ---------------------------------------------------------------------------
section('Idempotent unique data — no duplicates on re-seed guards');
// The seeders guard against duplicates (INSERT ... only when count()==0). Verify
// there is exactly one current academic year and unique user uids.
$currentYears = (int) QueryBuilder::table('academic_years')->where('is_current', 1)->count();
check('exactly one current academic year', $currentYears === 1);
$dupUids = QueryBuilder::table('users')->selectRaw('uid, COUNT(*) c')->groupBy('uid')->get();
$hasDup = false;
foreach ($dupUids as $r) { if ((int) $r['c'] > 1) { $hasDup = true; } }
check('no duplicate user uids', $hasDup === false);

echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll safe-deploy checks passed.\033[0m\n";
exit(0);
