<?php

declare(strict_types=1);

/**
 * Class-wise subject structure test.
 *
 *   php bin/console migrate:fresh && php bin/console seed
 *   php tests/integration_classsubject.php
 */

use App\Services\ClassSubjectService;
use Core\Database\Connection;
use Core\Database\QueryBuilder;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

$adminId = (int) QueryBuilder::table('users')->where('email', 'superadmin@school.test')->value('id');
auth()->onceUsingId($adminId);
$svc = new ClassSubjectService();

// ---------------------------------------------------------------------------
section('Schema');
$cols = array_column(Connection::instance()->select('SHOW COLUMNS FROM class_subjects'), 'Field');
foreach (['subject_type', 'is_mandatory', 'sort_order'] as $c) {
    check("class_subjects.$c exists", in_array($c, $cols, true));
}

$subs = QueryBuilder::table('subjects')->limit(2)->get();
$s1 = (int) $subs[0]['id'];
$s2 = (int) $subs[1]['id'];
$c2 = (int) QueryBuilder::table('classes')->where('name', 'Class 2')->value('id');
$c3 = (int) QueryBuilder::table('classes')->where('name', 'Class 3')->value('id');
$c8 = (int) QueryBuilder::table('classes')->where('name', 'Class 8')->value('id');
$srcClass = (int) QueryBuilder::table('class_subjects')->value('class_id'); // seeded class w/ subjects

// ---------------------------------------------------------------------------
section('Typed assignment (core/optional) + mandatory defaults');
check('optional subject => created', $svc->assign($c2, $s1, ['type' => 'optional']) === 'created');
$row1 = QueryBuilder::table('class_subjects')->where('class_id', $c2)->where('subject_id', $s1)->first();
check('type stored optional', $row1['subject_type'] === 'optional');
check('optional defaults to NOT mandatory', (int) $row1['is_mandatory'] === 0);
$svc->assign($c2, $s2, ['type' => 'core']);
$row2 = QueryBuilder::table('class_subjects')->where('class_id', $c2)->where('subject_id', $s2)->first();
check('core defaults to mandatory', (int) $row2['is_mandatory'] === 1);
check('sort_order auto-increments', (int) $row2['sort_order'] > (int) $row1['sort_order']);

// ---------------------------------------------------------------------------
section('Re-assign is idempotent (no duplicate)');
check('re-assign same subject => updated', $svc->assign($c2, $s1, ['type' => 'elective']) === 'updated');
check('still exactly 2 rows for the class', (int) QueryBuilder::table('class_subjects')->where('class_id', $c2)->count() === 2);
check('type changed to elective', QueryBuilder::table('class_subjects')->where('class_id', $c2)->where('subject_id', $s1)->value('subject_type') === 'elective');
$svc->assign($c2, $s1, ['type' => 'optional', 'mandatory' => true]);
check('explicit mandatory override honoured', (int) QueryBuilder::table('class_subjects')->where('class_id', $c2)->where('subject_id', $s1)->value('is_mandatory') === 1);

// ---------------------------------------------------------------------------
section('Reorder');
$before = array_column($svc->forClass($c2), 'subject_id');
$firstId = (int) $svc->forClass($c2)[0]['id'];
$svc->move($firstId, 'down');
$after = array_column($svc->forClass($c2), 'subject_id');
check('order changed after move down', $before !== $after && count($after) === 2);

// ---------------------------------------------------------------------------
section('Copy structure from another class');
$srcCount = count($svc->forClass($srcClass));
$cp = $svc->copyFromClass($srcClass, $c3);
check('copied all source subjects', $cp['copied'] === $srcCount && $srcCount > 0);
check('target class now has the same count', count($svc->forClass($c3)) === $srcCount);
$svc->copyFromClass($srcClass, $c3);
check('re-copy does not duplicate', count($svc->forClass($c3)) === $srcCount);

// ---------------------------------------------------------------------------
section('Setup health');
$health = $svc->setupHealth();
check('a class with no subjects is flagged', in_array('Class 9', $health['classes_without_subjects'], true));
// Class 8: only an optional, non-mandatory subject -> flagged as no-mandatory.
$svc->assign($c8, $s1, ['type' => 'optional', 'mandatory' => false]);
$health2 = $svc->setupHealth();
check('class with only optional subjects flagged no-mandatory', in_array('Class 8', $health2['classes_without_mandatory'], true));
check('Class 8 no longer in without-subjects', !in_array('Class 8', $health2['classes_without_subjects'], true));

echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll class-subject checks passed.\033[0m\n";
exit(0);
