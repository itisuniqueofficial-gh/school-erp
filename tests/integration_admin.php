<?php

declare(strict_types=1);

/**
 * Admin management integration test (Phase 7): copy-previous-year-setup + users.
 *
 *   php bin/console migrate:fresh && php bin/console seed
 *   php tests/integration_admin.php
 */

use App\Services\AcademicYearService;
use Core\Database\QueryBuilder;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

$adminId = (int) QueryBuilder::table('users')->where('email', 'superadmin@school.test')->value('id');
auth()->onceUsingId($adminId);

$fromYear = (int) QueryBuilder::table('academic_years')->where('is_current', 1)->value('id');

// ---------------------------------------------------------------------------
section('Copy Previous Academic Year Setup (safe)');
$y2 = (int) date('Y') + 1;
$toYear = QueryBuilder::table('academic_years')->insert([
    'name' => $y2 . '-' . ($y2 + 1), 'uid' => '710000009999', 'public_id' => 'AY-' . $y2 . '-' . substr((string) ($y2 + 1), -2),
    'start_date' => $y2 . '-04-01', 'end_date' => ($y2 + 1) . '-03-31', 'is_current' => 0, 'status' => 'active',
    'created_at' => now(), 'updated_at' => now(),
]);

$srcTa  = (int) QueryBuilder::table('teacher_assignments')->where('academic_year_id', $fromYear)->count();
$srcStudents = (int) QueryBuilder::table('student_enrollments')->where('academic_year_id', $fromYear)->count();
check('Source year has teacher assignments to copy', $srcTa > 0);

$svc = new AcademicYearService();
$res = $svc->copySetup($fromYear, $toYear, true);
check('copySetup ok', $res['ok'] === true);
check('Teacher assignments copied to target year', (int) QueryBuilder::table('teacher_assignments')->where('academic_year_id', $toYear)->count() === $srcTa);
check('Class teachers copied', (int) QueryBuilder::table('class_teachers')->where('academic_year_id', $toYear)->count() >= 1);
check('History rows created for copied assignments', (int) QueryBuilder::table('teacher_assignment_history')->count() >= $srcTa);

// SAFETY: students/enrollments/attendance/marks were NOT copied into the new year.
check('Enrollments NOT copied (historical data safe)', (int) QueryBuilder::table('student_enrollments')->where('academic_year_id', $toYear)->count() === 0);
check('Subject attendance NOT copied', (int) QueryBuilder::table('subject_attendance')->where('academic_year_id', $toYear)->count() === 0);

// Idempotency: running again copies nothing new (duplicate-safe).
$res2 = $svc->copySetup($fromYear, $toYear, true);
check('Re-run copies 0 new teacher assignments (dup-safe)', $res2['teacher_assignments'] === 0);
check('Target year assignment count unchanged after re-run', (int) QueryBuilder::table('teacher_assignments')->where('academic_year_id', $toYear)->count() === $srcTa);

// Guard: same source/target rejected.
check('Same source/target rejected', $svc->copySetup($fromYear, $fromYear, true)['ok'] === false);

// ---------------------------------------------------------------------------
section('User Management listing surfaces the 12-digit User ID');
$rows = QueryBuilder::table('users')
    ->selectRaw("`users`.`id`, `users`.`uid`, `users`.`name`, (SELECT GROUP_CONCAT(`r2`.`name` SEPARATOR ', ') FROM `user_roles` `ur2` JOIN `roles` `r2` ON `ur2`.`role_id` = `r2`.`id` WHERE `ur2`.`user_id` = `users`.`id`) AS role_names")
    ->whereNull('users.deleted_at')->orderBy('users.id')->get();
check('Users listed', count($rows) > 0);
$allUid12 = true;
foreach ($rows as $u) {
    if (!(strlen((string) $u['uid']) === 12 && ctype_digit((string) $u['uid']) && str_starts_with((string) $u['uid'], '1'))) { $allUid12 = false; break; }
}
check('Every user has a 12-digit User ID (leading 1)', $allUid12);
check('Roles are resolved for listing', array_filter($rows, fn ($u) => !empty($u['role_names'])) !== []);

// users permissions seeded + granted to school_admin.
check('users.view + users.manage permissions exist', (int) QueryBuilder::table('permissions')->whereRaw("`slug` IN ('users.view','users.manage')")->count() === 2);

// ---------------------------------------------------------------------------
echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll admin-management checks passed.\033[0m\n";
exit(0);
