<?php

declare(strict_types=1);

/**
 * PRN (permanent roll number) + class-wise roll number test suite.
 *
 *   APP_TESTING=1 php bin/console migrate:fresh && APP_TESTING=1 php bin/console seed
 *   APP_TESTING=1 php tests/integration_prn_roll.php
 *
 * Covers: PRN format/uniqueness/immutability/never-reused, automatic serial
 * roll numbers, duplicate prevention, manual change + audit, promotion,
 * section transfer, withdrawal, resequencing, legacy backfill, and real
 * multi-process concurrent admissions / transfers / manual changes.
 */

use App\Models\ClassModel;
use App\Models\Student;
use App\Services\EnrollmentService;
use App\Services\IntegrityService;
use App\Services\StudentIdentifierService;
use Core\Database\Connection;
use Core\Database\QueryBuilder;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

// ---------------------------------------------------------------------------
// Worker mode: invoked by the concurrency tests below as separate processes.
// ---------------------------------------------------------------------------
if (($argv[1] ?? '') === 'worker') {
    [, , $mode, $startAt, $a, $b, $c, $d] = array_pad($argv, 8, null);
    while (microtime(true) < (float) $startAt) {
        usleep(200);
    }
    $enroll = new EnrollmentService();
    $res = [];
    try {
        if ($mode === 'admit') {            // classId yearId sectionId count
            for ($i = 0; $i < (int) $d; $i++) {
                $sid = Connection::instance()->transaction(function () use ($a, $b, $c, $enroll): int {
                    $id = Student::create([
                        'uid' => (string) (230000000000 + random_int(1, 99999999)),
                        'first_name' => 'Conc', 'last_name' => 'W' . getmypid(), 'gender' => 'other', 'status' => 'active',
                        'admission_date' => '2031-03-09',
                    ]);
                    $r = $enroll->enroll($id, (int) $b, (int) $a, (int) $c ?: null);
                    if (!$r['ok']) {
                        throw new RuntimeException($r['message']);
                    }
                    return $id;
                });
                $res[] = ['ok' => true, 'id' => $sid];
            }
        } elseif ($mode === 'transfer') {   // studentId yearId classId sectionId
            $r = $enroll->transfer((int) $a, (int) $b, (int) $c, (int) $d ?: null);
            $res[] = $r;
        } elseif ($mode === 'setroll') {    // studentId yearId roll
            $r = $enroll->changeRoll((int) $a, (int) $b, (int) $c, 'concurrency test');
            $res[] = $r;
        }
    } catch (Throwable $e) {
        $res[] = ['ok' => false, 'error' => $e->getMessage()];
    }
    echo json_encode($res);
    exit(0);
}

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }
function throws(callable $fn): bool { try { $fn(); return false; } catch (\Throwable) { return true; } }

$dbName = (string) config('database.connections.' . config('database.default') . '.database');
// Disposable databases only: the "_test" database (APP_TESTING=1) or an APP_ENV=testing environment such as CI.
if (!str_ends_with($dbName, '_test') && (string) config('app.env') !== 'testing') {
    fwrite(STDERR, "Refusing to run against non-test database '{$dbName}'. Use APP_TESTING=1 (or APP_ENV=testing).\n");
    exit(2);
}

$adminId = (int) QueryBuilder::table('users')->where('email', 'superadmin@school.test')->value('id');
auth()->onceUsingId($adminId);
$now = date('Y-m-d H:i:s');
$conn = Connection::instance();
$enroll = new EnrollmentService();
$ids = new StudentIdentifierService();
$sfx = substr(uniqid(), -6);

