<?php

declare(strict_types=1);

/**
 * Management / identification / editing integration test (Phase 3).
 *
 *   php bin/console migrate:fresh && php bin/console seed
 *   php tests/integration_management.php
 */

use App\Services\TeacherAssignmentService;
use Core\Database\QueryBuilder;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function throws(callable $fn): bool { try { $fn(); return false; } catch (\Throwable) { return true; } }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

$adminId = (int) QueryBuilder::table('users')->where('email', 'superadmin@school.test')->value('id');
auth()->onceUsingId($adminId);

$yearId  = (int) QueryBuilder::table('academic_years')->where('is_current', 1)->value('id');
$classId = (int) QueryBuilder::table('classes')->where('numeric_level', 7)->value('id');
$secA    = (int) QueryBuilder::table('sections')->where('class_id', $classId)->where('name', 'A')->value('id');
$mathId  = (int) QueryBuilder::table('subjects')->where('code', 'MATH')->value('id');

// ---------------------------------------------------------------------------
section('Human-friendly public IDs for academic entities');
foreach (['academic_years' => 'AY-', 'classes' => 'CLS-', 'sections' => 'SEC-', 'subjects' => 'SUB-', 'class_subjects' => 'CSA-'] as $table => $prefix) {
    $missing = (int) QueryBuilder::table($table)->whereNull('public_id')->count();
    // class_subjects may be empty; only assert no-nulls when rows exist.
    $total = (int) QueryBuilder::table($table)->count();
    check("{$table}: every row has a public_id", $total === 0 || $missing === 0);
}
check('Academic year public_id format AY-YYYY-YY', (bool) preg_match('/^AY-\d{4}-\d{2}$/', (string) QueryBuilder::table('academic_years')->value('public_id')));
check('Class public_id format CLS-NNNN', (bool) preg_match('/^CLS-\d{4}$/', (string) QueryBuilder::table('classes')->value('public_id')));
check('Subject public_id format SUB-NNNN', (bool) preg_match('/^SUB-\d{4}$/', (string) QueryBuilder::table('subjects')->value('public_id')));
// Uniqueness enforced by DB.
$dupPid = (string) QueryBuilder::table('classes')->value('public_id');
check('Duplicate class public_id blocked (DB UNIQUE)', throws(fn () =>
    QueryBuilder::table('classes')->insert(['name' => 'ZDup ' . uniqid(), 'public_id' => $dupPid, 'numeric_level' => 99, 'created_at' => now(), 'updated_at' => now()])
));

