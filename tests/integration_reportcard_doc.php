<?php

declare(strict_types=1);

/**
 * Report card DOCUMENT + PDF + authorization test.
 *
 *   php bin/console migrate:fresh && php bin/console seed
 *   php tests/integration_reportcard_doc.php
 */

use App\Models\Student;
use App\Services\ReportCardDocumentService;
use App\Services\ReportCardPdfService;
use App\Services\ReportCardService;
use Core\Database\QueryBuilder;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

$adminId = (int) QueryBuilder::table('users')->where('email', 'superadmin@school.test')->value('id');
auth()->onceUsingId($adminId);

// ---- Fixtures: exam + 2 subjects + 2 students with marks --------------------
$yearId = (int) QueryBuilder::table('academic_years')->value('id');
$classId = (int) QueryBuilder::table('classes')->value('id');
$subs = QueryBuilder::table('subjects')->limit(2)->get();
$examTypeId = (int) QueryBuilder::table('exam_types')->value('id');
$examId = (int) QueryBuilder::table('exams')->insert(['exam_type_id' => $examTypeId, 'academic_year_id' => $yearId, 'uid' => (string) (900000000000 + random_int(1, 899999)), 'name' => 'Final Doc ' . uniqid(), 'status' => 'scheduled', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
$sched = [];
foreach ($subs as $sub) {
    $sched[] = (int) QueryBuilder::table('exam_schedules')->insert(['exam_id' => $examId, 'class_id' => $classId, 'subject_id' => (int) $sub['id'], 'exam_date' => date('Y-m-d'), 'full_marks' => 100, 'pass_marks' => 33, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
}
$mk = function (int $sid, array $obt): void { global $sched; foreach ($sched as $i => $scId) { QueryBuilder::table('exam_marks')->insert(['exam_schedule_id' => $scId, 'student_id' => $sid, 'marks_obtained' => $obt[$i], 'status' => 'submitted', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]); } };

$sidA = Student::create(['uid' => (string) (220000000000 + random_int(1, 899999)), 'admission_no' => 'DA-' . uniqid(), 'first_name' => 'Alice', 'last_name' => 'Top', 'gender' => 'female', 'class_id' => $classId, 'academic_year_id' => $yearId, 'status' => 'active']);
$sidB = Student::create(['uid' => (string) (220000000000 + random_int(1, 899999)), 'admission_no' => 'DB-' . uniqid(), 'first_name' => 'Bob', 'last_name' => 'Low', 'gender' => 'male', 'class_id' => $classId, 'academic_year_id' => $yearId, 'status' => 'active']);
$mk($sidA, [80, 90]);   // 170/200 = 85% PASS
$mk($sidB, [20, 30]);   // 50/200 = fail subjects

// Guardian for Alice (father) + student_guardian link.
$gid = (int) QueryBuilder::table('guardians')->insert(['name' => 'Mr Top', 'relation' => 'father', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
QueryBuilder::table('student_guardians')->insert(['student_id' => $sidA, 'guardian_id' => $gid, 'is_primary' => 1]);

$svc = new ReportCardDocumentService();

// ---------------------------------------------------------------------------
section('Document assembly (real DB data, server-side calc)');
$doc = $svc->build($examId, $sidA);
check('build() returns a document', $doc !== null);
check('school branding pulled from settings', $doc['branding']['name'] === 'Greenfield International School');
check('exactly 2 subject rows (dynamic)', count($doc['subjects']) === 2);
check('grand max = 200', (float) $doc['totals']['max'] === 200.0);
check('grand obtained = 170', (float) $doc['totals']['obtained'] === 170.0);
check('percentage = 85', (float) $doc['totals']['percentage'] === 85.0);
check('result PASSED', $doc['totals']['result'] === 'PASSED');
check('division = First Division', $doc['totals']['division'] === 'First Division');
check('GPA computed (> 0)', (float) $doc['totals']['gpa'] > 0);
check('father name resolved from guardians', $doc['student']['father'] === 'Mr Top');
check('RC id present + verify url', preg_match('/^RC-\d{4}-\d+$/', (string) $doc['rc_uid']) === 1 && str_contains((string) $doc['verify_url'], (string) $doc['rc_uid']));
check('attendance summary present (year-scoped keys)', array_key_exists('present', $doc['attendance']) && array_key_exists('attendancePercentage', $doc['attendance']));

$docB = $svc->build($examId, $sidB);
check('failing student => result FAILED', $docB['totals']['result'] === 'FAILED');

// ---------------------------------------------------------------------------
section('Real one-click PDF bytes');
$pdf = (new ReportCardPdfService())->render($doc);
check('output is a real PDF (starts with %PDF-)', str_starts_with($pdf, '%PDF-'));
check('PDF is non-trivial in size', strlen($pdf) > 1500);
check('PDF trailer present (%%EOF)', str_contains($pdf, '%%EOF'));

// ---------------------------------------------------------------------------
section('Authorization isolation (the query the portals rely on)');
$rcA = (string) $doc['rc_uid'];
// Student ownership: A's card constrained to B's id must NOT resolve.
$asB = QueryBuilder::table('report_cards')->where('rc_uid', $rcA)->where('student_id', $sidB)->first();
$asA = QueryBuilder::table('report_cards')->where('rc_uid', $rcA)->where('student_id', $sidA)->first();
check('student CANNOT resolve another student\'s card', $asB === null);
check('student CAN resolve own card', $asA !== null);
// Parent linkage: A's card only resolves within the set of linked child ids.
$linkedToA = QueryBuilder::table('report_cards')->where('rc_uid', $rcA)->whereIn('student_id', [$sidA])->first();
$linkedToBonly = QueryBuilder::table('report_cards')->where('rc_uid', $rcA)->whereIn('student_id', [$sidB])->first();
check('parent with child A sees A\'s card', $linkedToA !== null);
check('parent WITHOUT child A cannot see A\'s card', $linkedToBonly === null);

// ---------------------------------------------------------------------------
section('Public verify remains non-sensitive (regression)');
$v = (new ReportCardService())->verify($rcA);
check('verify returns data', $v !== null);
check('verify exposes NO marks/percentage', !array_key_exists('percentage', $v) && !array_key_exists('obtained_marks', $v) && !array_key_exists('total_marks', $v));
check('verify exposes NO parent info', !array_key_exists('father', $v) && !array_key_exists('mother', $v));

echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll report-card document checks passed.\033[0m\n";
exit(0);