// Isolated fixtures: own year (current) is not touched; own classes/sections.
$yearA = (int) QueryBuilder::table('academic_years')->insert(['name' => 'PRN-A-' . $sfx, 'start_date' => '2031-04-01', 'end_date' => '2032-03-31', 'is_current' => 0, 'created_at' => $now, 'updated_at' => $now]);
$yearB = (int) QueryBuilder::table('academic_years')->insert(['name' => 'PRN-B-' . $sfx, 'start_date' => '2032-04-01', 'end_date' => '2033-03-31', 'is_current' => 0, 'created_at' => $now, 'updated_at' => $now]);
$class5 = ClassModel::create(['name' => 'PRN C5 ' . $sfx, 'numeric_level' => 91, 'uses_sections' => 1]);
$class6 = ClassModel::create(['name' => 'PRN C6 ' . $sfx, 'numeric_level' => 92, 'uses_sections' => 1]);
$classNoSec = ClassModel::create(['name' => 'PRN NS ' . $sfx, 'numeric_level' => 93, 'uses_sections' => 0]);
$mkSec = static fn (int $cid, string $n): int => (int) QueryBuilder::table('sections')->insert(['class_id' => $cid, 'name' => $n, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
$c5A = $mkSec($class5, 'A'); $c5B = $mkSec($class5, 'B'); $c6A = $mkSec($class6, 'A');
$newStudent = static function (string $first, ?string $date = '2031-03-09', array $extra = []): int {
    return Student::create(array_merge([
        'uid' => (string) (240000000000 + random_int(1, 99999999)),
        'first_name' => $first, 'last_name' => 'T', 'gender' => 'other', 'status' => 'active', 'admission_date' => $date,
    ], $extra));
};
$roll = static fn (int $sid, int $y): ?int => $enroll->rollFor($sid, $y);

// ---------------------------------------------------------------------------
section('PRN generation: format, automatic, registry');
$s1 = $newStudent('Prn1', '2031-03-09');
$row1 = Student::find($s1);
check('PRN auto-generated on creation', !empty($row1['prn']));
check('PRN = YYYYMMDD + sequence (12 digits)', preg_match('/^20310309\d{4}$/', (string) $row1['prn']) === 1);
check('admission number issued in ADM-YYYY-NNNN form', preg_match('/^ADM-2031-\d{4}$/', (string) $row1['admission_no']) === 1);
check('PRN sequence equals admission sequence', substr((string) $row1['prn'], 8) === substr((string) $row1['admission_no'], -4));
check('PRN recorded in append-only registry', QueryBuilder::table('prn_registry')->where('prn', $row1['prn'])->where('student_id', $s1)->count() === 1);
$s2 = $newStudent('Prn2', '2031-03-09');
$s3 = $newStudent('Prn3', '2031-11-15');
$row2 = Student::find($s2); $row3 = Student::find($s3);
check('second admission same day gets next sequence', (int) substr((string) $row2['prn'], 8) === (int) substr((string) $row1['prn'], 8) + 1);
check('PRN uses admission date (2031-11-15)', str_starts_with((string) $row3['prn'], '20311115'));
check('all PRNs distinct', count(array_unique([$row1['prn'], $row2['prn'], $row3['prn']])) === 3);
check('caller-supplied PRN rejected', throws(fn () => Student::create(['prn' => '999', 'uid' => '240000999001', 'first_name' => 'X', 'last_name' => 'Y', 'gender' => 'other'])));
check('invalid admission date falls back to today', str_starts_with((string) Student::find($newStudent('BadDate', '2031-02-31'))['prn'], date('Ymd')));

// ---------------------------------------------------------------------------
section('PRN immutability');
check('Student::updateById with a different PRN throws', throws(fn () => Student::updateById($s1, ['prn' => '203103099999'])));
check('PRN unchanged after rejected update', Student::find($s1)['prn'] === $row1['prn']);
Student::updateById($s1, ['first_name' => 'Renamed', 'prn' => $row1['prn']]);
check('routine update (same PRN passed) keeps PRN and applies other fields', Student::find($s1)['prn'] === $row1['prn'] && Student::find($s1)['first_name'] === 'Renamed');
Student::updateById($s1, ['first_name' => 'Prn1', 'class_id' => $class5]);
check('routine updates never regenerate PRN', Student::find($s1)['prn'] === $row1['prn']);
check('DB UNIQUE index rejects duplicate PRN', throws(fn () => $conn->statement('UPDATE `students` SET `prn` = ? WHERE `id` = ?', [$row1['prn'], $s2])));
check('registry UNIQUE rejects re-issuing a PRN', throws(fn () => QueryBuilder::table('prn_registry')->insert(['prn' => $row1['prn'], 'issued_at' => $now])));
$hasTrigger = (int) $conn->scalar("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE() AND TRIGGER_NAME = 'trg_students_prn_immutable'") > 0;
if ($hasTrigger) {
    check('DB trigger blocks raw PRN change', throws(fn () => $conn->statement("UPDATE `students` SET `prn` = '1' WHERE `id` = ?", [$s1])));
} else {
    echo "  (DB trigger not installed: DB user lacks TRIGGER privilege under binlog; integrity check below covers raw SQL tampering)\n";
    $conn->statement("UPDATE `students` SET `prn` = '203103091234' WHERE `id` = ?", [$s1]);
    $hit = array_filter((new IntegrityService())->checks(), static fn ($c) => $c['key'] === 'prn_registry_mismatch' && $c['count'] > 0);
    check('integrity check detects a raw-SQL PRN tamper', count($hit) === 1);
    $conn->statement('UPDATE `students` SET `prn` = ? WHERE `id` = ?', [$row1['prn'], $s1]);
}

// ---------------------------------------------------------------------------
section('PRN never reused (deletion)');
$sx = $newStudent('Gone', '2031-03-09');
$prnGone = (string) Student::find($sx)['prn'];
Student::deleteById($sx);
check('soft-deleted student keeps its PRN', QueryBuilder::table('students')->where('id', $sx)->value('prn') === $prnGone);
$conn->statement('DELETE FROM `students` WHERE `id` = ?', [$sx]);
check('registry still holds PRN after hard delete', QueryBuilder::table('prn_registry')->where('prn', $prnGone)->count() === 1);
$after = [];
for ($i = 0; $i < 5; $i++) { $after[] = (string) Student::find($newStudent('After' . $i, '2031-03-09'))['prn']; }
check('PRN of a deleted student is never issued again', !in_array($prnGone, $after, true));
check('ids.prnTaken() sees deleted PRN', $ids->prnTaken($prnGone));

// ---------------------------------------------------------------------------
section('Class-wise roll numbers: automatic serial, per class/section/year');
$a = [];
for ($i = 1; $i <= 4; $i++) { $a[$i] = $newStudent('RA' . $i); $enroll->enroll($a[$i], $yearA, $class5, $c5A); }
check('Class 5-A gets 1,2,3,4', array_map(fn ($s) => $roll($s, $yearA), $a) === [1 => 1, 2 => 2, 3 => 3, 4 => 4]);
$b1 = $newStudent('RB1'); $enroll->enroll($b1, $yearA, $class5, $c5B);
check('Class 5-B starts again at 1 (same number, different section)', $roll($b1, $yearA) === 1);
$c1 = $newStudent('RC1'); $enroll->enroll($c1, $yearA, $class6, $c6A);
check('Class 6-A starts at 1 (same number, different class)', $roll($c1, $yearA) === 1);
$n1 = $newStudent('NS1'); $n2 = $newStudent('NS2');
$enroll->enroll($n1, $yearA, $classNoSec, null); $enroll->enroll($n2, $yearA, $classNoSec, null);
check('class without sections numbers 1,2', $roll($n1, $yearA) === 1 && $roll($n2, $yearA) === 2);
check('students.roll_no mirrors the enrollment', Student::find($a[3])['roll_no'] === '3');
check('double enrollment in same year refused', $enroll->enroll($a[1], $yearA, $class5, $c5A)['ok'] === false && $roll($a[1], $yearA) === 1);
check('DB UNIQUE key blocks duplicate roll in year/class/section', throws(fn () => QueryBuilder::table('student_enrollments')->insert(['student_id' => $b1, 'academic_year_id' => $yearA, 'class_id' => $class5, 'section_id' => $c5A, 'roll_no' => 2, 'status' => 'active'])));
$ghost = $newStudent('Ghost');
check('DB UNIQUE key blocks duplicate roll in a no-section class (NULL section)', throws(fn () => QueryBuilder::table('student_enrollments')->insert(['student_id' => $ghost, 'academic_year_id' => $yearA, 'class_id' => $classNoSec, 'section_id' => null, 'roll_no' => 1, 'status' => 'active'])) && $roll($ghost, $yearA) === null);
$explicit = $newStudent('Exp'); $r = $enroll->enroll($explicit, $yearA, $class5, $c5A, 3);
check('explicit duplicate roll rejected with a readable message', $r['ok'] === false && str_contains($r['message'], 'already used'));
check('no enrollment row created by a rejected enrollment', $roll($explicit, $yearA) === null);

// ---------------------------------------------------------------------------
section('Manual roll change: validation, duplicates, audit, history');
$res = $enroll->changeRoll($a[1], $yearA, 2, 'x');
check('duplicate manual roll rejected', $res['ok'] === false && $roll($a[1], $yearA) === 1);
check('roll 0 rejected', $enroll->changeRoll($a[1], $yearA, 0, 'x')['ok'] === false);
check('roll above max rejected', $enroll->changeRoll($a[1], $yearA, 100000, 'x')['ok'] === false);
check('reason is mandatory', $enroll->changeRoll($a[1], $yearA, 10, '  ')['ok'] === false);
$res = $enroll->changeRoll($a[1], $yearA, 10, 'Correction after merit list');
check('free roll accepted', $res['ok'] && $roll($a[1], $yearA) === 10 && Student::find($a[1])['roll_no'] === '10');
$h = QueryBuilder::table('student_roll_history')->where('student_id', $a[1])->where('change_type', 'manual')->first();
check('history row stores old=1 new=10 reason and actor', $h && (int) $h['old_roll_no'] === 1 && (int) $h['new_roll_no'] === 10 && $h['reason'] === 'Correction after merit list' && (int) $h['changed_by'] === $adminId);
check('audit log entry written for the change', QueryBuilder::table('audit_logs')->where('action', 'student.roll_changed')->where('entity_id', $a[1])->count() >= 1);
check('PRN unchanged by roll change', Student::find($a[1])['prn'] !== null && QueryBuilder::table('prn_registry')->where('student_id', $a[1])->value('prn') === Student::find($a[1])['prn']);
$next = $newStudent('AfterManual'); $enroll->enroll($next, $yearA, $class5, $c5A);
check('next auto roll continues after the highest (11), never fills below/duplicates', $roll($next, $yearA) === 11);

// ---------------------------------------------------------------------------
section('Transfer between sections (same year)');
$prnT = Student::find($a[2])['prn'];
$res = $enroll->transfer($a[2], $yearA, $class5, $c5B, 'transfer', 'Section change');
check('transfer succeeds with new roll in destination (2 in 5-B)', $res['ok'] && $roll($a[2], $yearA) === 2);
check('PRN unchanged by transfer', Student::find($a[2])['prn'] === $prnT);
check('students placement updated', (int) Student::find($a[2])['section_id'] === $c5B && Student::find($a[2])['roll_no'] === '2');
$th = QueryBuilder::table('student_roll_history')->where('student_id', $a[2])->where('change_type', 'transfer')->first();
check('old roll (2 in 5-A) preserved in history', $th && (int) $th['old_roll_no'] === 2 && (int) $th['old_section_id'] === $c5A && (int) $th['section_id'] === $c5B);
$late = $newStudent('LateA'); $enroll->enroll($late, $yearA, $class5, $c5A);
check('vacated roll in origin is not reused (12, not 2)', $roll($late, $yearA) === 12);
check('transfer to same section is a no-op', $enroll->transfer($a[2], $yearA, $class5, $c5B)['roll_no'] === 2);

// ---------------------------------------------------------------------------
section('Withdrawal keeps history; reactivation restores');
$w = $a[3]; $rollW = $roll($w, $yearA);
$res = $enroll->withdraw($w, $yearA, 'left', 'Left school');
check('withdraw closes enrollment, roll retained', $res['ok'] && $roll($w, $yearA) === $rollW && QueryBuilder::table('student_enrollments')->where('student_id', $w)->value('status') === 'left');
$z = $newStudent('AfterW'); $enroll->enroll($z, $yearA, $class5, $c5A);
check('new admission does not take the withdrawn number', $roll($z, $yearA) > $rollW && $roll($z, $yearA) !== $rollW);
check('transfer of a withdrawn enrollment refused', $enroll->transfer($w, $yearA, $class5, $c5B)['ok'] === false);
$enroll->syncWithStudentStatus($w, 'active');
check('reactivation returns the same roll number', QueryBuilder::table('student_enrollments')->where('student_id', $w)->value('status') === 'active' && $roll($w, $yearA) === $rollW);
$enroll->syncWithStudentStatus($w, 'transferred');
check('student status "transferred" closes enrollment as transferred', QueryBuilder::table('student_enrollments')->where('student_id', $w)->value('status') === 'transferred');
$enroll->syncWithStudentStatus($w, 'active');

// ---------------------------------------------------------------------------
section('Promotion to the next academic year');
$p = $a[4]; $prnP = Student::find($p)['prn']; $oldRoll = $roll($p, $yearA);
$other = $newStudent('PromoOther'); $enroll->enroll($other, $yearB, $class6, $c6A);
$res = $enroll->promote($p, $yearA, $yearB, $class6, $c6A);
check('promotion succeeds with the next free roll in the target (2)', $res['ok'] && $roll($p, $yearB) === 2);
check('historical roll in old year untouched', $roll($p, $yearA) === $oldRoll && QueryBuilder::table('student_enrollments')->where('student_id', $p)->where('academic_year_id', $yearA)->value('status') === 'promoted');
check('PRN unchanged by promotion', Student::find($p)['prn'] === $prnP);
check('promotion recorded in student_promotions', QueryBuilder::table('student_promotions')->where('student_id', $p)->where('academic_year_id', $yearB)->count() === 1);
check('second promotion into same year refused', $enroll->promote($p, $yearA, $yearB, $class6, $c6A)['ok'] === false && $roll($p, $yearB) === 2);
check('students.roll_no now shows the new-year roll', Student::find($p)['roll_no'] === '2' && (int) Student::find($p)['academic_year_id'] === $yearB);
check('past-year roll is still retrievable for documents (rollFor)', $enroll->rollFor($p, $yearA) === $oldRoll);

// ---------------------------------------------------------------------------
section('Resequencing (explicit admin action)');
$yr = (int) QueryBuilder::table('academic_years')->insert(['name' => 'PRN-CUR-' . $sfx, 'start_date' => '2034-04-01', 'end_date' => '2035-03-31', 'is_current' => 0, 'created_at' => $now, 'updated_at' => $now]);
$rs = [];
foreach (['Zed', 'Amy', 'Bob', 'Cat', 'Dan'] as $nm) { $rs[$nm] = $newStudent($nm); $enroll->enroll($rs[$nm], $yr, $class6, $c6A); }
check('resequence refused for a non-current year (history)', $enroll->resequence($yr, $class6, $c6A, 'x')['ok'] === false);
$conn->statement('UPDATE `academic_years` SET `is_current` = 0'); $conn->statement('UPDATE `academic_years` SET `is_current` = 1 WHERE `id` = ?', [$yr]);
$enroll->withdraw($rs['Bob'], $yr, 'left');               // keeps roll 3
check('reason required for resequence', $enroll->resequence($yr, $class6, $c6A, '')['ok'] === false);
$res = $enroll->resequence($yr, $class6, $c6A, 'Alphabetical order for new term');
check('resequence ok', $res['ok']);
$order = array_map(fn ($n) => $roll($rs[$n], $yr), ['Amy', 'Cat', 'Dan', 'Zed']);
check('active students alphabetical 1,2,4,5 (skipping withdrawn Bob=3)', $order === [1, 2, 4, 5] && $roll($rs['Bob'], $yr) === 3);
check('resequence logged in history + audit', QueryBuilder::table('student_roll_history')->where('change_type', 'resequence')->count() >= 1 && QueryBuilder::table('audit_logs')->where('action', 'student.rolls_resequenced')->count() >= 1);
check('resequence did not change any PRN', (string) QueryBuilder::table('students')->where('id', $rs['Zed'])->value('prn') === (string) QueryBuilder::table('prn_registry')->where('student_id', $rs['Zed'])->value('prn'));
$conn->statement('UPDATE `academic_years` SET `is_current` = 0 WHERE `id` = ?', [$yr]);

// ---------------------------------------------------------------------------
section('Atomicity: a failed enrollment inside an admission rolls the whole admission back');
$before = (int) QueryBuilder::table('students')->count();
$threw = false;
try {
    $conn->transaction(function () use ($newStudent, $enroll, $yearA, $class5, $c5A, $a): void {
        $id = $newStudent('Atomic');
        $r = $enroll->enroll($id, $yearA, $class5, $c5A, 10);   // roll 10 is held by $a[1]
        if (!$r['ok']) { throw new RuntimeException($r['message']); }
    });
} catch (RuntimeException) { $threw = true; }
check('admission rolled back (no orphan student)', $threw && (int) QueryBuilder::table('students')->count() === $before);
$conn->transaction(function () use ($enroll, $a, $yearA, $class5, $c5A) {
    $enroll->enroll($a[1], $yearA, $class5, $c5A);                 // fails inside a joined tx (savepoint)
    return true;
});
check('failed step inside an outer transaction leaves the outer transaction usable', $roll($a[1], $yearA) === 10);

// ---------------------------------------------------------------------------
section('Legacy data backfill (missing / invalid / duplicate identifiers)');
$raw = static function (array $d): int {
    return QueryBuilder::table('students')->insert(array_merge([
        'first_name' => 'Legacy', 'last_name' => 'Row', 'gender' => 'male', 'status' => 'active',
        'created_at' => '2024-06-01 10:00:00', 'updated_at' => '2024-06-01 10:00:00',
    ], $d));
};
$yL = (int) QueryBuilder::table('academic_years')->insert(['name' => 'PRN-L-' . $sfx, 'start_date' => '2024-04-01', 'end_date' => '2025-03-31', 'is_current' => 0, 'created_at' => $now, 'updated_at' => $now]);
$classL = ClassModel::create(['name' => 'PRN LEG ' . $sfx, 'numeric_level' => 94, 'uses_sections' => 0]);
$L = [];
$L['ok1']    = $raw(['admission_no' => 'ADM-0001-' . $sfx, 'admission_date' => '2024-06-03', 'class_id' => $classL, 'academic_year_id' => $yL, 'roll_no' => '7']);
$L['dupseq'] = $raw(['admission_no' => 'OLD-2024-0001', 'admission_date' => '2024-06-03']);          // same date + seq 0001 as another legacy row
$L['dupseq2'] = $raw(['admission_no' => 'ADM-2024-0001', 'admission_date' => '2024-06-03']);
$L['blankno'] = $raw(['admission_no' => '', 'admission_date' => '2024-06-04']);
$L['nodate'] = $raw(['admission_no' => 'WEIRD#' . $sfx, 'admission_date' => null]);
$L['baddate'] = $raw(['admission_no' => 'ADM-2024-0099', 'admission_date' => null, 'created_at' => null]);   // no usable date at all
$L['dupRoll'] = $raw(['admission_no' => 'ADM-2024-0100', 'admission_date' => '2024-06-05', 'class_id' => $classL, 'academic_year_id' => $yL, 'roll_no' => '7']);
$L['textRoll'] = $raw(['admission_no' => 'ADM-2024-0101', 'admission_date' => '2024-06-05', 'class_id' => $classL, 'academic_year_id' => $yL, 'roll_no' => 'A-12']);
$L['noyear'] = $raw(['admission_no' => 'ADM-2024-0102', 'admission_date' => '2024-06-05', 'class_id' => $classL]);
$L['deleted'] = $raw(['admission_no' => 'ADM-2024-0103', 'admission_date' => '2024-06-05', 'deleted_at' => '2025-01-01 00:00:00']);
foreach ($L as $k => $id) { $conn->statement('UPDATE `students` SET `uid` = ? WHERE `id` = ?', [(string) (250000000000 + $id), $id]); }
$snap = static function () use ($L): array {
    $o = [];
    foreach ($L as $k => $id) { $r = Student::find($id) ?? QueryBuilder::table('students')->where('id', $id)->first(); $o[$k] = [$r['first_name'], $r['admission_no'], $r['admission_date'], $r['class_id']]; }
    return $o;
};
$existingPrn = Student::find($s1)['prn'];
$dry = $ids->backfill(true);
check('dry run reports work but writes nothing', $dry['dry_run'] === true && $dry['prn_issued'] >= count($L) && QueryBuilder::table('students')->whereNull('prn')->count() >= count($L));
$report = $ids->backfill();
check('every student now has a PRN', QueryBuilder::table('students')->whereNull('prn')->count() === 0);
$legacyPrns = array_map(fn ($id) => (string) QueryBuilder::table('students')->where('id', $id)->value('prn'), $L);
check('legacy PRNs are all unique (even with identical date+sequence)', count(array_unique($legacyPrns)) === count($L));
check('PRN follows legacy admission date: ADM-2024-0100 -> 202406050100', $legacyPrns['dupRoll'] === '202406050100');
check('missing admission date -> created_at date used (20240601…)', str_starts_with($legacyPrns['nodate'], '20240601'));
check('no admission_date and no created_at -> today used, and noted in registry', str_starts_with($legacyPrns['baddate'], date('Ymd')) && str_contains((string) QueryBuilder::table('prn_registry')->where('student_id', $L['baddate'])->value('notes'), 'backfill date used'));
check('blank admission number gets a fresh ADM number, others untouched', preg_match('/^ADM-\d{4}-\d{4}$/', (string) QueryBuilder::table('students')->where('id', $L['blankno'])->value('admission_no')) === 1 && QueryBuilder::table('students')->where('id', $L['textRoll'])->value('admission_no') === 'ADM-2024-0101');
check('existing admission numbers / names / classes not modified', $snap()['ok1'][1] === 'ADM-0001-' . $sfx && $snap()['dupseq'][1] === 'OLD-2024-0001' && $snap()['deleted'][1] === 'ADM-2024-0103');
check('soft-deleted legacy student also got a PRN (never reused)', $legacyPrns['deleted'] !== '');
check('registry has one row per legacy PRN with source=backfill', QueryBuilder::table('prn_registry')->where('source', 'backfill')->whereIn('student_id', array_values($L))->count() === count($L));
check('pre-existing PRN untouched by backfill', Student::find($s1)['prn'] === $existingPrn);
check('legacy roll 7 kept for first holder', $roll($L['ok1'], $yL) === 7);
$dupRollNow = $roll($L['dupRoll'], $yL);
check('duplicate legacy roll 7 reassigned to next free number, old value in history', $dupRollNow !== null && $dupRollNow !== 7 && QueryBuilder::table('student_roll_history')->where('student_id', $L['dupRoll'])->where('change_type', 'backfill')->count() === 1);
check('non-numeric legacy roll "A-12" gets a proper serial', $roll($L['textRoll'], $yL) !== null && $roll($L['textRoll'], $yL) !== 7);
$unres = array_column($report['unresolved'], 'student_id');
check('student with class but no academic year is reported, not guessed', in_array($L['noyear'], $unres, true) && $roll($L['noyear'], $yL) === null);
$again = $ids->backfill();
check('backfill is idempotent (second run issues nothing, changes no PRN)', $again['prn_issued'] === 0 && $again['enrollments_created'] === 0 && $legacyPrns === array_map(fn ($id) => (string) QueryBuilder::table('students')->where('id', $id)->value('prn'), $L));
$int = array_filter((new IntegrityService())->checks(), static fn ($c) => in_array($c['key'], ['students_no_prn', 'prn_registry_mismatch', 'duplicate_rolls'], true) && $c['count'] > 0);
check('integrity checks clean after backfill', count($int) === 0);

// ---------------------------------------------------------------------------
section('Concurrency: parallel admissions (separate PHP processes)');
$yC = (int) QueryBuilder::table('academic_years')->insert(['name' => 'PRN-C-' . $sfx, 'start_date' => '2036-04-01', 'end_date' => '2037-03-31', 'is_current' => 0, 'created_at' => $now, 'updated_at' => $now]);
$classC = ClassModel::create(['name' => 'PRN CC ' . $sfx, 'numeric_level' => 95, 'uses_sections' => 1]);
$secC = $mkSec($classC, 'A');
$spawn = static function (array $argsList): array {
    $start = microtime(true) + 1.5;
    $procs = [];
    foreach ($argsList as $args) {
        $cmd = array_merge([PHP_BINARY, __FILE__, 'worker', $args[0], (string) $start], array_map('strval', array_slice($args, 1)));
        $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null);
        $procs[] = [$p, $pipes];
    }
    $out = [];
    foreach ($procs as [$p, $pipes]) {
        $stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]);
        proc_close($p);
        $dec = json_decode($stdout, true);
        $out[] = is_array($dec) ? $dec : [['ok' => false, 'error' => 'worker output: ' . substr($stdout . $stderr, 0, 300)]];
    }
    return $out;
};
$workers = 8; $per = 5;
$results = $spawn(array_fill(0, $workers, ['admit', $classC, $yC, $secC, $per]));
$flat = array_merge(...$results);
$errors = array_filter($flat, fn ($r) => empty($r['ok']));
check("{$workers} workers x {$per} admissions all succeeded" . ($errors ? ' (first error: ' . json_encode(array_values($errors)[0]) . ')' : ''), count($flat) === $workers * $per && !$errors);
$cIds = array_column($flat, 'id');
$rolls = array_map(fn ($id) => $roll((int) $id, $yC), $cIds); sort($rolls);
check('roll numbers are exactly 1..' . ($workers * $per) . ' with no gaps or duplicates', $rolls === range(1, $workers * $per));
$prns = array_map(fn ($id) => (string) Student::find((int) $id)['prn'], $cIds);
check('PRNs all unique under concurrency', count(array_unique($prns)) === count($prns) && count($prns) === $workers * $per);
$adm = array_map(fn ($id) => (string) Student::find((int) $id)['admission_no'], $cIds);
check('admission numbers all unique under concurrency', count(array_unique($adm)) === count($adm));
$seqs = array_map(fn ($p) => (int) substr($p, 8), $prns); sort($seqs);
check('PRN sequences consecutive (no burned numbers on success)', $seqs === range($seqs[0], $seqs[0] + count($seqs) - 1));
check('every concurrent PRN is in the registry', QueryBuilder::table('prn_registry')->whereIn('student_id', $cIds)->count() === count($cIds));

