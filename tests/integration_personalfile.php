<?php

declare(strict_types=1);

/**
 * Student Personal File / document-checklist test.
 * Verifies the configurable requirements, per-requirement status
 * (missing/pending/verified/rejected/not_applicable), NA handling (optional
 * docs never reported missing) and the completion summary.
 */

require __DIR__ . '/../bootstrap/app.php';

use App\Models\Student;
use App\Services\PersonalFileService;
use Core\Database\QueryBuilder;

$pass = 0; $fail = 0;
$check = static function (string $l, bool $c) use (&$pass, &$fail): void {
    if ($c) { $pass++; } else { $fail++; echo "FAIL  {$l}\n"; }
};

$svc = new PersonalFileService();

// Requirements were seeded (15 default checklist items).
$reqs = $svc->requirements();
$check('15 default requirements seeded', count($reqs) === 15);
$codes = array_column($reqs, 'code');
$check('checklist includes SSC marksheet', in_array('ssc_marksheet', $codes, true));
$check('EWS is optional (is_required=0)', (int) (QueryBuilder::table('document_requirements')->where('code', 'ews_certificate')->value('is_required')) === 0);

// Fresh student.
$yearId = (int) QueryBuilder::table('academic_years')->value('id');
$classId = (int) QueryBuilder::table('classes')->value('id');
$sid = Student::create(['uid' => (string) (260000000000 + random_int(1, 899999)), 'admission_no' => 'PF-' . uniqid(), 'first_name' => 'Pf', 'last_name' => 'Test', 'gender' => 'other', 'class_id' => $classId, 'academic_year_id' => $yearId, 'status' => 'active']);

// All missing initially.
$cl0 = $svc->checklist($sid);
$check('checklist has 15 rows for the student', count($cl0) === 15);
$check('all requirements start missing', count(array_filter($cl0, fn ($i) => $i['status'] === 'missing')) === 15);
$sum0 = $svc->summary($sid);
$check('summary applicable = 13 required items', $sum0['applicable'] === 13); // 15 - 2 optional (cbse_migration, ews)
$check('summary 0% when nothing verified', $sum0['percent'] === 0);
$check('summary not complete initially', $sum0['complete'] === false);

$docRow = static function (int $studentId, string $code, string $status) {
    QueryBuilder::table('student_documents')->insert([
        'uid' => substr(bin2hex(random_bytes(6)), 0, 12), 'student_id' => $studentId,
        'title' => $code, 'type' => $code, 'file_path' => 'x/' . $code . '.pdf',
        'mime_type' => 'application/pdf', 'file_size' => 100, 'uploaded_by' => null,
        'verification_status' => $status, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
    ]);
};

// Upload (pending) SSC, verify HSC, reject Aadhaar.
$docRow($sid, 'ssc_marksheet', 'pending');
$docRow($sid, 'hsc_marksheet', 'verified');
$docRow($sid, 'aadhaar', 'rejected');

$byCode = [];
foreach ($svc->checklist($sid) as $it) { $byCode[$it['code']] = $it['status']; }
$check('uploaded-unverified doc shows pending', $byCode['ssc_marksheet'] === 'pending');
$check('verified doc shows verified', $byCode['hsc_marksheet'] === 'verified');
$check('rejected doc shows rejected', $byCode['aadhaar'] === 'rejected');
$check('untouched requirement still missing', $byCode['seat_acceptance'] === 'missing');

// Mark the optional EWS as not applicable -> not counted as missing.
$ok = $svc->markNotApplicable($sid, 'ews_certificate', 'General category', null);
$check('markNotApplicable succeeds for a valid code', $ok === true);
$check('markNotApplicable rejects an unknown code', $svc->markNotApplicable($sid, 'nonexistent_code', 'x', null) === false);
$byCode2 = [];
foreach ($svc->checklist($sid) as $it) { $byCode2[$it['code']] = $it['status']; }
$check('NA requirement shows not_applicable', $byCode2['ews_certificate'] === 'not_applicable');

$sum = $svc->summary($sid);
$check('summary counts 1 verified', $sum['verified'] === 1);
$check('summary counts >=1 pending', $sum['pending'] >= 1);
$check('summary counts >=1 rejected', $sum['rejected'] >= 1);
$check('summary counts the NA item', $sum['not_applicable'] === 1);
$check('percent = verified/applicable (1/13 ≈ 8)', $sum['percent'] === (int) round((1 / 13) * 100));

// Clearing NA restores it to missing.
$svc->clearNotApplicable($sid, 'ews_certificate');
$byCode3 = [];
foreach ($svc->checklist($sid) as $it) { $byCode3[$it['code']] = $it['status']; }
$check('clearNotApplicable restores missing', $byCode3['ews_certificate'] === 'missing');

echo "PASS: {$pass}   FAIL: {$fail}\n";
exit($fail === 0 ? 0 : 1);