// ---------------------------------------------------------------------------
section('Class-subject allocation + duplicate prevention');
if (!QueryBuilder::table('class_subjects')->where('class_id', $classId)->where('subject_id', $mathId)->first()) {
    $csId = QueryBuilder::table('class_subjects')->insert([
        'class_id' => $classId, 'subject_id' => $mathId, 'full_marks' => 100, 'pass_marks' => 33,
        'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ]);
    QueryBuilder::table('class_subjects')->where('id', $csId)->update(['public_id' => sprintf('CSA-%04d', $csId)]);
}
check('Class-subject allocation exists', QueryBuilder::table('class_subjects')->where('class_id', $classId)->where('subject_id', $mathId)->exists());
check('Duplicate class-subject allocation blocked (DB UNIQUE)', throws(fn () =>
    QueryBuilder::table('class_subjects')->insert(['class_id' => $classId, 'subject_id' => $mathId, 'full_marks' => 100, 'pass_marks' => 33, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()])
));

// ---------------------------------------------------------------------------
section('Teacher reassignment with history + effective dates');
// Two teachers.
$mkTeacher = function (string $code, string $first) : int {
    $id = QueryBuilder::table('staff')->insert([
        'employee_code' => $code, 'first_name' => $first, 'last_name' => 'Test', 'staff_type' => 'teaching',
        'designation' => 'Teacher', 'joining_date' => date('Y-m-d'), 'status' => 'active',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    QueryBuilder::table('staff')->where('id', $id)->update(['public_id' => sprintf('TCH-9%03d', $id)]);
    return $id;
};
$teacherA = $mkTeacher('EMP-RA-' . substr(uniqid(), -5), 'Alpha');
$teacherB = $mkTeacher('EMP-RB-' . substr(uniqid(), -5), 'Beta');

$svc = new TeacherAssignmentService();
$res = $svc->assign($yearId, $classId, $secA, $mathId, $teacherA, 'subject_teacher');
check('Assign teacher A succeeded', $res['ok'] === true);
$assignmentId = (int) $res['id'];
check('History opened: 1 active row for teacher A', (int) QueryBuilder::table('teacher_assignment_history')->where('assignment_id', $assignmentId)->where('staff_id', $teacherA)->where('status', 'active')->count() === 1);

// Simulate historical ownership data that must survive a reassignment: an
// attendance row marked by the acting admin user (ownership by acting user).
QueryBuilder::table('subject_attendance')->insert([
    'academic_year_id' => $yearId, 'class_id' => $classId, 'section_id' => $secA, 'subject_id' => $mathId,
    'student_id' => (int) QueryBuilder::table('students')->value('id'), 'date' => date('Y-m-d'), 'period' => 0,
    'status' => 'present', 'marked_by' => $adminId, 'created_at' => now(), 'updated_at' => now(),
]);
$attnBefore = (int) QueryBuilder::table('subject_attendance')->where('subject_id', $mathId)->where('class_id', $classId)->count();

$eff = date('Y-m-d');
$rr = $svc->reassign($assignmentId, $teacherB, 'Teacher transfer', $eff);
check('Reassign to teacher B succeeded', $rr['ok'] === true);
check('Current assignment now owned by teacher B', (int) QueryBuilder::table('teacher_assignments')->where('id', $assignmentId)->value('staff_id') === $teacherB);
check('Teacher A period ended with end_date', (string) QueryBuilder::table('teacher_assignment_history')->where('assignment_id', $assignmentId)->where('staff_id', $teacherA)->value('status') === 'ended'
    && QueryBuilder::table('teacher_assignment_history')->where('assignment_id', $assignmentId)->where('staff_id', $teacherA)->value('end_date') !== null);
check('Teacher B has a new active period', (int) QueryBuilder::table('teacher_assignment_history')->where('assignment_id', $assignmentId)->where('staff_id', $teacherB)->where('status', 'active')->count() === 1);
check('Historical attendance preserved after reassignment', (int) QueryBuilder::table('subject_attendance')->where('subject_id', $mathId)->where('class_id', $classId)->count() === $attnBefore);
check('historyForTeacher(A) shows their (ended) period', count(array_filter($svc->historyForTeacher($teacherA), fn ($h) => $h['status'] === 'ended')) >= 1);
check('canTeach: A can no longer teach', !$svc->canTeach($teacherA, $yearId, $classId, $secA, $mathId));
check('canTeach: B can now teach', $svc->canTeach($teacherB, $yearId, $classId, $secA, $mathId));
// Guards.
check('Reassign to same teacher rejected', $svc->reassign($assignmentId, $teacherB, 'again', $eff)['ok'] === false);
check('Reassign with empty reason rejected', $svc->reassign($assignmentId, $teacherA, '', $eff)['ok'] === false);

// ---------------------------------------------------------------------------
section('Academic year activation keeps exactly one current');
$y2 = (int) date('Y') + 1;
$year2Id = QueryBuilder::table('academic_years')->insert([
    'name' => $y2 . '-' . ($y2 + 1), 'public_id' => 'AY-' . $y2 . '-' . substr((string) ($y2 + 1), -2),
    'start_date' => $y2 . '-04-01', 'end_date' => ($y2 + 1) . '-03-31', 'is_current' => 0, 'status' => 'active',
    'created_at' => now(), 'updated_at' => now(),
]);
\App\Models\AcademicYear::makeCurrent($year2Id);
check('Exactly one academic year is current after activation', (int) QueryBuilder::table('academic_years')->where('is_current', 1)->count() === 1);
check('The newly activated year is the current one', (int) QueryBuilder::table('academic_years')->where('is_current', 1)->value('id') === $year2Id);

// ---------------------------------------------------------------------------
echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll management-upgrade checks passed.\033[0m\n";
exit(0);
