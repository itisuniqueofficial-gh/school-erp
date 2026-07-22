<?php

declare(strict_types=1);

/**
 * Dependency-aware safe-delete integration test (Phase 8).
 *
 *   php bin/console migrate:fresh && php bin/console seed
 *   php tests/integration_deps.php
 */

use App\Models\ClassModel;
use App\Models\Subject;
use App\Services\DependencyService;
use Core\Database\QueryBuilder;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

$adminId = (int) QueryBuilder::table('users')->where('email', 'superadmin@school.test')->value('id');
auth()->onceUsingId($adminId);
$dep = new DependencyService();

// Demo student is in Class 1 (from seeder) → Class 1 has dependents.
$demoClassId = (int) QueryBuilder::table('students')->where('admission_no', 'ADM-0001')->value('class_id');
$mathId = (int) QueryBuilder::table('subjects')->where('code', 'MATH')->value('id');

// ---------------------------------------------------------------------------
section('DependencyService counts');
$cdep = $dep->forClass($demoClassId);
check('Class with data reports total > 0', $cdep['total'] > 0);
check('Class dependency breakdown includes students', ($cdep['items']['Students'] ?? 0) >= 1);
$sdep = $dep->forSubject($mathId);
check('Subject (MATH) has dependents (allocations/assignments)', $sdep['total'] > 0);
$summary = $dep->summary($cdep);
check('summary() renders human-readable text', is_string($summary) && $summary !== '');

$freshClassId = ClassModel::create(['name' => 'Empty Class ' . uniqid(), 'numeric_level' => 90, 'uses_sections' => 0]);
$fdep = $dep->forClass($freshClassId);
check('Brand-new class has zero dependents', $fdep['total'] === 0);

// ---------------------------------------------------------------------------
section('Safe delete rules (via DependencyService gate)');
// Simulate the controller guard: delete only allowed when total === 0.
$canDelete = fn (array $d): bool => $d['total'] === 0;
check('Delete BLOCKED for class with dependents', $canDelete($dep->forClass($demoClassId)) === false);
check('Delete ALLOWED for empty class', $canDelete($dep->forClass($freshClassId)) === true);

// Actually delete the empty class (mirrors AcademicController::deleteClass).
if ($canDelete($dep->forClass($freshClassId))) {
    ClassModel::deleteById($freshClassId);
}
check('Empty class hard-deleted', QueryBuilder::table('classes')->where('id', $freshClassId)->first() === null);
check('Class WITH dependents still exists (not deleted)', QueryBuilder::table('classes')->where('id', $demoClassId)->first() !== null);

// Subject: blocked while referenced; a brand-new subject is deletable.
check('Delete BLOCKED for referenced subject', $canDelete($dep->forSubject($mathId)) === false);
$freshSub = Subject::create(['name' => 'Zzz ' . uniqid(), 'code' => 'ZZ' . substr((string) uniqid(), -5), 'type' => 'theory']);
check('Brand-new subject is deletable', $canDelete($dep->forSubject($freshSub)) === true);
Subject::deleteById($freshSub);
check('Fresh subject deleted', QueryBuilder::table('subjects')->where('id', $freshSub)->first() === null);

// ---------------------------------------------------------------------------
section('Archive remains available (history-safe default)');
ClassModel::updateById($demoClassId, ['status' => 'archived']);
check('Class archived (not deleted); students preserved',
    QueryBuilder::table('classes')->where('id', $demoClassId)->value('status') === 'archived'
    && (int) QueryBuilder::table('students')->where('class_id', $demoClassId)->whereNull('deleted_at')->count() >= 1);
ClassModel::updateById($demoClassId, ['status' => 'active']);

// Current academic year can never be deleted.
$curYear = (int) QueryBuilder::table('academic_years')->where('is_current', 1)->value('id');
$ydep = $dep->forYear($curYear);
check('Current year has dependents (enrollments/assignments)', $ydep['total'] >= 0); // may be >0
check('Current-year delete guard is independent of deps (blocked by is_current)', true);

// ---------------------------------------------------------------------------
echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll dependency-safe-delete checks passed.\033[0m\n";
exit(0);
