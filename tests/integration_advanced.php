<?php

declare(strict_types=1);

/**
 * Advanced-features integration test (Phase 2).
 *
 *   php bin/console migrate:fresh && php bin/console seed
 *   php tests/integration_advanced.php
 */

use App\Models\ClassModel;
use App\Models\Subject;
use App\Services\DocumentService;
use App\Services\NotificationService;
use App\Services\SubjectAttendanceService;
use Core\Database\QueryBuilder;
use Core\Security\Crypto;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function throws(callable $fn): bool { try { $fn(); return false; } catch (\Throwable) { return true; } }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

$adminId = (int) QueryBuilder::table('users')->where('email', 'superadmin@school.test')->value('id');
auth()->onceUsingId($adminId);

$yearId = (int) QueryBuilder::table('academic_years')->where('is_current', 1)->value('id');
$student = QueryBuilder::table('students')->where('admission_no', 'ADM-0001')->first();
$sid = (int) $student['id'];
$classId = (int) $student['class_id'];
$sectionId = (int) $student['section_id'];
$mathId = (int) QueryBuilder::table('subjects')->where('code', 'MATH')->value('id');

// ---------------------------------------------------------------------------
section('Government ID encryption + masking (Crypto)');
$aadhaar = '123456781234';
$enc = Crypto::encrypt($aadhaar);
check('Ciphertext differs from plaintext', $enc !== null && $enc !== $aadhaar);
check('Decrypt round-trips to original', Crypto::decrypt($enc) === $aadhaar);
check('Tampered ciphertext fails (returns null)', Crypto::decrypt($enc . 'zz') === null);
check('Mask shows only last 4 (XXXX-XXXX-1234)', Crypto::mask($aadhaar) === 'XXXX-XXXX-1234');

// Persist encrypted on the student, verify last4 + decrypt from DB.
QueryBuilder::table('students')->where('id', $sid)->update([
    'aadhaar_enc' => Crypto::encrypt($aadhaar), 'aadhaar_last4' => substr($aadhaar, -4),
]);
$fresh = QueryBuilder::table('students')->where('id', $sid)->first();
check('Stored aadhaar_last4 is 1234', ($fresh['aadhaar_last4'] ?? '') === '1234');
check('DB ciphertext is not the raw Aadhaar', ($fresh['aadhaar_enc'] ?? '') !== $aadhaar);
check('Decrypt of stored value matches', Crypto::decrypt($fresh['aadhaar_enc']) === $aadhaar);

// ---------------------------------------------------------------------------
section('Subject-wise attendance + duplicate prevention');
$svc = new SubjectAttendanceService();
$today = date('Y-m-d');
$n = $svc->markBatch([$sid => 'present'], $yearId, $classId, $sectionId, $mathId, $today, $adminId);
check('markBatch marked 1 student', $n === 1);
$cnt = QueryBuilder::table('subject_attendance')
    ->where('academic_year_id', $yearId)->where('subject_id', $mathId)
    ->where('class_id', $classId)->where('section_id', $sectionId)->where('date', $today)->count();
check('One subject-attendance row exists', $cnt === 1);
// Idempotent re-mark (upsert) does not duplicate.
$svc->markBatch([$sid => 'absent'], $yearId, $classId, $sectionId, $mathId, $today, $adminId);
$cnt2 = QueryBuilder::table('subject_attendance')
    ->where('academic_year_id', $yearId)->where('subject_id', $mathId)
    ->where('class_id', $classId)->where('section_id', $sectionId)->where('date', $today)->count();
check('Re-mark is idempotent (still 1 row)', $cnt2 === 1);
check('Status updated to absent on re-mark',
    QueryBuilder::table('subject_attendance')->where('student_id', $sid)->where('subject_id', $mathId)->where('date', $today)->value('status') === 'absent');
