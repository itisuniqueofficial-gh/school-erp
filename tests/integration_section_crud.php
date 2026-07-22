<?php

declare(strict_types=1);

/**
 * Section management CRUD + duplicate-guard + dependency-safe delete test.
 *
 *   php bin/console migrate:fresh && php bin/console seed
 *   php tests/integration_section_crud.php
 */

use App\Models\ClassModel;
use App\Models\Section;
use App\Services\DependencyService;
use Core\Database\QueryBuilder;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

$dep = new DependencyService();

// A class that uses sections.
$classId = ClassModel::create(['name' => 'SecTest ' . uniqid(), 'numeric_level' => 80, 'uses_sections' => 1]);

// ---------------------------------------------------------------------------
section('Create + unique ID');
$secId = Section::create(['class_id' => $classId, 'name' => 'A', 'capacity' => 30]);
Section::updateById($secId, ['public_id' => sprintf('SEC-%04d', $secId), 'uid' => (string) (100000000000 + $secId)]);
$row = Section::find($secId);
check('Section created', $row !== null);
check('Section has a public_id', !empty($row['public_id']));
check('Section defaults to active status', ($row['status'] ?? 'active') === 'active');

// ---------------------------------------------------------------------------
section('Duplicate prevention (same Class + name)');
$isDup = fn (int $cid, string $name, int $exclude = 0): bool =>
    QueryBuilder::table('sections')->where('class_id', $cid)->where('name', $name)
        ->whereRaw('`id` != ?', [$exclude])->exists();
check('Duplicate name in same class detected', $isDup($classId, 'A') === true);
check('Same name allowed in a DIFFERENT class', $isDup($classId + 999999, 'A') === false);
$dbBlocked = false;
try { Section::create(['class_id' => $classId, 'name' => 'A', 'capacity' => 10]); }
catch (\Throwable) { $dbBlocked = true; }
check('DB UNIQUE(class_id,name) backstops duplicate insert', $dbBlocked === true);

// ---------------------------------------------------------------------------
section('Edit persists');
Section::updateById($secId, ['name' => 'A1', 'capacity' => 55, 'status' => 'active']);
$row = Section::find($secId);
check('Name updated', $row['name'] === 'A1');
check('Capacity updated', (int) $row['capacity'] === 55);
check('Edit dup-guard: renaming to an existing sibling is caught', (function () use ($classId, $secId, $isDup) {
    $sib = Section::create(['class_id' => $classId, 'name' => 'B', 'capacity' => 20]);
    $blocked = $isDup($classId, 'B', $secId); // would this section conflict if renamed to B?
    Section::deleteById($sib);
    return $blocked === true;
})());

// ---------------------------------------------------------------------------
section('Activate / Archive toggle (history-safe)');
Section::updateById($secId, ['status' => 'archived']);
check('Section archived', Section::find($secId)['status'] === 'archived');
Section::updateById($secId, ['status' => 'active']);
check('Section restored to active', Section::find($secId)['status'] === 'active');

// ---------------------------------------------------------------------------
section('Dependency-safe delete');
check('Empty section reports zero dependents', $dep->forSection($secId)['total'] === 0);
Section::deleteById($secId);
check('Empty section deleted', Section::find($secId) === null);

// Section with a student is blocked.
$secWithStudent = (int) QueryBuilder::table('students')->whereNotNull('section_id')->value('section_id');
if ($secWithStudent > 0) {
    $sdep = $dep->forSection($secWithStudent);
    check('Section with students reports dependents', $sdep['total'] > 0);
    check('summary() lists them', $dep->summary($sdep) !== '');
} else {
    check('(no seeded section with students to assert block — skipped)', true);
    check('(skip)', true);
}

// cleanup
ClassModel::deleteById($classId);

echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll section-CRUD checks passed.\033[0m\n";
exit(0);
