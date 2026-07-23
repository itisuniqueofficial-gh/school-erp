<?php

declare(strict_types=1);

/**
 * Migration snapshot / zero-data-loss verification test.
 *
 *   APP_TESTING=1 php bin/console migrate:fresh && APP_TESTING=1 php bin/console seed
 *   APP_TESTING=1 php tests/integration_snapshot.php
 */

use App\Models\Student;
use App\Services\MigrationSnapshotService;
use Core\Database\QueryBuilder;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

$svc = new MigrationSnapshotService();
// Ensure at least one student exists so counts are meaningful.
$yearId = (int) QueryBuilder::table('academic_years')->value('id');
$classId = (int) QueryBuilder::table('classes')->value('id');
Student::create(['uid' => (string) (220000000000 + random_int(1, 899999)), 'admission_no' => 'SNAP-' . uniqid(), 'first_name' => 'Snap', 'last_name' => 'Shot', 'gender' => 'other', 'class_id' => $classId, 'academic_year_id' => $yearId, 'status' => 'active']);

// ---------------------------------------------------------------------------
section('Snapshot capture');
$snap = $svc->snapshot();
check('has counts for key tables', isset($snap['counts']['users'], $snap['counts']['students']));
check('users count > 0', (int) $snap['counts']['users'] > 0);
check('students count > 0', (int) $snap['counts']['students'] > 0);
check('total_records aggregated', (int) $snap['total_records'] > 0);
check('records driver + database identity', $snap['driver'] !== '' && $snap['database'] !== '');
check('checksum present', is_string($snap['checksum']) && strlen($snap['checksum']) === 64);

// ---------------------------------------------------------------------------
section('Comparison: identical data is SAFE');
$r = $svc->compareToCurrent($snap);
check('same snapshot vs current => safe', $r['safe'] === true);
check('no dropped tables', $r['dropped'] === []);

// ---------------------------------------------------------------------------
section('Comparison: DETECTS data loss');
$loss = $snap;
$loss['counts']['students'] = (int) $loss['counts']['students'] + 5; // source had 5 more
$rl = $svc->compareToCurrent($loss);
check('data loss flagged unsafe', $rl['safe'] === false);
check('dropped list names the table', in_array('students', array_column($rl['dropped'], 'table'), true));

// ---------------------------------------------------------------------------
section('Comparison: growth is SAFE, missing table is UNSAFE');
$grew = $snap;
$grew['counts']['students'] = max(0, (int) $grew['counts']['students'] - 1); // source had fewer
$rg = $svc->compareToCurrent($grew);
check('growth (more records now) is safe', $rg['safe'] === true && in_array('students', array_column($rg['added'], 'table'), true));
$miss = $snap;
$miss['counts']['ghost_table_xyz'] = 3; // source had a table this DB lacks
$rm = $svc->compareToCurrent($miss);
check('missing table flagged unsafe', $rm['safe'] === false && in_array('ghost_table_xyz', $rm['missing'], true));

// ---------------------------------------------------------------------------
section('Persist + reload');
$file = $svc->save();
$loaded = $svc->load($file);
check('snapshot saved to disk', is_file($file));
check('reloaded snapshot has counts', $loaded !== null && isset($loaded['counts']['users']));
@unlink($file);

echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll migration-snapshot checks passed.\033[0m\n";
exit(0);
