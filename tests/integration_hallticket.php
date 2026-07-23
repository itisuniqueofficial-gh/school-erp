<?php

declare(strict_types=1);

/**
 * Hall ticket / admit card test.
 *
 *   php bin/console migrate:fresh && php bin/console seed
 *   php tests/integration_hallticket.php
 */

use App\Models\Student;
use App\Services\HallTicketPdfService;
use App\Services\HallTicketService;
use Core\Database\Connection;
use Core\Database\QueryBuilder;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

$adminId = (int) QueryBuilder::table('users')->where('email', 'superadmin@school.test')->value('id');
auth()->onceUsingId($adminId);
$svc = new HallTicketService();

// ---------------------------------------------------------------------------
section('Schema');
$cols = array_column(Connection::instance()->select('SHOW COLUMNS FROM hall_tickets'), 'Field');
foreach (['ht_uid', 'exam_id', 'student_id', 'type', 'attempt_type', 'status', 'issued_on'] as $c) {
    check("hall_tickets.$c exists", in_array($c, $cols, true));
}

// Fixtures: exam + 2 subject schedules for a class + 2 students (A pass, B fail).
$yearId = (int) QueryBuilder::table('academic_years')->value('id');
$classId = (int) QueryBuilder::table('classes')->value('id');
$subs = QueryBuilder::table('subjects')->limit(2)->get();
$et = (int) QueryBuilder::table('exam_types')->value('id');
$examId = (int) QueryBuilder::table('exams')->insert(['exam_type_id' => $et, 'academic_year_id' => $yearId, 'uid' => (string) (900000000000 + random_int(1, 899999)), 'name' => 'HT Exam ' . uniqid(), 'status' => 'scheduled', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
foreach ($subs as $sub) {
    QueryBuilder::table('exam_schedules')->insert(['exam_id' => $examId, 'class_id' => $classId, 'subject_id' => (int) $sub['id'], 'exam_date' => date('Y-m-d'), 'start_time' => '10:00:00', 'end_time' => '13:00:00', 'full_marks' => 100, 'pass_marks' => 33, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
}
$sidA = Student::create(['uid' => (string) (220000000000 + random_int(1, 899999)), 'admission_no' => 'HT-A-' . uniqid(), 'first_name' => 'Hallie', 'last_name' => 'Pass', 'gender' => 'female', 'class_id' => $classId, 'academic_year_id' => $yearId, 'status' => 'active']);
$sidB = Student::create(['uid' => (string) (220000000000 + random_int(1, 899999)), 'admission_no' => 'HT-B-' . uniqid(), 'first_name' => 'Barry', 'last_name' => 'Fail', 'gender' => 'male', 'class_id' => $classId, 'academic_year_id' => $yearId, 'status' => 'active']);
QueryBuilder::table('report_cards')->insert(['exam_id' => $examId, 'student_id' => $sidA, 'result' => 'pass', 'version' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
QueryBuilder::table('report_cards')->insert(['exam_id' => $examId, 'student_id' => $sidB, 'result' => 'fail', 'version' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);

// ---------------------------------------------------------------------------
section('Eligibility (regular vs back-paper)');
$regular = $svc->eligibleStudents($examId, $classId, null, 'hall_ticket');
$regIds = array_column($regular, 'id');
check('both class students eligible for regular hall ticket', in_array($sidA, $regIds, true) && in_array($sidB, $regIds, true));
$backs = $svc->eligibleStudents($examId, $classId, null, 'back_paper');
$backIds = array_column($backs, 'id');
check('only the FAILED student is eligible for back paper', in_array($sidB, $backIds, true) && !in_array($sidA, $backIds, true));
check('exam not covering a class => no eligibility', $svc->eligibleStudents($examId, 999999, null, 'hall_ticket') === []);

// ---------------------------------------------------------------------------
section('Issue + idempotency + duplicate prevention');
$h1 = $svc->issue($examId, $sidA, 'hall_ticket');
check('issued with HT-YYYY-NNNNN id', preg_match('/^HT-\d{4}-\d+$/', (string) $h1) === 1);
check('re-issue same exam+student+type => same id (idempotent)', $svc->issue($examId, $sidA, 'hall_ticket') === $h1);
check('exactly ONE row for exam+student+type', (int) QueryBuilder::table('hall_tickets')->where('exam_id', $examId)->where('student_id', $sidA)->where('type', 'hall_ticket')->count() === 1);
check('back paper for a PASSED student is refused', $svc->issue($examId, $sidA, 'back_paper') === null);
check('back paper for a FAILED student is allowed', preg_match('/^HT-/', (string) $svc->issue($examId, $sidB, 'back_paper')) === 1);
check('invalid type refused', $svc->issue($examId, $sidA, 'nonsense') === null);

// ---------------------------------------------------------------------------
section('Bulk issue is duplicate-safe');
$r1 = $svc->issueForClass($examId, $classId, null, 'hall_ticket');
check('bulk issued the remaining eligible (incl. B)', $r1['created'] >= 1 && QueryBuilder::table('hall_tickets')->where('exam_id', $examId)->where('student_id', $sidB)->where('type', 'hall_ticket')->count() === 1);
check('bulk skipped the already-issued A', $r1['skipped'] >= 1);
$r2 = $svc->issueForClass($examId, $classId, null, 'hall_ticket');
check('re-run bulk creates nothing (no duplicates)', $r2['created'] === 0);
check('total hall_ticket rows == eligible class size', (int) QueryBuilder::table('hall_tickets')->where('exam_id', $examId)->where('type', 'hall_ticket')->count() === count($regular));

// ---------------------------------------------------------------------------
section('Document + PDF + verification');
$doc = $svc->document($h1);
check('document has exam schedule subjects', count($doc['subjects']) === 2);
check('verify_url contains ht id', str_contains((string) $doc['verify_url'], (string) $h1));
$pdf = (new HallTicketPdfService())->render($doc);
check('PDF is real (%PDF-) + EOF', str_starts_with($pdf, '%PDF-') && str_contains($pdf, '%%EOF'));
check('PDF is a single page', preg_match_all('#/Type\s*/Page[^s]#', $pdf) === 1);
$v = $svc->verify($h1);
check('verify returns valid non-sensitive data', $v !== null && $v['valid'] === true && isset($v['student_name']));
check('verify exposes NO marks/percentage', !array_key_exists('percentage', $v) && !array_key_exists('marks', $v));
check('verify(unknown) => null', $svc->verify('HT-0000-999999') === null);

// ---------------------------------------------------------------------------
section('Revoke + ownership isolation');
check('revoke succeeds', $svc->revoke($h1, 'Issued in error') === true);
check('status now revoked', $svc->find($h1)['status'] === 'revoked');
check('verify reports invalid after revoke', $svc->verify($h1)['valid'] === false);
$mismatch = QueryBuilder::table('hall_tickets')->where('ht_uid', $h1)->where('student_id', $sidB)->first();
$match = QueryBuilder::table('hall_tickets')->where('ht_uid', $h1)->where('student_id', $sidA)->first();
check('another student cannot resolve the ticket', $mismatch === null);
check('owner can resolve the ticket', $match !== null);

echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll hall-ticket checks passed.\033[0m\n";
exit(0);
