<?php

declare(strict_types=1);

/**
 * Report card ID / versioning / verification test.
 *
 *   php bin/console migrate:fresh && php bin/console seed
 *   php tests/integration_reportcard.php
 */

use App\Models\Student;
use App\Services\ReportCardService;
use Core\Database\Connection;
use Core\Database\QueryBuilder;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

$adminId = (int) QueryBuilder::table('users')->where('email', 'superadmin@school.test')->value('id');
auth()->onceUsingId($adminId);
$svc = new ReportCardService();

// ---------------------------------------------------------------------------
section('Schema');
$cols = array_column(Connection::instance()->select('SHOW COLUMNS FROM report_cards'), 'Field');
foreach (['rc_uid', 'version', 'generated_by', 'reason'] as $col) {
    check("report_cards.$col exists", in_array($col, $cols, true));
}
check('report_card_versions table exists', Connection::instance()->selectOne("SHOW TABLES LIKE 'report_card_versions'") !== null);

// Set up an exam with one graded student.
$yearId = (int) QueryBuilder::table('academic_years')->value('id');
$classId = (int) QueryBuilder::table('classes')->value('id');
$subjectId = (int) QueryBuilder::table('subjects')->value('id');
$examTypeId = (int) QueryBuilder::table('exam_types')->value('id');
$examId = (int) QueryBuilder::table('exams')->insert(['exam_type_id' => $examTypeId, 'academic_year_id' => $yearId, 'uid' => (string) (900000000000 + random_int(1, 899999)), 'name' => 'Final RC ' . uniqid(), 'status' => 'scheduled', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
$schedId = (int) QueryBuilder::table('exam_schedules')->insert(['exam_id' => $examId, 'class_id' => $classId, 'subject_id' => $subjectId, 'exam_date' => date('Y-m-d'), 'full_marks' => 100, 'pass_marks' => 33, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
$sid = Student::create(['uid' => (string) (220000000000 + random_int(1, 899999)), 'admission_no' => 'RC-' . uniqid(), 'first_name' => 'Report', 'last_name' => 'Card', 'gender' => 'other', 'class_id' => $classId, 'academic_year_id' => $yearId, 'status' => 'active']);
QueryBuilder::table('exam_marks')->insert(['exam_schedule_id' => $schedId, 'student_id' => $sid, 'marks_obtained' => 80, 'status' => 'submitted', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);

// ---------------------------------------------------------------------------
section('Generate v1 with a unique RC id');
$rc1 = $svc->generate($examId, $sid);
check('rc_uid matches RC-YYYY-NNNNN', preg_match('/^RC-\d{4}-\d+$/', $rc1) === 1);
$cur = $svc->latest($examId, $sid);
check('current pointer set to v1', (int) $cur['version'] === 1 && $cur['rc_uid'] === $rc1);
check('percentage computed (80%) + pass', (float) $cur['percentage'] == 80.0 && $cur['result'] === 'pass');
check('generator recorded', (int) $cur['generated_by'] === $adminId);
check('history has 1 version', (int) QueryBuilder::table('report_card_versions')->where('exam_id', $examId)->where('student_id', $sid)->count() === 1);

// ---------------------------------------------------------------------------
section('Regenerate preserves history as a new version');
$rc2 = $svc->generate($examId, $sid, 'Correction after re-check');
check('new RC id differs from v1', $rc2 !== $rc1);
$cur2 = $svc->latest($examId, $sid);
check('current pointer advanced to v2', (int) $cur2['version'] === 2 && $cur2['rc_uid'] === $rc2);
check('exactly ONE current row per exam+student', (int) QueryBuilder::table('report_cards')->where('exam_id', $examId)->where('student_id', $sid)->count() === 1);
check('history now has 2 versions (v1 preserved)', (int) QueryBuilder::table('report_card_versions')->where('exam_id', $examId)->where('student_id', $sid)->count() === 2);
check('v1 history row still resolvable by its rc_uid', $svc->verify($rc1) !== null);
check('reason recorded on v2', QueryBuilder::table('report_card_versions')->where('rc_uid', $rc2)->value('reason') === 'Correction after re-check');

// ---------------------------------------------------------------------------
section('ensureForExam is idempotent');
$svc->ensureForExam($examId);
$svc->ensureForExam($examId);
check('no extra versions created by ensureForExam', (int) QueryBuilder::table('report_card_versions')->where('exam_id', $examId)->where('student_id', $sid)->count() === 2);

// ---------------------------------------------------------------------------
section('Public verification exposes only non-sensitive fields');
$v = $svc->verify($rc1);
check('verify returns data for a valid RC id', $v !== null);
check('includes student name + result + academic year', isset($v['student_name'], $v['result'], $v['academic_year']));
check('does NOT expose marks/percentage', !array_key_exists('percentage', $v) && !array_key_exists('obtained_marks', $v) && !array_key_exists('total_marks', $v));
check('verify(unknown) returns null', $svc->verify('RC-0000-999999') === null);

echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll report-card checks passed.\033[0m\n";
exit(0);