section('Concurrency: parallel transfers into one section, and racing manual roll changes');
$movers = [];
for ($i = 0; $i < 6; $i++) { $m = $newStudent('Mover' . $i); $enroll->enroll($m, $yC, $class5, $c5A); $movers[] = $m; }
$before = array_map(fn ($m) => Student::find($m)['prn'], $movers);
$results = $spawn(array_map(fn ($m) => ['transfer', $m, $yC, $classC, $secC], $movers));
check('all parallel transfers ok', count(array_filter(array_merge(...$results), fn ($r) => !empty($r['ok']))) === 6);
$newRolls = array_map(fn ($m) => $roll($m, $yC), $movers); sort($newRolls);
check('transferred students got unique consecutive rolls after the existing ' . ($workers * $per), $newRolls === range($workers * $per + 1, $workers * $per + 6));
check('PRNs unchanged by parallel transfers', $before === array_map(fn ($m) => Student::find($m)['prn'], $movers));
$t1 = $newStudent('Race1'); $t2 = $newStudent('Race2'); $t3 = $newStudent('Race3');
foreach ([$t1, $t2, $t3] as $t) { $enroll->enroll($t, $yC, $classC, $secC); }
$results = $spawn([['setroll', $t1, $yC, 500], ['setroll', $t2, $yC, 500], ['setroll', $t3, $yC, 500]]);
$okCount = count(array_filter(array_merge(...$results), fn ($r) => !empty($r['ok'])));
check('racing manual changes to the same roll: exactly one wins', $okCount === 1);
$held = QueryBuilder::table('student_enrollments')->where('academic_year_id', $yC)->where('class_id', $classC)->where('roll_no', 500)->count();
check('only one enrollment holds roll 500', $held === 1);
$dups = (int) $conn->scalar('SELECT COUNT(*) FROM (SELECT 1 FROM `student_enrollments` GROUP BY `academic_year_id`,`class_id`,`section_key`,`roll_no` HAVING COUNT(*) > 1) t');
check('no duplicate roll numbers anywhere in the database', $dups === 0);

