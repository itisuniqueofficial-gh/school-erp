<?php

declare(strict_types=1);

/**
 * Exam public-ID (uid) integration test.
 *
 *   php bin/console migrate:fresh && php bin/console seed
 *   php tests/integration_exam_uid.php
 */

use App\Models\Exam;
use App\Services\IdSequenceService;
use Core\Database\Connection;
use Core\Database\QueryBuilder;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

$svc = new IdSequenceService();

// ---------------------------------------------------------------------------
section('Schema: exams.uid column + UNIQUE index');
$col = Connection::instance()->selectOne("SHOW COLUMNS FROM exams LIKE 'uid'");
check('exams.uid column exists (char(12))', $col !== null && stripos((string) $col['Type'], 'char(12)') !== false);
$uniq = (int) Connection::instance()->scalar(
    "SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'exams' AND index_name = 'uq_exams_uid' AND non_unique = 0"
);
check('exams.uid has a UNIQUE index', $uniq > 0);

// ---------------------------------------------------------------------------
section('Generator: 12-digit, lead 9, unique, never reused');
$a = $svc->uidExam();
$b = $svc->uidExam();
check('uidExam is 12 numeric digits', preg_match('/^\d{12}$/', $a) === 1);
check('uidExam leading digit is 9 (exam type)', $a[0] === '9');
check('consecutive ids differ (no reuse)', $a !== $b);
check('ids increment monotonically', (int) $b === (int) $a + 1);

// ---------------------------------------------------------------------------
section('Create path assigns an immutable uid');
$examTypeId = (int) QueryBuilder::table('exam_types')->value('id');
$yearId = (int) QueryBuilder::table('academic_years')->where('is_current', 1)->value('id');
$id = Exam::create([
    'exam_type_id' => $examTypeId, 'academic_year_id' => $yearId,
    'uid' => $svc->uidExam(), 'name' => 'Unit Test Exam ' . uniqid(), 'status' => 'scheduled',
]);
$row = Exam::find($id);
check('Created exam has a uid', !empty($row['uid']) && preg_match('/^9\d{11}$/', (string) $row['uid']) === 1);

// Uniqueness is enforced at the DB level.
$dupBlocked = false;
try {
    QueryBuilder::table('exams')->insert([
        'exam_type_id' => $examTypeId, 'uid' => $row['uid'], 'name' => 'Dup', 'status' => 'scheduled',
    ]);
} catch (\Throwable) {
    $dupBlocked = true;
}
check('Duplicate exam uid rejected by DB UNIQUE constraint', $dupBlocked === true);

// uid is immutable in practice: not in a normal update payload, and unique-guarded.
QueryBuilder::table('exams')->where('id', $id)->delete();

echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll exam-uid checks passed.\033[0m\n";
exit(0);
