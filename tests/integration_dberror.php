<?php

declare(strict_types=1);

/**
 * Database-failure handling test: the DB-unavailable page must be friendly and
 * must never leak stack traces, file paths, or credentials.
 *
 *   php tests/integration_dberror.php
 */

use Core\Database\DatabaseConnectionException;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

// ---------------------------------------------------------------------------
section('Exception carries a safe public message');
$e = new DatabaseConnectionException('Unable to connect to the database.', 2002, new \PDOException('SQLSTATE[HY000] [2002] Connection refused'));
check('Public message is generic (no SQLSTATE)', strpos($e->getMessage(), 'SQLSTATE') === false);
check('Real driver error preserved as previous (for logging only)', str_contains((string) $e->getPrevious()?->getMessage(), 'SQLSTATE'));

// ---------------------------------------------------------------------------
section('Production page (APP_DEBUG=false: no detail) is credential/path-safe');
$html = view('errors.database', [
    'code' => 503, 'title' => 'Service Unavailable',
    'message' => 'Unable to connect to the database. Please check your database configuration or contact the administrator.',
    'detail' => null,
]);
check('Shows the friendly message', str_contains($html, 'Unable to connect to the database'));
check('Shows the 503 code', str_contains($html, '503'));
check('No SQLSTATE / driver detail leaked', strpos($html, 'SQLSTATE') === false);
check('No absolute file paths leaked', strpos($html, '/home/') === false && strpos($html, dirname(__DIR__)) === false);
check('No DB password leaked', strpos($html, (string) config('database.connections.mysql.password')) === false || (string) config('database.connections.mysql.password') === '');
check('No DB username leaked', strpos($html, 'erp_local') === false);
check('No stack-trace markers', strpos($html, '#0 ') === false && strpos($html, 'Stack trace') === false);

// ---------------------------------------------------------------------------
section('Debug page (APP_DEBUG=true) may include the driver detail only');
$dbg = view('errors.database', [
    'code' => 503, 'title' => 'Service Unavailable', 'message' => 'Unable to connect to the database.',
    'detail' => 'SQLSTATE[HY000] [2002] Connection refused',
]);
check('Debug detail is rendered when provided', str_contains($dbg, 'Connection refused'));
check('Debug detail is clearly labelled as debug-only', str_contains($dbg, 'APP_DEBUG'));

// ---------------------------------------------------------------------------
section('Live connectivity ping (DB is expected up during tests)');
$ping = \Core\Database\Connection::instance()->ping();
check('Connection::ping() returns a structured result', array_key_exists('ok', $ping) && array_key_exists('error', $ping));
check('Ping succeeds against the running database', $ping['ok'] === true);

echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll database-failure-handling checks passed.\033[0m\n";
exit(0);