// ---------------------------------------------------------------------------
section('Admission through StudentController::store (form flow)');
try {
    $_POST = ['first_name' => 'Form', 'last_name' => 'Admit', 'gender' => 'female', 'class_id' => (string) $classC,
        'section_id' => (string) $secC, 'academic_year_id' => (string) $yC, 'admission_date' => '2031-12-24', 'roll_no' => '999'];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $resp = (new \App\Controllers\Admin\StudentController())->store(new \Core\Http\Request());
    $form = QueryBuilder::table('students')->where('first_name', 'Form')->where('last_name', 'Admit')->first();
    check('form admission created student with PRN 20311224NNNN', $form && preg_match('/^20311224\d{4}$/', (string) $form['prn']) === 1);
    check('admission without a photo stores NULL photo (regression: used to store "Array")', $form && $form['photo'] === null);
    check('form-supplied roll_no (999) ignored; next serial after the highest used (500 set manually earlier) = 501', $form && $roll((int) $form['id'], $yC) === 501);
    // Edit form: tampered prn / roll_no inputs must not change anything.
    $_POST = ['first_name' => 'Form', 'last_name' => 'Admit', 'gender' => 'female', 'status' => 'active', 'class_id' => (string) $classC,
        'section_id' => (string) $secC, 'prn' => '1', 'roll_no' => '777', '_method' => 'PUT'];
    (new \App\Controllers\Admin\StudentController())->update(new \Core\Http\Request(), (string) $form['id']);
    $form2 = Student::find((int) $form['id']);
    check('student edit ignores posted prn and roll_no', $form2['prn'] === $form['prn'] && $roll((int) $form['id'], $yC) !== 777);
    // Class change via edit form moves enrollment + issues new roll.
    $_POST = ['first_name' => 'Form', 'last_name' => 'Admit', 'gender' => 'female', 'status' => 'active', 'class_id' => (string) $class6,
        'section_id' => (string) $c6A, '_method' => 'PUT'];
    $oldFormRoll = $roll((int) $form['id'], $yC);
    (new \App\Controllers\Admin\StudentController())->update(new \Core\Http\Request(), (string) $form['id']);
    $enr = QueryBuilder::table('student_enrollments')->where('student_id', (int) $form['id'])->where('academic_year_id', $yC)->first();
    check('class change via edit form updates the enrollment (no drift) and PRN stays', (int) $enr['class_id'] === $class6 && (int) Student::find((int) $form['id'])['class_id'] === $class6 && Student::find((int) $form['id'])['prn'] === $form['prn']);
    check('class change recorded old roll in history', QueryBuilder::table('student_roll_history')->where('student_id', (int) $form['id'])->where('change_type', 'transfer')->where('old_roll_no', $oldFormRoll)->count() === 1);
} catch (\Throwable $e) {
    check('controller flow executes without exception: ' . $e->getMessage(), false);
}

// ---------------------------------------------------------------------------
echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) {
    echo "Failures:\n - " . implode("\n - ", $failures) . "\n";
    exit(1);
}
echo "\033[32mAll PRN / roll-number checks passed.\033[0m\n";
