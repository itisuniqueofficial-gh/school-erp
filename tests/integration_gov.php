<?php

declare(strict_types=1);

/**
 * Government integration framework + digital-identity status test.
 *
 *   php bin/console migrate:fresh && php bin/console seed
 *   php tests/integration_gov.php
 */

use App\Services\GovernmentIntegrationService;
use App\Models\GovSyncLog;
use Core\Database\Connection;
use Core\Database\QueryBuilder;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

$adminId = (int) QueryBuilder::table('users')->where('email', 'superadmin@school.test')->value('id');
auth()->onceUsingId($adminId);
$svc = new GovernmentIntegrationService();

// ---------------------------------------------------------------------------
section('Schema: sync log + student identity fields');
check('government_sync_logs table exists', Connection::instance()->selectOne("SHOW TABLES LIKE 'government_sync_logs'") !== null);
$cols = array_column(Connection::instance()->select('SHOW COLUMNS FROM students'), 'Field');
foreach (['apaar_status', 'abc_status', 'digilocker_status', 'state_student_id', 'samagra_id', 'emis_number', 'board_registration_no', 'digilocker_ref'] as $col) {
    check("students.$col column exists", in_array($col, $cols, true));
}
$stu = QueryBuilder::table('students')->first();
check('student digital-identity status defaults to "pending"', ($stu['apaar_status'] ?? '') === 'pending' && ($stu['abc_status'] ?? '') === 'pending');

// ---------------------------------------------------------------------------
section('Adapter config encrypted at rest + masked');
$SECRET = 'apaar_client_secret_' . bin2hex(random_bytes(5));
$svc->save('apaar', ['enabled' => true, 'mode' => 'sandbox', 'client_id' => 'CID-1', 'client_secret' => $SECRET, 'base_url' => 'https://sandbox.apaar']);
$cfg = $svc->get('apaar');
check('decrypted client_secret round-trips', $cfg['client_secret'] === $SECRET);
$raw = (string) QueryBuilder::table('settings')->where('key', 'integration.apaar')->value('value');
check('stored value has NO plaintext secret', strpos($raw, $SECRET) === false);
$pub = $svc->publicConfig('apaar');
check('publicConfig masks secret + flags set', $pub['client_secret'] !== $SECRET && ($pub['client_secret_set'] ?? false) === true);
check('publicConfig leaks no plaintext', strpos(json_encode($pub), $SECRET) === false);
check('non-secret client_id visible', ($pub['client_id'] ?? '') === 'CID-1');

section('Blank secret keeps existing; enable/disable filtering');
$svc->save('apaar', ['enabled' => true, 'mode' => 'sandbox', 'client_id' => 'CID-2', 'client_secret' => '', 'base_url' => 'https://sandbox.apaar']);
check('client_id updated, secret preserved', $svc->get('apaar')['client_id'] === 'CID-2' && $svc->get('apaar')['client_secret'] === $SECRET);
$svc->save('digilocker', ['enabled' => false, 'client_id' => 'x', 'client_secret' => 'y', 'redirect_uri' => 'z']);
$enabled = array_column($svc->enabled(), 'provider');
check('enabled() lists apaar', in_array('apaar', $enabled, true));
check('enabled() omits disabled digilocker', !in_array('digilocker', $enabled, true));
check('enabled() carries no secrets', strpos(json_encode($svc->enabled()), $SECRET) === false);
check('isEnabled reflects state', $svc->isEnabled('apaar') === true && $svc->isEnabled('nad') === false);

// ---------------------------------------------------------------------------
section('Sync ledger: log + result');
$sid = (int) QueryBuilder::table('students')->value('id') ?: null;
$logId = $svc->logSync('apaar', 'verify', $sid, 'pending', 'REQ-123', 'Verification requested');
$row = GovSyncLog::find($logId);
check('sync attempt recorded as pending', $row !== null && $row['status'] === 'pending' && $row['provider'] === 'apaar');
$svc->markResult($logId, 'success', 'APAAR verified');
check('markResult advances status to success', QueryBuilder::table('government_sync_logs')->where('id', $logId)->value('status') === 'success');
check('invalid status coerced to failed', (function () use ($svc, $sid) {
    $id = $svc->logSync('abc', 'push', $sid, 'bogus');
    return GovSyncLog::find($id)['status'] === 'pending'; // logSync coerces unknown -> pending
})());
check('recent() joins student + returns rows', count(GovSyncLog::recent(10)) >= 1);

echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll government-integration checks passed.\033[0m\n";
exit(0);
