<?php

declare(strict_types=1);

/**
 * Section-optional architecture integration test (Phase 4).
 *
 *   php bin/console migrate:fresh && php bin/console seed
 *   php tests/integration_sections.php
 *
 * Verifies a class that does NOT use sections works end-to-end: enrollment,
 * roll numbers, teacher assignment, subject attendance, duplicate prevention —
 * all with section_id = NULL — while section-based classes still work.
 */

use App\Models\ClassModel;
use App\Models\Student;
use App\Services\EnrollmentService;
use App\Services\SubjectAttendanceService;
use App\Services\TeacherAssignmentService;
use Core\Database\QueryBuilder;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function throws(callable $fn): bool { try { $fn(); return false; } catch (\Throwable) { return true; } }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

$adminId = (int) QueryBuilder::table('users')->where('email', 'superadmin@school.test')->value('id');
auth()->onceUsingId($adminId);
$yearId = (int) QueryBuilder::table('academic_years')->where('is_current', 1)->value('id');
$mathId = (int) QueryBuilder::table('subjects')->where('code', 'MATH')->value('id');
$teacherId = (int) QueryBuilder::table('staff')->where('employee_code', 'EMP-0001')->value('id');

// ---------------------------------------------------------------------------
section('Create a no-section class');
$noSecClassId = ClassModel::create(['name' => 'NoSec Class ' . uniqid(), 'numeric_level' => 88, 'uses_sections' => 0]);
ClassModel::updateById($noSecClassId, ['public_id' => sprintf('CLS-%04d', $noSecClassId)]);
check('Class created with uses_sections = 0', (int) QueryBuilder::table('classes')->where('id', $noSecClassId)->value('uses_sections') === 0);
check('Class has NO sections', QueryBuilder::table('sections')->where('class_id', $noSecClassId)->count() === 0);

// ---------------------------------------------------------------------------
section('Enroll students into a no-section class (roll numbers per class)');
$enr = new EnrollmentService();
$ids = [];
$rolls = [];
for ($i = 1; $i <= 3; $i++) {
    $sid = Student::create([
        'public_id' => 'STU-NS-' . uniqid(), 'admission_no' => 'NS-' . uniqid(),
        'first_name' => 'NoSec', 'last_name' => 'Student' . $i, 'gender' => 'male',
        'class_id' => $noSecClassId, 'academic_year_id' => $yearId,
        'admission_date' => date('Y-m-d'), 'status' => 'active',
    ]);
    $ids[] = $sid;
    $res = $enr->enroll($sid, $yearId, $noSecClassId, null);
    $rolls[] = $res['roll_no'];
    check("Student {$i} enrolled without a section", $res['ok'] === true);
}
check('Roll numbers are sequential 1,2,3 (scoped to class, no section)', $rolls === [1, 2, 3]);
check('Enrollment rows have NULL section', (int) QueryBuilder::table('student_enrollments')->where('class_id', $noSecClassId)->whereNull('section_id')->count() === 3);

// Duplicate roll in a no-section class must be blocked (via section_key).
check('Duplicate roll in no-section class blocked (section_key UNIQUE)', throws(fn () =>
    QueryBuilder::table('student_enrollments')->insert([
        'student_id' => $ids[0], 'academic_year_id' => $yearId, 'class_id' => $noSecClassId,
        'section_id' => null, 'roll_no' => 1, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ])
));

// ---------------------------------------------------------------------------
section('Assign a teacher to a no-section class (no section field)');
$ta = new TeacherAssignmentService();
$r = $ta->assign($yearId, $noSecClassId, null, $mathId, $teacherId, 'subject_teacher');
check('Teacher assigned without a section', $r['ok'] === true);
check('Assignment row has NULL section', (int) QueryBuilder::table('teacher_assignments')->where('id', (int) $r['id'])->whereNull('section_id')->count() === 1);
check('canTeach() true for no-section assignment', $ta->canTeach($teacherId, $yearId, $noSecClassId, null, $mathId));
// Duplicate no-section assignment blocked (application + DB section_key).
check('Duplicate no-section assignment blocked (application)', $ta->assign($yearId, $noSecClassId, null, $mathId, $teacherId, 'subject_teacher')['ok'] === false);
check('Duplicate no-section assignment blocked (database section_key UNIQUE)', throws(fn () =>
    QueryBuilder::table('teacher_assignments')->insert([
        'academic_year_id' => $yearId, 'class_id' => $noSecClassId, 'section_id' => null,
        'subject_id' => $mathId, 'staff_id' => $teacherId, 'role' => 'subject_teacher', 'created_at' => now(), 'updated_at' => now(),
    ])
));

// ---------------------------------------------------------------------------
section('Subject attendance for a no-section class');
$sa = new SubjectAttendanceService();
$today = date('Y-m-d');
$marked = $sa->markBatch([$ids[0] => 'present', $ids[1] => 'absent', $ids[2] => 'late'], $yearId, $noSecClassId, null, $mathId, $today, $adminId);
check('Marked 3 no-section students', $marked === 3);
check('Subject attendance rows have NULL section', (int) QueryBuilder::table('subject_attendance')->where('class_id', $noSecClassId)->whereNull('section_id')->count() === 3);
// Idempotent re-mark.
$sa->markBatch([$ids[0] => 'absent'], $yearId, $noSecClassId, null, $mathId, $today, $adminId);
check('Re-mark idempotent (still 3 rows)', (int) QueryBuilder::table('subject_attendance')->where('class_id', $noSecClassId)->whereNull('section_id')->count() === 3);
check('Duplicate no-section subject attendance blocked (DB UNIQUE)', throws(fn () =>
    QueryBuilder::table('subject_attendance')->insert([
        'academic_year_id' => $yearId, 'class_id' => $noSecClassId, 'section_id' => null, 'subject_id' => $mathId,
        'student_id' => $ids[0], 'date' => $today, 'period' => 0, 'status' => 'present', 'created_at' => now(), 'updated_at' => now(),
    ])
));

// ---------------------------------------------------------------------------
section('Section-based classes still work (regression)');
$secClassId = (int) QueryBuilder::table('classes')->where('numeric_level', 5)->value('id');
$secA = (int) QueryBuilder::table('sections')->where('class_id', $secClassId)->where('name', 'A')->value('id');
$sSec = Student::create([
    'public_id' => 'STU-S-' . uniqid(), 'admission_no' => 'S-' . uniqid(),
    'first_name' => 'Sec', 'last_name' => 'Student', 'gender' => 'female',
    'class_id' => $secClassId, 'section_id' => $secA, 'academic_year_id' => $yearId,
    'admission_date' => date('Y-m-d'), 'status' => 'active',
]);
$resSec = $enr->enroll($sSec, $yearId, $secClassId, $secA);
check('Section-based enrollment still works', $resSec['ok'] === true);
check('Section enrollment stores the section', (int) QueryBuilder::table('student_enrollments')->where('student_id', $sSec)->value('section_id') === $secA);

// Enable-sections-later: flip the flag; historical no-section enrollments remain intact.
ClassModel::updateById($noSecClassId, ['uses_sections' => 1]);
check('Class can be switched to use sections later', (int) QueryBuilder::table('classes')->where('id', $noSecClassId)->value('uses_sections') === 1);
check('Historical no-section enrollments preserved after enabling sections', (int) QueryBuilder::table('student_enrollments')->where('class_id', $noSecClassId)->whereNull('section_id')->count() === 3);

// ---------------------------------------------------------------------------
echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll section-optional checks passed.\033[0m\n";
exit(0);
