<?php

declare(strict_types=1);

/**
 * Startup guard test: the data-safety gate that refuses to serve on an
 * unavailable or unmigrated database. Classification logic is unit-tested via
 * evaluate() (no real down DB needed); check() is verified against the live
 * migrated test database.
 */

require __DIR__ . '/../bootstrap/app.php';

use App\Support\StartupGuard;

$pass = 0;
$fail = 0;
$check = static function (string $label, bool $cond) use (&$pass, &$fail): void {
    if ($cond) {
        $pass++;
    } else {
        $fail++;
        echo "FAIL  {$label}\n";
    }
};

// Healthy probe -> null.
$check('healthy probe returns null', StartupGuard::evaluate(static fn () => true) === null);

// Missing-table errors -> 'unmigrated' (cover MySQL + SQLite phrasings + code).
$check('mysql missing table -> unmigrated', StartupGuard::evaluate(static function () {
    throw new \RuntimeException("SQLSTATE[42S02]: Base table or view not found: 1146 Table 'school_erp.users' doesn't exist");
}) === 'unmigrated');
$check('sqlite missing table -> unmigrated', StartupGuard::evaluate(static function () {
    throw new \RuntimeException('SQLSTATE[HY000]: General error: 1 no such table: users');
}) === 'unmigrated');

// Connection-level failures -> 'connect'.
$check('connection refused -> connect', StartupGuard::evaluate(static function () {
    throw new \RuntimeException("SQLSTATE[HY000] [2002] Connection refused");
}) === 'connect');
$check('unknown database -> connect', StartupGuard::evaluate(static function () {
    throw new \RuntimeException("SQLSTATE[HY000] [1049] Unknown database 'school_erp'");
}) === 'connect');
$check('access denied -> connect', StartupGuard::evaluate(static function () {
    throw new \RuntimeException("SQLSTATE[HY000] [1045] Access denied for user");
}) === 'connect');

// Live check against the migrated test DB must pass (schema present).
$check('live migrated DB passes check()', StartupGuard::check() === null);

// respond() emits a self-contained page with a reference id and no fatals.
ob_start();
StartupGuard::respond('connect');
$html = (string) ob_get_clean();
$check('respond outputs html', str_contains($html, '<!DOCTYPE html>'));
$check('respond includes a reference id', (bool) preg_match('/Reference:\s*<code>[0-9a-f]{6,}<\/code>/', $html));
$check('respond has how-to-fix guidance', stripos($html, 'How to fix') !== false);
$check('respond leaks no stack trace', stripos($html, 'Stack trace') === false);

ob_start();
StartupGuard::respond('unmigrated');
$html2 = (string) ob_get_clean();
$check('unmigrated page mentions migrate', stripos($html2, 'migrate') !== false);

echo "PASS: {$pass}   FAIL: {$fail}\n";
exit($fail === 0 ? 0 : 1);
