<?php

declare(strict_types=1);

/**
 * Exam ABSENT-handling regression test.
 *
 * A student marked absent must be recorded as absent ("AB"), NOT as a scored
 * zero: the subject shows AB, carries no obtained marks toward the total, and
 * fails that subject. Both marks-entry paths (admin + teacher) must write
 * is_absent. The report-card document already honours is_absent; this proves
 * the full path end-to-end.
 */

require_once dirname(__DIR__) . '/bootstrap/app.php';

use App\Models\Student;
use App\Services\ReportCardDocumentService;
use Core\Database\QueryBuilder;

$pass = 0; $fail = 0;
$check = static function (string $l, bool $c) use (&$pass, &$fail): void {
    if ($c) { $pass++; } else { $fail++; echo "FAIL  {$l}\n"; }
};

$adminId = (int) QueryBuilder::table('users')->where('email', 'superadmin@school.test')->value('id');
auth()->onceUsingId($adminId);

$yearId = (int) QueryBuilder::table('academic_years')->value('id');
$classId = (int) QueryBuilder::table('classes')->value('id');
$subs = QueryBuilder::table('subjects')->limit(2)->get();
$examTypeId = (int) QueryBuilder::table('exam_types')->value('id');
$examId = (int) QueryBuilder::table('exams')->insert(['exam_type_id' => $examTypeId, 'academic_year_id' => $yearId, 'uid' => (string) (900000000000 + random_int(1, 899999)), 'name' => 'Absent Test ' . uniqid(), 'status' => 'scheduled', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
$sched = [];
foreach ($subs as $sub) {
    $sched[] = (int) QueryBuilder::table('exam_schedules')->insert(['exam_id' => $examId, 'class_id' => $classId, 'subject_id' => (int) $sub['id'], 'exam_date' => date('Y-m-d'), 'full_marks' => 100, 'pass_marks' => 33, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
}
$sid = Student::create(['uid' => (string) (220000000000 + random_int(1, 899999)), 'admission_no' => 'AB-' . uniqid(), 'first_name' => 'Casey', 'last_name' => 'Absent', 'gender' => 'other', 'class_id' => $classId, 'academic_year_id' => $yearId, 'status' => 'active']);

// Subject 1: scored 80. Subject 2: ABSENT (is_absent=1, marks_obtained=0).
QueryBuilder::table('exam_marks')->insert(['exam_schedule_id' => $sched[0], 'student_id' => $sid, 'marks_obtained' => 80, 'is_absent' => 0, 'status' => 'submitted', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
QueryBuilder::table('exam_marks')->insert(['exam_schedule_id' => $sched[1], 'student_id' => $sid, 'marks_obtained' => 0, 'is_absent' => 1, 'status' => 'submitted', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);

$doc = (new ReportCardDocumentService())->build($examId, $sid);
$check('report card builds', $doc !== null);

$obtainedValues = array_map(static fn ($r) => $r['obtained'], $doc['subjects']);
$check('absent subject shows "AB" (not a numeric 0)', in_array('AB', $obtainedValues, true));
$check('scored subject shows its numeric mark (80)', in_array(80.0, array_map(fn ($v) => is_numeric($v) ? (float) $v : $v, $obtainedValues), true));

// Aggregates: only the scored subject counts toward obtained; both toward max.
$check('grand obtained = 80 (absent contributes nothing scored)', (float) $doc['totals']['obtained'] === 80.0);
$check('grand max = 200 (both subjects count toward total)', (float) $doc['totals']['max'] === 200.0);
$check('percentage = 40', (float) $doc['totals']['percentage'] === 40.0);
$check('an absent subject fails the overall result', $doc['totals']['result'] === 'FAILED');

// A row-level absent flag is exposed so the PDF can render AB semantics.
$absentRow = null;
foreach ($doc['subjects'] as $r) { if (($r['obtained'] ?? null) === 'AB') { $absentRow = $r; break; } }
$check('absent row present in document', $absentRow !== null);

// Cleanup fixtures.
QueryBuilder::table('exam_marks')->whereIn('exam_schedule_id', $sched)->delete();
foreach ($sched as $scId) { QueryBuilder::table('exam_schedules')->where('id', $scId)->delete(); }
QueryBuilder::table('report_cards')->where('student_id', $sid)->delete();
QueryBuilder::table('exams')->where('id', $examId)->delete();
QueryBuilder::table('students')->where('id', $sid)->delete();

// --- Static guards: both entry paths and views write/collect is_absent.
$root = dirname(__DIR__);
$adminCtl = (string) file_get_contents($root . '/app/Controllers/Admin/ExamController.php');
$teacherCtl = (string) file_get_contents($root . '/app/Controllers/Portal/TeacherPortalController.php');
$adminView = (string) file_get_contents($root . '/resources/views/admin/exams/marks.php');
$teacherView = (string) file_get_contents($root . '/resources/views/portal/teacher/marks.php');
$check('admin saveMarks reads absent[] and writes is_absent', str_contains($adminCtl, "input('absent'") && str_contains($adminCtl, "'is_absent'"));
$check('teacher saveMarks reads absent[] and writes is_absent', str_contains($teacherCtl, "input('absent'") && str_contains($teacherCtl, "'is_absent'"));
$check('admin marks view has an accessible Absent control', str_contains($adminView, 'data-absent-toggle') && str_contains($adminView, 'aria-label="Mark'));
$check('teacher marks view has an accessible Absent control', str_contains($teacherView, 'data-absent-toggle') && str_contains($teacherView, 'aria-label="Mark'));

echo "PASS: {$pass}   FAIL: {$fail}\n";
exit($fail === 0 ? 0 : 1);
