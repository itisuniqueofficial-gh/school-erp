<?php

declare(strict_types=1);

/**
 * Marks-integrity regression guard.
 *
 * Invariant: obtained marks must satisfy 0 <= obtained <= full_marks, enforced
 * server-side on BOTH marks-entry paths (admin ExamController + teacher portal)
 * via the single source of truth Exam::isValidMark(). Previously the admin path
 * lacked this check, allowing marks above the maximum (corrupting percentage,
 * grade, and report cards).
 */

require_once dirname(__DIR__) . '/bootstrap/app.php';

use App\Models\Exam;

$pass = 0; $fail = 0;
$check = static function (string $l, bool $c) use (&$pass, &$fail): void {
    if ($c) { $pass++; } else { $fail++; echo "FAIL  {$l}\n"; }
};

// Boundary + typical cases.
$check('0 is valid', Exam::isValidMark(0.0, 100.0) === true);
$check('full marks is valid', Exam::isValidMark(100.0, 100.0) === true);
$check('mid value is valid', Exam::isValidMark(67.5, 100.0) === true);
$check('above maximum is rejected', Exam::isValidMark(150.0, 100.0) === false);
$check('just above maximum is rejected', Exam::isValidMark(100.01, 100.0) === false);
$check('negative is rejected', Exam::isValidMark(-1.0, 100.0) === false);
$check('respects a non-100 maximum (valid)', Exam::isValidMark(40.0, 50.0) === true);
$check('respects a non-100 maximum (rejected)', Exam::isValidMark(60.0, 50.0) === false);

// Both marks-entry paths must use the shared validator (no divergent copies).
$root = dirname(__DIR__);
$admin = (string) file_get_contents($root . '/app/Controllers/Admin/ExamController.php');
$teacher = (string) file_get_contents($root . '/app/Controllers/Portal/TeacherPortalController.php');
$check('admin marks path uses Exam::isValidMark', str_contains($admin, 'Exam::isValidMark'));
$check('teacher marks path uses Exam::isValidMark', str_contains($teacher, 'Exam::isValidMark'));

echo "PASS: {$pass}   FAIL: {$fail}\n";
exit($fail === 0 ? 0 : 1);
