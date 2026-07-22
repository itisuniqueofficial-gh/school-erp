<?php

declare(strict_types=1);

/**
 * Login-by-12-digit-User-ID resolution test.
 *
 *   php bin/console migrate:fresh && php bin/console seed
 *   php tests/integration_login.php
 */

use App\Models\User;
use Core\Database\QueryBuilder;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

$admin = QueryBuilder::table('users')->where('email', 'superadmin@school.test')->first();
$uid = (string) ($admin['uid'] ?? '');

// ---------------------------------------------------------------------------
section('12-digit User ID');
check('Admin has a 12-digit numeric uid', preg_match('/^\d{12}$/', $uid) === 1);
check('uid column has a UNIQUE index', (function () {
    foreach (\Core\Database\Connection::instance()->select('SHOW INDEX FROM users') as $ix) {
        if (($ix['Column_name'] ?? '') === 'uid' && (int) ($ix['Non_unique'] ?? 1) === 0) { return true; }
    }
    return false;
})());

// ---------------------------------------------------------------------------
section('Resolution accepts User ID / email / username');
$byUid = User::findByLogin($uid);
check('findByLogin(uid) resolves the user', $byUid !== null && (int) $byUid['id'] === (int) $admin['id']);
check('findByLogin(email) resolves the same user', (int) (User::findByLogin('superadmin@school.test')['id'] ?? 0) === (int) $admin['id']);
$uname = (string) ($admin['username'] ?? '');
if ($uname !== '') {
    check('findByLogin(username) resolves the same user', (int) (User::findByLogin($uname)['id'] ?? 0) === (int) $admin['id']);
} else {
    check('(admin has no username — skipped)', true);
}
check('findByLogin(unknown) returns null', User::findByLogin('100000000000-nope') === null);

// ---------------------------------------------------------------------------
section('Soft-deleted users cannot resolve');
$tmpId = User::create([
    'name' => 'Temp Login', 'uid' => '109900990099', 'email' => 'tmp.login@school.test',
    'username' => 'tmplogin' . random_int(100, 999), 'password' => \Core\Security\Hash::make('x'), 'is_active' => 1,
]);
check('New user resolves by uid before deletion', User::findByLogin('109900990099') !== null);
QueryBuilder::table('users')->where('id', $tmpId)->update(['deleted_at' => date('Y-m-d H:i:s')]);
check('Soft-deleted user no longer resolves by uid', User::findByLogin('109900990099') === null);
QueryBuilder::table('users')->where('id', $tmpId)->delete();

echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll User-ID login checks passed.\033[0m\n";
exit(0);
