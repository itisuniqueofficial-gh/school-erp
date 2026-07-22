<?php

declare(strict_types=1);

/**
 * Automated promotion + rollover test.
 *
 *   php bin/console migrate:fresh && php bin/console seed
 *   php tests/integration_promotion.php
 */

use App\Models\Student;
use App\Services\EnrollmentService;
use App\Services\PromotionService;
use Core\Database\QueryBuilder;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

$adminId = (int) QueryBuilder::table('users')->where('email', 'superadmin@school.test')->value('id');
auth()->onceUsingId($adminId);

$svc = new PromotionService();
$enroll = new EnrollmentService();

// Two academic years (from = current, to = another).
$fromYear = (int) QueryBuilder::table('academic_years')->where('is_current', 1)->value('id');
$toYear = (int) QueryBuilder::table('academic_years')->whereRaw('`id` != ?', [$fromYear])->value('id');
if ($toYear === 0) {
    $toYear = (int) QueryBuilder::table('academic_years')->insert(['name' => '2099-00', 'start_date' => '2099-04-01', 'end_date' => '2100-03-31', 'is_current' => 0, 'status' => 'active', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
}

// Two consecutive classes by numeric level.
$classes = QueryBuilder::table('classes')->where('status', 'active')->orderBy('numeric_level')->get();
$c1 = (int) $classes[0]['id'];
$c2 = (int) $classes[1]['id'];
$topClass = (int) end($classes)['id'];

// ---------------------------------------------------------------------------
section('Next-class mapping (numeric level)');
check('class 1 maps to the next class up', $svc->nextClassId($c1) === $c2);
check('top class has no next class (graduates)', $svc->nextClassId($topClass) === null);

// ---------------------------------------------------------------------------
section('Evaluate without an exam (all active eligible)');
$sA = Student::create(['uid' => (string) (210000000000 + random_int(1, 899999)), 'admission_no' => 'PRA-' . uniqid(), 'first_name' => 'Promo', 'last_name' => 'A', 'gender' => 'other', 'status' => 'active']);
$sB = Student::create(['uid' => (string) (210000000000 + random_int(1, 899999)), 'admission_no' => 'PRB-' . uniqid(), 'first_name' => 'Promo', 'last_name' => 'B', 'gender' => 'other', 'status' => 'active']);
$enroll->enroll($sA, $fromYear, $c1, null);
$enroll->enroll($sB, $fromYear, $c1, null);

$review = $svc->evaluate($fromYear, $c1, null);
$byId = [];
foreach ($review as $r) { $byId[$r['student_id']] = $r; }
check('both new students appear in the review list', isset($byId[$sA], $byId[$sB]));
check('result is "promoted" without an exam', ($byId[$sA]['result'] ?? '') === 'promoted');
check('suggested next class is the class above', (int) ($byId[$sA]['next_class_id'] ?? 0) === $c2);

// ---------------------------------------------------------------------------
section('Evaluate WITH an exam (pass vs fail)');
$examTypeId = (int) QueryBuilder::table('exam_types')->value('id');
$subjectId  = (int) QueryBuilder::table('subjects')->value('id');
$examId = (int) QueryBuilder::table('exams')->insert(['exam_type_id' => $examTypeId, 'academic_year_id' => $fromYear, 'uid' => (string) (900000000000 + random_int(1, 899999)), 'name' => 'Final ' . uniqid(), 'status' => 'scheduled', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
$schedId = (int) QueryBuilder::table('exam_schedules')->insert(['exam_id' => $examId, 'class_id' => $c1, 'subject_id' => $subjectId, 'exam_date' => date('Y-m-d'), 'full_marks' => 100, 'pass_marks' => 33, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
QueryBuilder::table('exam_marks')->insert(['exam_schedule_id' => $schedId, 'student_id' => $sA, 'marks_obtained' => 72, 'status' => 'submitted', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
QueryBuilder::table('exam_marks')->insert(['exam_schedule_id' => $schedId, 'student_id' => $sB, 'marks_obtained' => 20, 'status' => 'submitted', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);

$rev2 = [];
foreach ($svc->evaluate($fromYear, $c1, $examId) as $r) { $rev2[$r['student_id']] = $r; }
check('passing student -> promoted', ($rev2[$sA]['result'] ?? '') === 'promoted' && $rev2[$sA]['percentage'] == 72);
check('failing student -> failed', ($rev2[$sB]['result'] ?? '') === 'failed');

// ---------------------------------------------------------------------------
section('Apply promotion (history-preserving, idempotent)');
$fromEnrollA = QueryBuilder::table('student_enrollments')->where('student_id', $sA)->where('academic_year_id', $fromYear)->first();
$res = $svc->apply($fromYear, $toYear, [
    $sA => ['action' => 'promote', 'to_class_id' => $c2, 'to_section_id' => 0],
    $sB => ['action' => 'repeat', 'to_class_id' => $c1, 'to_section_id' => 0],
]);
check('one promoted, one repeated', $res['promoted'] === 1 && $res['repeated'] === 1);

$newA = QueryBuilder::table('student_enrollments')->where('student_id', $sA)->where('academic_year_id', $toYear)->first();
check('promoted student has a NEW enrollment in the target year', $newA !== null && (int) $newA['class_id'] === $c2 && (int) $newA['roll_no'] >= 1);
check('old enrollment preserved + marked promoted', (string) QueryBuilder::table('student_enrollments')->where('id', $fromEnrollA['id'])->value('status') === 'promoted');
check('promotion history row recorded (promoted)', (int) QueryBuilder::table('student_promotions')->where('student_id', $sA)->where('academic_year_id', $toYear)->where('result', 'promoted')->count() === 1);
check('repeated student re-enrolled in SAME class', (int) QueryBuilder::table('student_enrollments')->where('student_id', $sB)->where('academic_year_id', $toYear)->value('class_id') === $c1);
check('repeated student recorded as retained', (int) QueryBuilder::table('student_promotions')->where('student_id', $sB)->where('academic_year_id', $toYear)->where('result', 'retained')->count() === 1);

// Idempotent re-run: no second enrollment created.
$res2 = $svc->apply($fromYear, $toYear, [$sA => ['action' => 'promote', 'to_class_id' => $c2]]);
check('re-applying skips already-enrolled students', $res2['skipped'] === 1 && $res2['promoted'] === 0);
check('still exactly one target-year enrollment for the student', (int) QueryBuilder::table('student_enrollments')->where('student_id', $sA)->where('academic_year_id', $toYear)->count() === 1);

echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll promotion/rollover checks passed.\033[0m\n";
exit(0);
