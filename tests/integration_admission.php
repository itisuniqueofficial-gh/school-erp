<?php

declare(strict_types=1);

/**
 * Admission-number integrity regression test.
 *
 * generateAdmissionNo() must never produce an already-used number. The old
 * count()+1 approach collided after hard deletes / gaps / cross-year counting.
 * The generator now uses the max existing suffix for the year (including
 * soft-deleted rows), so numbers are never reused or duplicated.
 */

require_once dirname(__DIR__) . '/bootstrap/app.php';

use App\Models\Student;
use Core\Database\QueryBuilder;

$pass = 0; $fail = 0;
$check = static function (string $l, bool $c) use (&$pass, &$fail): void {
    if ($c) { $pass++; } else { $fail++; echo "FAIL  {$l}\n"; }
};

$year = date('Y');
$p = 'ADM-' . $year . '-';
$mk = static function (string $no, ?string $deletedAt = null): void {
    QueryBuilder::table('students')->insert([
        'admission_no' => $no, 'first_name' => 'Test', 'last_name' => 'Admission',
        'gender' => 'male', 'status' => 'active',
        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        'deleted_at' => $deletedAt,
    ]);
};
$cleanup = static function () use ($p): void {
    QueryBuilder::table('students')->where('admission_no', 'LIKE', $p . '9%')->delete();
};

$cleanup();

// Gap scenario: 9001 and 9005 exist (9002-9004 "hard deleted"). Next must be
// max+1 = 9006, NOT count-based (which could hit an existing number).
$mk($p . '9001');
$mk($p . '9005');
$next = Student::generateAdmissionNo();
$check('next admission is max+1 (skips gaps): ' . $next, $next === $p . '9006');

$existing = QueryBuilder::table('students')->where('admission_no', $next)->first();
$check('generated admission number does not already exist', $existing === null);

// Soft-deleted numbers must NOT be reused (unique key still occupies them).
$mk($p . '9006');
$mk($p . '9010', date('Y-m-d H:i:s')); // soft-deleted, highest suffix
$next2 = Student::generateAdmissionNo();
$check('respects soft-deleted highest number (no reuse): ' . $next2, $next2 === $p . '9011');
$check('soft-deleted number is not regenerated', $next2 !== $p . '9010');

$cleanup();

// After cleanup the generator still returns a well-formed, unique number.
$after = Student::generateAdmissionNo();
$check('well-formed admission number', (bool) preg_match('/^ADM-\d{4}-\d{4}$/', $after));
$check('post-cleanup number is unique', QueryBuilder::table('students')->where('admission_no', $after)->first() === null);

// Static guard: duplicate/unique violations become a friendly message (no raw SQL).
$sc = (string) file_get_contents(dirname(__DIR__) . '/app/Controllers/Admin/StudentController.php');
$check('store() detects duplicate/unique violations', str_contains($sc, 'Duplicate entry') || str_contains($sc, '1062'));
$check('store() returns a friendly (non-SQL) duplicate message', str_contains($sc, 'already registered') || str_contains($sc, 'already exists'));

echo "PASS: {$pass}   FAIL: {$fail}\n";
exit($fail === 0 ? 0 : 1);
