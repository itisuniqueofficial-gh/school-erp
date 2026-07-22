<?php

declare(strict_types=1);

/**
 * Digital certificate system test.
 *
 *   php bin/console migrate:fresh && php bin/console seed
 *   php tests/integration_certificate.php
 */

use App\Models\Student;
use App\Services\CertificatePdfService;
use App\Services\CertificateService;
use Core\Database\Connection;
use Core\Database\QueryBuilder;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

$adminId = (int) QueryBuilder::table('users')->where('email', 'superadmin@school.test')->value('id');
auth()->onceUsingId($adminId);
$svc = new CertificateService();

// ---------------------------------------------------------------------------
section('Schema + RBAC');
$cols = array_column(Connection::instance()->select('SHOW COLUMNS FROM certificates'), 'Field');
foreach (['cert_uid', 'type', 'student_id', 'body', 'data_json', 'status', 'issued_by', 'issued_on'] as $col) {
    check("certificates.$col exists", in_array($col, $cols, true));
}
check('certificates.view permission seeded', QueryBuilder::table('permissions')->where('slug', 'certificates.view')->first() !== null);
check('certificates.manage permission seeded', QueryBuilder::table('permissions')->where('slug', 'certificates.manage')->first() !== null);

// Fixture student + guardian.
$yearId = (int) QueryBuilder::table('academic_years')->value('id');
$classId = (int) QueryBuilder::table('classes')->value('id');
$sid = Student::create(['uid' => (string) (220000000000 + random_int(1, 899999)), 'admission_no' => 'CRT-' . uniqid(), 'first_name' => 'Cathy', 'last_name' => 'Cert', 'gender' => 'female', 'class_id' => $classId, 'academic_year_id' => $yearId, 'status' => 'active']);
$gid = (int) QueryBuilder::table('guardians')->insert(['name' => 'Mr Cert', 'relation' => 'father', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
QueryBuilder::table('student_guardians')->insert(['student_id' => $sid, 'guardian_id' => $gid, 'is_primary' => 1]);

// ---------------------------------------------------------------------------
section('Issue with unique ID + auto-populated body');
$c1 = $svc->issue($sid, 'bonafide');
check('bonafide issued with CERT-YYYY-NNNNN id', preg_match('/^CERT-\d{4}-\d+$/', (string) $c1) === 1);
$row = $svc->find($c1);
check('row persisted', $row !== null);
check('body auto-populated with student name', str_contains((string) $row['body'], 'Cathy Cert'));
check('body includes school branding', str_contains((string) $row['body'], 'Greenfield International School'));
check('body includes class + guardian', str_contains((string) $row['body'], 'Mr Cert'));
check('data snapshot stored (json)', is_array(json_decode((string) $row['data_json'], true)));
$c2 = $svc->issue($sid, 'transfer');
check('second cert has a DIFFERENT unique id', $c2 !== $c1 && preg_match('/^CERT-\d{4}-\d+$/', (string) $c2) === 1);

// ---------------------------------------------------------------------------
section('Custom certificate rules');
check('custom without body is rejected', $svc->issue($sid, 'custom') === null);
$c3 = $svc->issue($sid, 'custom', ['body' => 'Special award for {name} of Class {class}.']);
check('custom body placeholders are filled', str_contains((string) $svc->find($c3)['body'], 'Cathy Cert'));
check('invalid type rejected', $svc->issue($sid, 'nonsense') === null);

// ---------------------------------------------------------------------------
section('Real one-click PDF');
$doc = $svc->document($c1);
$pdf = (new CertificatePdfService())->render($doc);
check('output is a real PDF (%PDF-)', str_starts_with($pdf, '%PDF-'));
check('PDF non-trivial + has EOF', strlen($pdf) > 1500 && str_contains($pdf, '%%EOF'));
check('verify_url contains the cert id', str_contains((string) $doc['verify_url'], (string) $c1));

// ---------------------------------------------------------------------------
section('Public verification (non-sensitive only)');
$v = $svc->verify($c1);
check('verify returns data + valid', $v !== null && $v['valid'] === true);
check('verify exposes student name + type', isset($v['student_name'], $v['title']));
check('verify does NOT expose body/data_json', !array_key_exists('body', $v) && !array_key_exists('data_json', $v));
check('verify(unknown) returns null', $svc->verify('CERT-0000-999999') === null);

// ---------------------------------------------------------------------------
section('Revocation (record preserved)');
check('revoke succeeds', $svc->revoke($c1, 'Issued in error') === true);
check('row still exists after revoke', $svc->find($c1) !== null);
check('status is revoked', $svc->find($c1)['status'] === 'revoked');
check('verify now reports invalid', $svc->verify($c1)['valid'] === false);
check('re-revoke is a no-op (already revoked)', $svc->revoke($c1, 'again') === false);

echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll certificate checks passed.\033[0m\n";
exit(0);
