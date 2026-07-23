<?php

declare(strict_types=1);

/**
 * Class-wise exam scheduling engine test.
 *
 *   php bin/console migrate:fresh && php bin/console seed
 *   php tests/integration_examschedule.php
 */

use App\Services\ExamScheduleService;
use Core\Database\QueryBuilder;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

$adminId = (int) QueryBuilder::table('users')->where('email', 'superadmin@school.test')->value('id');
auth()->onceUsingId($adminId);
$svc = new ExamScheduleService();

$classId = (int) QueryBuilder::table('class_subjects')->value('class_id');
$yearId = (int) QueryBuilder::table('academic_years')->value('id');
$et = (int) QueryBuilder::table('exam_types')->value('id');
$examId = (int) QueryBuilder::table('exams')->insert(['exam_type_id' => $et, 'academic_year_id' => $yearId, 'uid' => (string) (900000000000 + random_int(1, 899999)), 'name' => 'Sched Exam ' . uniqid(), 'status' => 'scheduled', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);

// ---------------------------------------------------------------------------
section('Class subjects come from class_subjects');
$subs = $svc->classSubjects($classId);
check('class has >= 2 assigned subjects', count($subs) >= 2);
$s1 = (int) $subs[0]['subject_id'];
$s2 = (int) $subs[1]['subject_id'];

// ---------------------------------------------------------------------------
section('Save schedule (upsert, no duplicates)');
$rows = [
    $s1 => ['include' => 1, 'date' => '2026-03-10', 'start' => '10:00', 'end' => '13:00', 'full_marks' => 100, 'pass_marks' => 33],
    $s2 => ['include' => 1, 'date' => '2026-03-12', 'start' => '10:00', 'end' => '13:00', 'full_marks' => 80, 'pass_marks' => 27],
];
$r1 = $svc->saveSchedule($examId, $classId, $rows);
check('first save creates 2 rows', $r1['created'] === 2);
check('current schedule has 2 rows', count($svc->currentSchedule($examId, $classId)) === 2);
$r2 = $svc->saveSchedule($examId, $classId, $rows);
check('re-save updates, creates none (idempotent)', $r2['created'] === 0 && $r2['updated'] === 2);
check('still exactly 2 rows (no duplicate)', (int) QueryBuilder::table('exam_schedules')->where('exam_id', $examId)->where('class_id', $classId)->count() === 2);
check('marks persisted', (float) QueryBuilder::table('exam_schedules')->where('exam_id', $examId)->where('subject_id', $s2)->value('full_marks') === 80.0);

// ---------------------------------------------------------------------------
section('Conflict detection (same date + time)');
$clash = $rows;
$clash[$s2]['date'] = '2026-03-10';
$clash[$s2]['start'] = '10:00';
$svc->saveSchedule($examId, $classId, $clash);
check('conflict detected for same date+time', count($svc->conflicts($examId, $classId)) >= 1);
$svc->saveSchedule($examId, $classId, $rows); // restore distinct dates
check('no conflict with distinct dates', $svc->conflicts($examId, $classId) === []);

// ---------------------------------------------------------------------------
section('Un-include removes a subject row');
$drop = $rows;
unset($drop[$s2]); // not included this time
$r3 = $svc->saveSchedule($examId, $classId, $drop);
check('un-included subject removed', $r3['removed'] === 1);
check('schedule now has 1 row', count($svc->currentSchedule($examId, $classId)) === 1);
$svc->saveSchedule($examId, $classId, $rows); // restore

// ---------------------------------------------------------------------------
section('Copy schedule from another exam');
$examId2 = (int) QueryBuilder::table('exams')->insert(['exam_type_id' => $et, 'academic_year_id' => $yearId, 'uid' => (string) (900000000000 + random_int(1, 899999)), 'name' => 'Sched Target ' . uniqid(), 'status' => 'scheduled', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
$cp = $svc->copyFrom($examId, $examId2, $classId);
check('copied 2 rows', $cp['copied'] === 2);
check('target exam now has the schedule', count($svc->currentSchedule($examId2, $classId)) === 2);
check('re-copy does not duplicate', ($svc->copyFrom($examId, $examId2, $classId)['copied'] === 2) && (int) QueryBuilder::table('exam_schedules')->where('exam_id', $examId2)->where('class_id', $classId)->count() === 2);

// ---------------------------------------------------------------------------
section('Validation: dates + duplicate exam name in scope');
check('end before start is invalid', $svc->datesValid('2026-03-10', '2026-03-01') === false);
check('end after start is valid', $svc->datesValid('2026-03-01', '2026-03-10') === true);
check('empty dates are allowed', $svc->datesValid('', '') === true);
$name = 'Unique Exam ' . uniqid();
QueryBuilder::table('exams')->insert(['exam_type_id' => $et, 'academic_year_id' => $yearId, 'uid' => (string) (900000000000 + random_int(1, 899999)), 'name' => $name, 'status' => 'scheduled', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
check('duplicate name in same year+type detected', $svc->duplicateExists($name, $yearId, $et) === true);
check('case/space-insensitive duplicate detected', $svc->duplicateExists('  ' . strtoupper($name) . ' ', $yearId, $et) === true);
check('different name is not a duplicate', $svc->duplicateExists($name . ' X', $yearId, $et) === false);

echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll exam-schedule checks passed.\033[0m\n";
exit(0);
