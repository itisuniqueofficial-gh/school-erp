<?php

declare(strict_types=1);

/**
 * DatabasePreflight regression test.
 *
 * Verifies connection failures are classified into stable, specific states and
 * that the user-facing message NEVER leaks credentials, host, SQLSTATE, or the
 * raw driver text. Also verifies the live probe and ping() enrichment.
 */

require_once dirname(__DIR__) . '/bootstrap/app.php';

use Core\Database\Connection;
use Core\Database\DatabasePreflight;

$pass = 0; $fail = 0;
$check = static function (string $l, bool $c) use (&$pass, &$fail): void {
    if ($c) { $pass++; } else { $fail++; echo "FAIL  {$l}\n"; }
};

// --- Classification of driver errors into safe states.
$cases = [
    [new \PDOException('SQLSTATE[HY000] [2002] Connection refused'), DatabasePreflight::UNREACHABLE],
    [new \PDOException('SQLSTATE[HY000] [2003] Can\'t connect to MySQL server on \'db.example\''), DatabasePreflight::UNREACHABLE],
    [new \PDOException("SQLSTATE[28000] [1045] Access denied for user 'erp_local'@'localhost' (using password: YES)"), DatabasePreflight::AUTH_FAILED],
    [new \PDOException("SQLSTATE[HY000] [1049] Unknown database 'school_erp_missing'"), DatabasePreflight::DATABASE_MISSING],
    [new \PDOException("SQLSTATE[HY000] [1044] Access denied for user 'erp'@'%' to database 'prod'"), DatabasePreflight::ACCESS_DENIED],
    [new \RuntimeException('some unexpected failure'), DatabasePreflight::UNKNOWN],
];
foreach ($cases as [$ex, $expected]) {
    [$state, $message] = DatabasePreflight::classify($ex);
    $check("classify -> {$expected}", $state === $expected);
    // Leak-safety: the safe message must not echo credentials/host/SQLSTATE/codes.
    $leaks = ['erp_local', 'using password', 'SQLSTATE', 'Connection refused', 'school_erp_missing', "@'%'", '1045', '1049', '2002'];
    $leaked = false;
    foreach ($leaks as $needle) {
        if (stripos($message, $needle) !== false) { $leaked = true; echo "  leaked '{$needle}' in: {$message}\n"; }
    }
    $check("safe message for {$expected} leaks nothing", !$leaked);
    $check("safe message for {$expected} is non-empty", trim($message) !== '');
}

// --- Live probe against the working test database.
$probe = DatabasePreflight::probe();
$check('live probe succeeds on the test DB', $probe['ok'] === true && $probe['state'] === DatabasePreflight::OK);
$check('probe returns ok/state/message keys', isset($probe['ok'], $probe['state'], $probe['message']));

// --- ping() is enriched with state + safe message and still reports ok.
$ping = Connection::instance()->ping();
$check('ping() ok on test DB', ($ping['ok'] ?? false) === true);
$check('ping() includes a state', isset($ping['state']) && $ping['state'] === DatabasePreflight::OK);
$check('ping() includes a message', isset($ping['message']) && trim((string) $ping['message']) !== '');

// --- Labels are human-readable and code-free.
$check('label(auth_failed) is human text', DatabasePreflight::label(DatabasePreflight::AUTH_FAILED) === 'AUTHENTICATION FAILED');
$check('label(unreachable) is human text', DatabasePreflight::label(DatabasePreflight::UNREACHABLE) === 'SERVER UNREACHABLE');

// --- StartupGuard still uses the classifier (no raw driver text in respond()).
$sg = (string) file_get_contents(dirname(__DIR__) . '/app/Support/StartupGuard.php');
$check('StartupGuard uses DatabasePreflight for the connect page', str_contains($sg, 'DatabasePreflight::probe'));

echo "PASS: {$pass}   FAIL: {$fail}\n";
exit($fail === 0 ? 0 : 1);
