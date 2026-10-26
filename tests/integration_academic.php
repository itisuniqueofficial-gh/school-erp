<?php

declare(strict_types=1);

/**
 * Academic system integration test.
 *
 * Boots the real framework and drives the services + database constraints that
 * back Workflows 1-5 and every duplicate-prevention rule. Run against a freshly
 * migrated + seeded database:
 *
 *   php bin/console migrate:fresh && php bin/console seed
 *   php tests/integration_academic.php
 */

use App\Models\Student;
use App\Services\EnrollmentService;
use App\Services\IdSequenceService;
use App\Services\IntegrityService;
use App\Services\MarksWorkflowService;
use App\Services\TeacherAssignmentService;
use Core\Database\Connection;
use Core\Database\QueryBuilder;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0;
$fail = 0;
$failures = [];

function check(string $label, bool $cond): void
{
    global $pass, $fail, $failures;
    if ($cond) {
        $pass++;
        echo "  \033[32mPASS\033[0m  {$label}\n";
    } else {
        $fail++;
        $failures[] = $label;
        echo "  \033[31mFAIL\033[0m  {$label}\n";
    }
}

/** Assert that $fn throws (used for DB-constraint duplicate prevention). */
function throws(callable $fn): bool
{
    try {
        $fn();
        return false;
    } catch (\Throwable) {
        return true;
    }
}

function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

// Authenticate as super admin so permission-gated workflow transitions run.
$adminId = (int) (QueryBuilder::table('users')->where('email', 'superadmin@school.test')->value('id') ?? 0);
auth()->onceUsingId($adminId);

$yearId    = (int) QueryBuilder::table('academic_years')->where('is_current', 1)->value('id');
$classId   = (int) QueryBuilder::table('classes')->where('numeric_level', 5)->value('id');
$secA      = (int) QueryBuilder::table('sections')->where('class_id', $classId)->where('name', 'A')->value('id');
$secB      = (int) QueryBuilder::table('sections')->where('class_id', $classId)->where('name', 'B')->value('id');
$mathId    = (int) QueryBuilder::table('subjects')->where('code', 'MATH')->value('id');
$sciId     = (int) QueryBuilder::table('subjects')->where('code', 'SCI')->value('id');
$teacherStaffId = (int) QueryBuilder::table('staff')->where('employee_code', 'EMP-0001')->value('id');

echo "Context: year={$yearId} class={$classId} secA={$secA} secB={$secB} math={$mathId} teacher={$teacherStaffId}\n";

// ---------------------------------------------------------------------------
section('Workflow 1: Unique IDs, class-subject, teacher assignment, admission + roll numbers');

$idSvc = new IdSequenceService();
$sid1 = $idSvc->student();
$sid2 = $idSvc->student();
check('Student public ID format STU-YYYY-NNNNN', (bool) preg_match('/^STU-\d{4}-\d{5}$/', $sid1));
check('Sequential public IDs are unique + increasing', $sid1 !== $sid2);
$tch = $idSvc->teacher();
check('Teacher public ID format TCH-YYYY-NNNNN', (bool) preg_match('/^TCH-\d{4}-\d{5}$/', $tch));

