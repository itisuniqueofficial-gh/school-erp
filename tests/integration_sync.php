<?php

declare(strict_types=1);

/**
 * Government sync: external mapping + duplicate-safe import engine test.
 *
 *   php bin/console migrate:fresh && php bin/console seed
 *   php tests/integration_sync.php
 */

use App\Services\ExternalMappingService;
use App\Services\GovernmentIntegrationService;
use App\Services\SyncJobService;
use Core\Database\Connection;
use Core\Database\QueryBuilder;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

$adminId = (int) QueryBuilder::table('users')->where('email', 'superadmin@school.test')->value('id');
auth()->onceUsingId($adminId);

// ---------------------------------------------------------------------------
section('Schema');
foreach (['external_mappings', 'sync_jobs'] as $t) {
    check("table $t exists", Connection::instance()->selectOne("SHOW TABLES LIKE '$t'") !== null);
}

$map = new ExternalMappingService();

// ---------------------------------------------------------------------------
section('External ID mapping is idempotent + conflict-aware');
check('first link => created', $map->link('udise', 'student', 101, 'UD-STU-1') === 'created');
check('same link again => exists (no duplicate)', $map->link('udise', 'student', 101, 'UD-STU-1') === 'exists');
check('same entity, new external => updated', $map->link('udise', 'student', 101, 'UD-STU-1B') === 'updated');
check('external id claimed by another entity => conflict (refused)', $map->link('udise', 'student', 102, 'UD-STU-1B') === 'conflict');
check('internalId resolves', $map->internalId('udise', 'student', 'UD-STU-1B') === 101);
check('externalId resolves', $map->externalId('udise', 'student', 101) === 'UD-STU-1B');
check('exactly ONE mapping row for entity 101', (int) QueryBuilder::table('external_mappings')->where('provider', 'udise')->where('entity_type', 'student')->where('entity_id', 101)->count() === 1);

// ---------------------------------------------------------------------------
section('Duplicate-safe batch import (re-sync never duplicates)');
$store = []; // simulated local table keyed by internal id
$seq = 0;
$upsert = function (array $rec, ?int $internalId) use (&$store, &$seq): int {
    if ($internalId === null) { $internalId = ++$seq; }
    $store[$internalId] = $rec['name'];
    return $internalId;
};
$resolveExt = fn (array $rec): string => (string) $rec['ext'];
$batch = [
    ['ext' => 'EMIS-1', 'name' => 'Aaa'],
    ['ext' => 'EMIS-2', 'name' => 'Bbb'],
    ['ext' => 'EMIS-3', 'name' => 'Ccc'],
    ['ext' => '',       'name' => 'NoExt'], // skipped
];
$svc = new SyncJobService();
$r1 = $svc->importBatch('emis', 'student', 'syncStudents', $batch, $resolveExt, $upsert);
check('first run: 3 created', $r1['created'] === 3);
check('first run: 1 skipped (no external id)', $r1['skipped'] === 1);
check('first run status completed', $r1['status'] === 'completed');
check('local store has 3 records', count($store) === 3);

// Re-run the SAME batch — must update in place, create nothing.
$r2 = $svc->importBatch('emis', 'student', 'syncStudents', $batch, $resolveExt, $upsert);
check('re-run: 0 created (no duplicates)', $r2['created'] === 0);
check('re-run: 3 updated', $r2['updated'] === 3);
check('local store STILL has 3 records (no duplication)', count($store) === 3);
check('mapping count stayed at 3', $map->count('emis', 'student') === 3);

// Changed data on re-sync updates the mapped record.
$batch[0]['name'] = 'Aaa-Updated';
$svc->importBatch('emis', 'student', 'syncStudents', $batch, $resolveExt, $upsert);
check('updated value reflected in local store', in_array('Aaa-Updated', $store, true));

// ---------------------------------------------------------------------------
section('Sync job tracking + dashboard');
$jobs = $svc->recent(10, 'emis');
check('sync jobs recorded', count($jobs) >= 3);
$dash = $svc->dashboard();
check('dashboard counts jobs', $dash['total_jobs'] >= 3 && $dash['completed'] >= 3);
check('dashboard sums created records', $dash['records_created'] >= 3);

// ---------------------------------------------------------------------------
section('Provider operation-direction gating');
$gov = new GovernmentIntegrationService();
check('digilocker is pull-only (no push)', $gov->supportsPull('digilocker') && !$gov->supportsPush('digilocker'));
check('nad is push-only (no pull)', $gov->supportsPush('nad') && !$gov->supportsPull('nad'));
check('udise is bidirectional', $gov->supportsPull('udise') && $gov->supportsPush('udise'));

echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll government-sync checks passed.\033[0m\n";
exit(0);