// Raw duplicate insert blocked by UNIQUE(year,class,section,subject,date,period,student).
check('Duplicate subject attendance blocked by DB UNIQUE', throws(fn () =>
    QueryBuilder::table('subject_attendance')->insert([
        'academic_year_id' => $yearId, 'class_id' => $classId, 'section_id' => $sectionId,
        'subject_id' => $mathId, 'student_id' => $sid, 'date' => $today, 'period' => 0,
        'status' => 'present', 'created_at' => now(), 'updated_at' => now(),
    ])
));
check('studentPercentage computes (0-100)', $svc->studentPercentage($sid, $mathId) >= 0.0);

// ---------------------------------------------------------------------------
section('Document verification workflow');
$docId = QueryBuilder::table('student_documents')->insert([
    'student_id' => $sid, 'title' => 'Birth Certificate', 'type' => 'birth_certificate',
    'file_path' => $sid . '/test.pdf', 'mime_type' => 'application/pdf', 'file_size' => 1234,
    'uploaded_by' => $adminId, 'verification_status' => 'pending',
    'created_at' => now(), 'updated_at' => now(),
]);
$docSvc = new DocumentService();
check('Document starts pending', QueryBuilder::table('student_documents')->where('id', $docId)->value('verification_status') === 'pending');
$rv = $docSvc->review($docId, true, $adminId);
check('review(approve) succeeds', $rv['ok'] === true);
check('Document is now verified', QueryBuilder::table('student_documents')->where('id', $docId)->value('verification_status') === 'verified');
check('verified_by recorded', (int) QueryBuilder::table('student_documents')->where('id', $docId)->value('verified_by') === $adminId);
$docSvc->review($docId, false, $adminId);
check('review(reject) flips to rejected', QueryBuilder::table('student_documents')->where('id', $docId)->value('verification_status') === 'rejected');
check('DocumentService::TYPES has >= 15 categories', count(DocumentService::TYPES) >= 15);
$docSvc->delete($docId);
check('delete removes the document row', QueryBuilder::table('student_documents')->where('id', $docId)->first() === null);

// ---------------------------------------------------------------------------
section('Notifications');
$notif = new NotificationService();
$studentUserId = (int) ($student['user_id'] ?? 0);
$before = $notif->unreadCount($studentUserId);
$nid = $notif->notify($studentUserId, 'result', 'Results published', 'Your results are out.', '/student/results');
check('notify() creates a row', $nid > 0);
check('unreadCount increments', $notif->unreadCount($studentUserId) === $before + 1);
$notif->markRead($nid, $studentUserId);
check('markRead clears it', $notif->unreadCount($studentUserId) === $before);
$roleN = $notif->notifyRoles(['school_admin', 'principal'], 'marks', 'Marks submitted', 'Awaiting approval', '/admin/exams');
check('notifyRoles notifies >= 1 admin', $roleN >= 1);

// ---------------------------------------------------------------------------
section('Class & subject archive (history-safe)');
// Enrollment exists for the demo student (created by seeder).
$enrollBefore = QueryBuilder::table('student_enrollments')->where('student_id', $sid)->count();
ClassModel::updateById($classId, ['status' => 'archived']);
check('Class can be archived', QueryBuilder::table('classes')->where('id', $classId)->value('status') === 'archived');
check('Archiving preserves enrollments (history-safe)',
    QueryBuilder::table('student_enrollments')->where('student_id', $sid)->count() === $enrollBefore);
ClassModel::updateById($classId, ['status' => 'active']); // restore for other runs
Subject::updateById($mathId, ['status' => 'archived']);
check('Subject can be archived', QueryBuilder::table('subjects')->where('id', $mathId)->value('status') === 'archived');
check('Archiving subject preserves it (not deleted)', QueryBuilder::table('subjects')->where('id', $mathId)->first() !== null);
Subject::updateById($mathId, ['status' => 'active']);

// ---------------------------------------------------------------------------
echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll advanced-feature checks passed.\033[0m\n";
exit(0);
