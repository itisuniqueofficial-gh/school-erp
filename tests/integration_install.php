<?php

declare(strict_types=1);

/**
 * Deployment-safety: fresh vs existing install detection + installer protection.
 *
 *   php bin/console migrate:fresh && php bin/console seed
 *   php tests/integration_install.php
 */

use App\Services\InstallService;
use Core\Database\QueryBuilder;
use Database\Seeds\DatabaseSeeder;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

$svc = new InstallService();

// ---------------------------------------------------------------------------
section('Existing installation is detected');
check('isInstalled() is TRUE on a seeded DB', $svc->isInstalled() === true);
check('shouldSeed() is FALSE when installed', $svc->shouldSeed() === false);
$state = $svc->state();
check('state: db reachable', $state['db_reachable'] === true);
check('state: marked installed', $state['installed'] === true);
check('state: users > 0', $state['users'] > 0);
check('state: tables > 0', $state['tables'] > 0);
check('state: migrations applied > 0', $state['migrations_applied'] > 0);
check('state: no pending migrations after migrate', $state['migrations_pending'] === 0);

// ---------------------------------------------------------------------------
section('Installer protection — reseed must NOT duplicate or reset data');
$usersBefore = (int) QueryBuilder::table('users')->count();
$adminBefore = (int) QueryBuilder::table('users')->where('email', 'superadmin@school.test')->count();
$studentsBefore = (int) QueryBuilder::table('students')->count();
// Simulate an accidental reseed on a live DB (the seeder must be idempotent).
(new DatabaseSeeder())->run();
$usersAfter = (int) QueryBuilder::table('users')->count();
$adminAfter = (int) QueryBuilder::table('users')->where('email', 'superadmin@school.test')->count();
$studentsAfter = (int) QueryBuilder::table('students')->count();
check('user count unchanged after reseed', $usersAfter === $usersBefore);
check('exactly ONE super admin (no duplicate account)', $adminAfter === 1 && $adminBefore === 1);
check('student records unchanged', $studentsAfter === $studentsBefore);

// ---------------------------------------------------------------------------
section('Deploy decision honours install state');
// A deploy that respects shouldSeed() would never seed a live DB.
$wouldSeed = $svc->shouldSeed();
check('a redeploy would NOT seed this live DB', $wouldSeed === false);
// And a fresh DB (no users) would be seeded — verify the logic, not by dropping.
check('shouldSeed() == !isInstalled()', $svc->shouldSeed() === !$svc->isInstalled());

echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll install-safety checks passed.\033[0m\n";
exit(0);
