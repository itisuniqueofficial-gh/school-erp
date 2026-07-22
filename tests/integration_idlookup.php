<?php

declare(strict_types=1);

/**
 * Universal ID resolver ("Go to Record by ID") test.
 *
 *   php bin/console migrate:fresh && php bin/console seed
 *   php tests/integration_idlookup.php
 */

use App\Models\Student;
use App\Services\CertificateRequestService;
use App\Services\CertificateService;
use App\Services\IdResolverService;
use App\Services\ReportCardService;
use Core\Database\QueryBuilder;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

$adminId = (int) QueryBuilder::table('users')->where('email', 'superadmin@school.test')->value('id');
auth()->onceUsingId($adminId);
$r = new IdResolverService();

// Fixtures.
$yearId = (int) QueryBuilder::table('academic_years')->value('id');
$classId = (int) QueryBuilder::table('classes')->value('id');
$sid = Student::create(['uid' => '200000555001', 'admission_no' => 'ADM-LOOKUP-1', 'first_name' => 'Lookup', 'last_name' => 'Kid', 'gender' => 'male', 'class_id' => $classId, 'academic_year_id' => $yearId, 'status' => 'active']);
QueryBuilder::table('students')->where('id', $sid)->update(['public_id' => 'STU-2026-90001']);
$studentPub = (string) QueryBuilder::table('students')->where('id', $sid)->value('public_id');
$staff = QueryBuilder::table('staff')->where('staff_type', 'teaching')->first();

// ---------------------------------------------------------------------------
section('Student resolution (multiple unique keys → same record)');
foreach ([$studentPub, 'ADM-LOOKUP-1', '200000555001'] as $key) {
    $res = $r->resolve($key);
    check("'{$key}' -> Student /admin/students/{$sid}", $res !== null && $res['type'] === 'Student' && $res['route'] === '/admin/students/' . $sid);
}
check("student public id is case-insensitive", ($r->resolve(strtolower($studentPub))['route'] ?? '') === '/admin/students/' . $sid);

// ---------------------------------------------------------------------------
section('Teacher / staff resolution');
$res = $r->resolve((string) $staff['public_id']);
check('TCH public id -> Teacher route', $res !== null && $res['type'] === 'Teacher' && $res['route'] === '/admin/teachers/' . (int) $staff['id']);
$res = $r->resolve((string) $staff['employee_code']);
check('employee code -> Teacher route', $res !== null && $res['route'] === '/admin/teachers/' . (int) $staff['id']);

// ---------------------------------------------------------------------------
section('Certificate / report card / request resolution');
$cert = (new CertificateService())->issue($sid, 'bonafide', ['purpose' => 'Bank Account']);
$resC = $r->resolve((string) $cert);
check('CERT id -> /admin/certificates/{id}', $resC !== null && $resC['type'] === 'Certificate' && $resC['route'] === '/admin/certificates/' . $cert);

// report card needs an exam + marks
$sub = (int) QueryBuilder::table('subjects')->value('id');
$et = (int) QueryBuilder::table('exam_types')->value('id');
$ex = (int) QueryBuilder::table('exams')->insert(['exam_type_id' => $et, 'academic_year_id' => $yearId, 'uid' => (string) (900000000000 + random_int(1, 899999)), 'name' => 'Lk', 'status' => 'scheduled', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
$scd = (int) QueryBuilder::table('exam_schedules')->insert(['exam_id' => $ex, 'class_id' => $classId, 'subject_id' => $sub, 'exam_date' => date('Y-m-d'), 'full_marks' => 100, 'pass_marks' => 33, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
QueryBuilder::table('exam_marks')->insert(['exam_schedule_id' => $scd, 'student_id' => $sid, 'marks_obtained' => 70, 'status' => 'submitted', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
$rc = (new ReportCardService())->generate($ex, $sid);
$resR = $r->resolve($rc);
check('RC id -> exam report-card route', $resR !== null && $resR['type'] === 'Report Card' && $resR['route'] === '/admin/exams/' . $ex . '/report-cards/' . $sid);

$req = (new CertificateRequestService())->create($sid, 'study', 'Examination', '');
$resReq = $r->resolve((string) $req);
check('REQ id -> certificate-requests queue', $resReq !== null && $resReq['type'] === 'Certificate Request');

// ---------------------------------------------------------------------------
section('Safety: no match / ambiguous / empty');
check('unknown id -> null', $r->resolve('STU-2099-99999') === null);
check('garbage -> null', $r->resolve('!!!nope!!!') === null);
check('empty -> null', $r->resolve('   ') === null);
// Roll number is NOT globally unique, so it must NOT resolve directly.
check('roll number does NOT resolve (ambiguous)', $r->resolve('1') === null);

echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll ID-lookup checks passed.\033[0m\n";
exit(0);