// Allocate Math + Science to the class (class_subjects).
foreach ([$mathId, $sciId] as $subj) {
    if (!QueryBuilder::table('class_subjects')->where('class_id', $classId)->where('subject_id', $subj)->first()) {
        QueryBuilder::table('class_subjects')->insert([
            'class_id' => $classId, 'subject_id' => $subj, 'full_marks' => 100, 'pass_marks' => 33,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
check('Class-subject allocation stored', QueryBuilder::table('class_subjects')->where('class_id', $classId)->where('subject_id', $mathId)->exists());

$ta = new TeacherAssignmentService();
$r = $ta->assign($yearId, $classId, $secA, $mathId, $teacherStaffId, 'subject_teacher');
check('Teacher assigned to Math (Class5-A)', $r['ok'] === true);
check('canTeach() true for assigned subject', $ta->canTeach($teacherStaffId, $yearId, $classId, $secA, $mathId));
check('canTeach() false for unassigned subject (Science)', !$ta->canTeach($teacherStaffId, $yearId, $classId, $secA, $sciId));

// Admit 3 students into Class5-A -> auto roll numbers 1,2,3.
$enr = new EnrollmentService();
$studentIds = [];
$rolls = [];
for ($i = 1; $i <= 3; $i++) {
    $pubId = $idSvc->student();
    $studentId = Student::create([
        'public_id' => $pubId, 'admission_no' => 'ITST-' . uniqid(),
        'first_name' => 'Test', 'last_name' => 'Student' . $i, 'gender' => 'male',
        'class_id' => $classId, 'section_id' => $secA, 'academic_year_id' => $yearId,
        'admission_date' => date('Y-m-d'), 'status' => 'active',
    ]);
    $studentIds[] = $studentId;
    $res = $enr->enroll($studentId, $yearId, $classId, $secA);
    $rolls[] = $res['roll_no'];
}
check('Auto roll numbers are sequential 1,2,3', $rolls === [1, 2, 3]);
check('Each admitted student has a permanent public_id',
    count(array_filter($studentIds, fn ($id) => (bool) QueryBuilder::table('students')->where('id', $id)->value('public_id'))) === 3);

// ---------------------------------------------------------------------------
section('Workflow 5: Duplicate prevention (application + database layers)');

// Duplicate teacher assignment (app-level friendly message + DB unique).
$dup = $ta->assign($yearId, $classId, $secA, $mathId, $teacherStaffId, 'subject_teacher');
check('Duplicate teacher assignment blocked (application)', $dup['ok'] === false);
check('Duplicate teacher assignment blocked (database UNIQUE)', throws(fn () =>
    QueryBuilder::table('teacher_assignments')->insert([
        'academic_year_id' => $yearId, 'class_id' => $classId, 'section_id' => $secA,
        'subject_id' => $mathId, 'staff_id' => $teacherStaffId, 'role' => 'subject_teacher',
        'created_at' => now(), 'updated_at' => now(),
    ])
));

// Duplicate enrollment for the same student in the same year.
$dupEnr = $enr->enroll($studentIds[0], $yearId, $classId, $secA);
check('Duplicate enrollment (same student/year) blocked', $dupEnr['ok'] === false);

// Duplicate roll number within (year,class,section) via explicit roll.
$pubX = $idSvc->student();
$sX = Student::create([
    'public_id' => $pubX, 'admission_no' => 'ITST-' . uniqid(), 'first_name' => 'Dup', 'last_name' => 'Roll',
    'gender' => 'male', 'class_id' => $classId, 'section_id' => $secA, 'academic_year_id' => $yearId,
    'admission_date' => date('Y-m-d'), 'status' => 'active',
]);
$dupRoll = $enr->enroll($sX, $yearId, $classId, $secA, 1); // roll 1 already used
check('Duplicate roll number blocked (year/class/section UNIQUE)', $dupRoll['ok'] === false);

// Duplicate subject allocation (class_subjects UNIQUE class_id, subject_id).
check('Duplicate class-subject allocation blocked (database UNIQUE)', throws(fn () =>
    QueryBuilder::table('class_subjects')->insert([
        'class_id' => $classId, 'subject_id' => $mathId, 'full_marks' => 100, 'pass_marks' => 33,
        'created_at' => now(), 'updated_at' => now(),
    ])
));

// Duplicate class-section (sections UNIQUE class_id, name).
check('Duplicate class+section blocked (database UNIQUE)', throws(fn () =>
    QueryBuilder::table('sections')->insert([
        'class_id' => $classId, 'name' => 'A', 'capacity' => 40, 'created_at' => now(), 'updated_at' => now(),
    ])
));

// Duplicate public_id (students UNIQUE public_id).
check('Duplicate public_id blocked (database UNIQUE)', throws(fn () =>
    QueryBuilder::table('students')->insert([
        'public_id' => $pubX, 'admission_no' => 'ITST-' . uniqid(), 'first_name' => 'Clone', 'last_name' => 'X',
        'gender' => 'male', 'admission_date' => date('Y-m-d'), 'status' => 'active',
        'created_at' => now(), 'updated_at' => now(),
    ])
));

// ---------------------------------------------------------------------------
section('Workflow 2: Attendance authorization, date validation, duplicate prevention');

$lock = new App\Services\AttendanceLockService();
check('Attendance editable for today', $lock->isEditable($classId, $secA, date('Y-m-d')));
check('Attendance NOT editable for a future date', !$lock->isEditable($classId, $secA, date('Y-m-d', time() + 86400)));
check('Attendance NOT editable for a past date without unlock', !$lock->isEditable($classId, $secA, date('Y-m-d', time() - 3 * 86400)));

// Mark attendance (upsert) for all 3 students today.
foreach ($studentIds as $i => $sid) {
    App\Models\StudentAttendance::mark($sid, $classId, $secA, date('Y-m-d'), $i === 0 ? 'absent' : 'present', $adminId, 'manual');
}
$attCount = QueryBuilder::table('student_attendance')->where('class_id', $classId)->where('section_id', $secA)->where('date', date('Y-m-d'))->count();
check('Attendance recorded for 3 students', $attCount === 3);

// Re-mark (upsert) must NOT create duplicates.
App\Models\StudentAttendance::mark($studentIds[0], $classId, $secA, date('Y-m-d'), 'present', $adminId, 'manual');
$attCount2 = QueryBuilder::table('student_attendance')->where('class_id', $classId)->where('section_id', $secA)->where('date', date('Y-m-d'))->count();
check('Re-marking does not duplicate (idempotent upsert)', $attCount2 === 3);

// Raw duplicate insert blocked by UNIQUE(student_id, date).
check('Duplicate attendance row blocked (database UNIQUE student_id,date)', throws(fn () =>
    QueryBuilder::table('student_attendance')->insert([
        'student_id' => $studentIds[0], 'class_id' => $classId, 'section_id' => $secA,
        'date' => date('Y-m-d'), 'status' => 'present', 'created_at' => now(), 'updated_at' => now(),
    ])
));

// ---------------------------------------------------------------------------
section('Workflow 3: Marks entry (authorized), duplicate prevention, submit->approve->publish->lock');

// Create an exam + schedule for Math in this class.
$examTypeId = (int) QueryBuilder::table('exam_types')->value('id');
$examId = QueryBuilder::table('exams')->insert([
    'exam_type_id' => $examTypeId, 'academic_year_id' => $yearId, 'name' => 'ITST Unit Test',
    'start_date' => date('Y-m-d'), 'status' => 'scheduled', 'created_at' => now(), 'updated_at' => now(),
]);
$scheduleId = QueryBuilder::table('exam_schedules')->insert([
    'exam_id' => $examId, 'class_id' => $classId, 'subject_id' => $mathId, 'full_marks' => 100, 'pass_marks' => 33,
    'created_at' => now(), 'updated_at' => now(),
]);

// Enter marks for the 3 students.
foreach ($studentIds as $i => $sid) {
    QueryBuilder::table('exam_marks')->insert([
        'exam_schedule_id' => $scheduleId, 'student_id' => $sid, 'marks_obtained' => 40 + $i * 20,
        'status' => 'draft', 'entered_by' => $adminId, 'created_at' => now(), 'updated_at' => now(),
    ]);
}
check('Marks entered for 3 students', QueryBuilder::table('exam_marks')->where('exam_schedule_id', $scheduleId)->count() === 3);

// Duplicate marks blocked by UNIQUE(exam_schedule_id, student_id).
check('Duplicate marks row blocked (database UNIQUE schedule,student)', throws(fn () =>
    QueryBuilder::table('exam_marks')->insert([
        'exam_schedule_id' => $scheduleId, 'student_id' => $studentIds[0], 'marks_obtained' => 99,
        'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
    ])
));

$wf = new MarksWorkflowService();
check('Workflow start status = draft', $wf->currentStatus($scheduleId) === 'draft');
check('submit: draft -> submitted', $wf->transition($scheduleId, 'submit', $adminId)['ok']);
check('approve: submitted -> approved', $wf->transition($scheduleId, 'approve', $adminId)['ok']);
check('Marks are edit-locked after approval', $wf->isLocked($scheduleId));
check('publish: approved -> published', $wf->transition($scheduleId, 'publish', $adminId)['ok']);
check('lock: published -> locked', $wf->transition($scheduleId, 'lock', $adminId)['ok']);
check('Cannot submit once locked (invalid transition)', $wf->transition($scheduleId, 'submit', $adminId)['ok'] === false);

// ---------------------------------------------------------------------------
section('Workflow 4: Promotion preserves Student ID + history, issues new roll number');

$promoteStudent = $studentIds[0];
$originalPublicId = QueryBuilder::table('students')->where('id', $promoteStudent)->value('public_id');
$year2 = QueryBuilder::table('academic_years')->where('id', '!=', $yearId)->first();
if ($year2 === null) {
    $y2 = (int) date('Y') + 1;
    $year2Id = QueryBuilder::table('academic_years')->insert([
        'name' => $y2 . '-' . ($y2 + 1), 'start_date' => $y2 . '-04-01', 'end_date' => ($y2 + 1) . '-03-31',
        'is_current' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
} else {
    $year2Id = (int) $year2['id'];
}
$class6 = (int) QueryBuilder::table('classes')->where('numeric_level', 6)->value('id');
$class6secA = (int) QueryBuilder::table('sections')->where('class_id', $class6)->where('name', 'A')->value('id');

$prom = $enr->promote($promoteStudent, $yearId, $year2Id, $class6, $class6secA);
check('Promotion succeeded', $prom['ok'] === true);
check('Student public ID unchanged after promotion',
    QueryBuilder::table('students')->where('id', $promoteStudent)->value('public_id') === $originalPublicId);
check('Old enrollment marked promoted (history preserved)',
    QueryBuilder::table('student_enrollments')->where('student_id', $promoteStudent)->where('academic_year_id', $yearId)->value('status') === 'promoted');
check('New active enrollment created in target year',
    (bool) QueryBuilder::table('student_enrollments')->where('student_id', $promoteStudent)->where('academic_year_id', $year2Id)->where('status', 'active')->first());
check('student_promotions history row created',
    QueryBuilder::table('student_promotions')->where('student_id', $promoteStudent)->where('academic_year_id', $year2Id)->exists());
check('Historical attendance preserved after promotion',
    QueryBuilder::table('student_attendance')->where('student_id', $promoteStudent)->exists());
check('Historical marks preserved after promotion',
    QueryBuilder::table('exam_marks')->where('student_id', $promoteStudent)->exists());

// ---------------------------------------------------------------------------
section('Integrity checks service');
$checks = (new IntegrityService())->checks();
check('IntegrityService returns >= 8 checks', count($checks) >= 8);
$keys = array_column($checks, 'key');
foreach (['students_no_roll', 'duplicate_rolls', 'subjects_no_teacher', 'marks_exceed_max', 'teacher_overload'] as $k) {
    check("Integrity check present: {$k}", in_array($k, $keys, true));
}
$dupRollsCheck = null;
foreach ($checks as $c) { if ($c['key'] === 'duplicate_rolls') { $dupRollsCheck = $c; } }
check('duplicate_rolls count is 0 (constraint holds)', $dupRollsCheck !== null && (int) $dupRollsCheck['count'] === 0);

// ---------------------------------------------------------------------------
echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) {
    echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n";
    exit(1);
}
echo "\033[32mAll integration checks passed.\033[0m\n";
exit(0);
