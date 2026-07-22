<?php

declare(strict_types=1);

/**
 * Setup Center integrity checks test (incl. new approval-queue checks).
 *
 *   php bin/console migrate:fresh && php bin/console seed
 *   php tests/integration_integrity.php
 */

use App\Services\IntegrityService;
use Core\Database\QueryBuilder;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

$svc = new IntegrityService();

// ---------------------------------------------------------------------------
section('Checks run without error and have the correct shape');
$checks = $svc->checks();
check('checks() returns a non-empty list', is_array($checks) && count($checks) >= 12);
$shapeOk = true;
foreach ($checks as $c) {
    foreach (['key', 'label', 'count', 'severity', 'link'] as $k) {
        if (!array_key_exists($k, $c)) { $shapeOk = false; }
    }
    if (!is_int($c['count']) || !in_array($c['severity'], ['critical', 'warning', 'healthy'], true)) { $shapeOk = false; }
}
check('every check has key/label/count(int)/severity/link', $shapeOk);
$keys = array_column($checks, 'key');
check('status() returns a valid overall status', in_array($svc->status(), ['critical', 'warning', 'healthy'], true));

// ---------------------------------------------------------------------------
section('New approval-queue checks exist');
check('documents_pending_verification check present', in_array('documents_pending_verification', $keys, true));
check('attendance_unlock_pending check present', in_array('attendance_unlock_pending', $keys, true));

$byKey = [];
foreach ($checks as $c) { $byKey[$c['key']] = $c; }
check('document check links to students', str_contains($byKey['documents_pending_verification']['link'], '/admin/students'));
check('unlock check links to attendance', str_contains($byKey['attendance_unlock_pending']['link'], '/admin/attendance'));

// ---------------------------------------------------------------------------
section('Counts reflect real data');
$unlockBefore = $byKey['attendance_unlock_pending']['count'];
$yearId = (int) QueryBuilder::table('academic_years')->where('is_current', 1)->value('id');
$classId = (int) QueryBuilder::table('classes')->value('id');
$sectionId = (int) (QueryBuilder::table('sections')->value('id') ?? 0);
$adminId = (int) QueryBuilder::table('users')->where('email', 'superadmin@school.test')->value('id');
QueryBuilder::table('attendance_unlock_requests')->insert([
    'academic_year_id' => $yearId, 'class_id' => $classId, 'section_id' => $sectionId, 'date' => date('Y-m-d'),
    'requested_by' => $adminId, 'reason' => 'test', 'status' => 'pending',
    'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
]);
$after = array_column((new IntegrityService())->checks(), 'count', 'key')['attendance_unlock_pending'];
check('pending unlock count increments after inserting a pending request', $after === $unlockBefore + 1);

// Approving it removes it from the queue.
QueryBuilder::table('attendance_unlock_requests')->where('status', 'pending')->update(['status' => 'approved']);
$after2 = array_column((new IntegrityService())->checks(), 'count', 'key')['attendance_unlock_pending'];
check('count returns to baseline after approval', $after2 === $unlockBefore);

echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll integrity-check tests passed.\033[0m\n";
exit(0);
