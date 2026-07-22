<?php

declare(strict_types=1);

/**
 * Certificate request & approval workflow test.
 *
 *   php bin/console migrate:fresh && php bin/console seed
 *   php tests/integration_certrequest.php
 */

use App\Models\Student;
use App\Services\CertificateRequestService;
use App\Services\CertificateService;
use Core\Database\Connection;
use Core\Database\QueryBuilder;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

$adminId = (int) QueryBuilder::table('users')->where('email', 'superadmin@school.test')->value('id');
auth()->onceUsingId($adminId);
$svc = new CertificateRequestService();
$certSvc = new CertificateService();

// ---------------------------------------------------------------------------
section('Schema');
$cols = array_column(Connection::instance()->select('SHOW COLUMNS FROM certificate_requests'), 'Field');
foreach (['req_uid', 'student_id', 'type', 'purpose', 'reason', 'status', 'certificate_id', 'cert_uid', 'reviewed_by'] as $c) {
    check("certificate_requests.$c exists", in_array($c, $cols, true));
}

$yearId = (int) QueryBuilder::table('academic_years')->value('id');
$classId = (int) QueryBuilder::table('classes')->value('id');
$sidA = Student::create(['uid' => (string) (220000000000 + random_int(1, 899999)), 'admission_no' => 'RQ-' . uniqid(), 'first_name' => 'Rita', 'last_name' => 'Req', 'gender' => 'female', 'class_id' => $classId, 'academic_year_id' => $yearId, 'status' => 'active']);
$sidB = Student::create(['uid' => (string) (220000000000 + random_int(1, 899999)), 'admission_no' => 'RQ-' . uniqid(), 'first_name' => 'Ben', 'last_name' => 'Req', 'gender' => 'male', 'class_id' => $classId, 'academic_year_id' => $yearId, 'status' => 'active']);

// ---------------------------------------------------------------------------
section('Create request + duplicate prevention');
$req = $svc->create($sidA, 'bonafide', 'Scholarship', 'National merit scholarship');
check('request created with REQ-YYYY-NNNNN id', preg_match('/^REQ-\d{4}-\d+$/', (string) $req) === 1);
check('status starts submitted', $svc->find($req)['status'] === 'submitted');
check('duplicate OPEN request for same type is blocked', $svc->create($sidA, 'bonafide', 'Bank Account', '') === null);
check('invalid (non-requestable) type rejected', $svc->create($sidA, 'custom', 'X', '') === null);
check('a different type is allowed', $svc->create($sidA, 'study', 'Examination', '') !== null);

// ---------------------------------------------------------------------------
section('Approval auto-issues a real certificate and links it');
$certBefore = (int) QueryBuilder::table('certificates')->where('student_id', $sidA)->count();
$certUid = $svc->approve($req, 'Verified enrolment');
check('approve returns a CERT id', preg_match('/^CERT-\d{4}-\d+$/', (string) $certUid) === 1);
$reqRow = $svc->find($req);
check('request status is now generated', $reqRow['status'] === 'generated');
check('request linked to the issued certificate', $reqRow['cert_uid'] === $certUid && (int) $reqRow['certificate_id'] > 0);
check('reviewer recorded', (int) $reqRow['reviewed_by'] === $adminId);
check('a certificate row was actually created', (int) QueryBuilder::table('certificates')->where('student_id', $sidA)->count() === $certBefore + 1);
$cert = $certSvc->find($certUid);
check('purpose reflected in certificate body', str_contains((string) $cert['body'], 'Scholarship'));
check('re-approving a generated request is a no-op', $svc->approve($req) === null);

// ---------------------------------------------------------------------------
section('Reject + cancel');
$req2 = $svc->create($sidB, 'bonafide', 'Passport', '');
check('reject succeeds with note', $svc->reject($req2, 'Incomplete details') === true);
check('rejected status + note stored', $svc->find($req2)['status'] === 'rejected' && $svc->find($req2)['review_note'] === 'Incomplete details');
check('cannot approve a rejected request', $svc->approve($req2) === null);
$req3 = $svc->create($sidB, 'study', 'Examination', '');
check('student can cancel own open request', $svc->cancel($req3, $sidB) === true);
check('cancelled status set', $svc->find($req3)['status'] === 'cancelled');

// ---------------------------------------------------------------------------
section('Authorization isolation');
$req4 = $svc->create($sidA, 'character', 'College/University Admission', '');
check('student B CANNOT cancel student A\'s request', $svc->cancel($req4, $sidB) === false);
check('student A CAN cancel own request', $svc->cancel($req4, $sidA) === true);
// The certificate download path the portal relies on: cert must match student_id.
$mismatch = QueryBuilder::table('certificates')->where('cert_uid', $certUid)->where('student_id', $sidB)->first();
$match = QueryBuilder::table('certificates')->where('cert_uid', $certUid)->where('student_id', $sidA)->first();
check('another student cannot resolve the certificate', $mismatch === null);
check('owner can resolve the certificate', $match !== null);

echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll certificate-request checks passed.\033[0m\n";
exit(0);
