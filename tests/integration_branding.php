<?php

declare(strict_types=1);

/**
 * School branding (logo) integration test.
 *
 *   APP_TESTING=1 php bin/console migrate:fresh && APP_TESTING=1 php bin/console seed
 *   APP_TESTING=1 php tests/integration_branding.php
 */

use App\Models\Student;
use App\Services\AvatarService;
use App\Services\CertificatePdfService;
use App\Services\CertificateService;
use App\Services\ReportCardDocumentService;
use Core\Database\QueryBuilder;
use Core\Http\UploadedFile;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

$adminId = (int) QueryBuilder::table('users')->where('email', 'superadmin@school.test')->value('id');
auth()->onceUsingId($adminId);

// Create a logo PNG via AvatarService (PNG encodes on this GD build).
$tmp = sys_get_temp_dir() . '/logo_' . bin2hex(random_bytes(3)) . '.png';
$im = imagecreatetruecolor(120, 120); imagepng($im, $tmp); imagedestroy($im);
$up = new UploadedFile(['name' => 'logo.png', 'type' => 'image/png', 'tmp_name' => $tmp, 'error' => 0, 'size' => (int) filesize($tmp)]);
$res = (new AvatarService())->store($up, 5242880);
@unlink($tmp);

// Persist as the school_logo setting (upsert).
$svcName = 'school_logo';
if (QueryBuilder::table('settings')->where('key', $svcName)->first() !== null) {
    QueryBuilder::table('settings')->where('key', $svcName)->update(['value' => $res['filename'], 'updated_at' => date('Y-m-d H:i:s')]);
} else {
    QueryBuilder::table('settings')->insert(['group' => 'general', 'key' => $svcName, 'value' => $res['filename'], 'type' => 'string', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
}

// ---------------------------------------------------------------------------
section('branding() resolves the logo');
$b = (new ReportCardDocumentService())->branding();
check('logo stored + upload ok', $res['ok'] === true && !empty($res['filename']));
check('branding.logo_file set', ($b['logo_file'] ?? '') === $res['filename']);
check('branding.logo resolves to an existing file', !empty($b['logo']) && is_file((string) $b['logo']));

// ---------------------------------------------------------------------------
section('Logo renders in a certificate PDF (single page, valid)');
$yearId = (int) QueryBuilder::table('academic_years')->value('id');
$classId = (int) QueryBuilder::table('classes')->value('id');
$sid = Student::create(['uid' => (string) (220000000000 + random_int(1, 899999)), 'admission_no' => 'BR-' . uniqid(), 'first_name' => 'Brand', 'last_name' => 'Test', 'gender' => 'other', 'class_id' => $classId, 'academic_year_id' => $yearId, 'status' => 'active']);
$cu = (new CertificateService())->issue($sid, 'bonafide', ['purpose' => 'Bank']);
$pdf = (new CertificatePdfService())->render((new CertificateService())->document($cu));
check('certificate PDF valid (%PDF + EOF)', str_starts_with($pdf, '%PDF-') && str_contains($pdf, '%%EOF'));
check('certificate PDF single page', preg_match_all('#/Type\s*/Page[^s]#', $pdf) === 1);

// ---------------------------------------------------------------------------
section('Fallback when logo removed');
QueryBuilder::table('settings')->where('key', 'school_logo')->update(['value' => '']);
$b2 = (new ReportCardDocumentService())->branding();
check('logo null when unset (safe fallback)', ($b2['logo'] ?? null) === null);
$pdf2 = (new CertificatePdfService())->render((new CertificateService())->document($cu));
check('certificate still renders without a logo', str_starts_with($pdf2, '%PDF-'));

// ---------------------------------------------------------------------------
section('Stamp + signature assets resolve and render (with pending fallback)');

$mkPng = static function (): string {
    $t = sys_get_temp_dir() . '/asset_' . bin2hex(random_bytes(3)) . '.png';
    $im = imagecreatetruecolor(160, 80); imagepng($im, $t); imagedestroy($im);
    return $t;
};
$putSetting = static function (string $key, string $val): void {
    if (QueryBuilder::table('settings')->where('key', $key)->first() !== null) {
        QueryBuilder::table('settings')->where('key', $key)->update(['value' => $val, 'updated_at' => date('Y-m-d H:i:s')]);
    } else {
        QueryBuilder::table('settings')->insert(['group' => 'general', 'key' => $key, 'value' => $val, 'type' => 'string', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
    }
};

$svc = new AvatarService();
$stTmp = $mkPng();
$stRes = $svc->store(new UploadedFile(['name' => 'stamp.png', 'type' => 'image/png', 'tmp_name' => $stTmp, 'error' => 0, 'size' => (int) filesize($stTmp)]), 2097152);
@unlink($stTmp);
$sgTmp = $mkPng();
$sgRes = $svc->store(new UploadedFile(['name' => 'sig.png', 'type' => 'image/png', 'tmp_name' => $sgTmp, 'error' => 0, 'size' => (int) filesize($sgTmp)]), 2097152);
@unlink($sgTmp);
$putSetting('school_stamp', (string) $stRes['filename']);
$putSetting('principal_signature', (string) $sgRes['filename']);

$b3 = (new ReportCardDocumentService())->branding();
check('branding.stamp resolves to an existing file', !empty($b3['stamp']) && is_file((string) $b3['stamp']));
check('branding.signature resolves to an existing file', !empty($b3['signature']) && is_file((string) $b3['signature']));

$render = static function (array $bb): string {
    $pdf = new \App\Services\Pdf\PdfDocument('P', 'mm', 'A4');
    $pdf->SetAutoPageBreak(false);
    $pdf->AddPage();
    $pdf->officialHeader($bb, 22);
    $pdf->stampBox(20, 200, 45, 24, $bb['stamp'] ?? null);
    $pdf->signatureLine(135, 220, 60, 'Authorized Signatory', $bb['signature'] ?? null);
    return $pdf->Output('S');
};
$withAssets = $render($b3);
check('PDF renders with stamp + signature images (%PDF)', str_starts_with($withAssets, '%PDF-') && str_contains($withAssets, '%%EOF'));

// Pending fallback when both are removed.
$putSetting('school_stamp', '');
$putSetting('principal_signature', '');
$b4 = (new ReportCardDocumentService())->branding();
check('stamp null when unset', ($b4['stamp'] ?? null) === null);
check('signature null when unset', ($b4['signature'] ?? null) === null);
$withPending = $render($b4);
check('PDF renders "Pending" fallback safely (%PDF)', str_starts_with($withPending, '%PDF-') && str_contains($withPending, '%%EOF'));

// ---------------------------------------------------------------------------
section('Historical branding snapshot (issued certificate keeps its branding)');
$putSetting('school_name', 'Original Academy');
$sid2 = Student::create(['uid' => (string) (230000000000 + random_int(1, 899999)), 'admission_no' => 'SNAP-' . uniqid(), 'first_name' => 'Snap', 'last_name' => 'Shot', 'gender' => 'other', 'class_id' => $classId, 'academic_year_id' => $yearId, 'status' => 'active']);
$cu2 = (new CertificateService())->issue($sid2, 'bonafide', ['purpose' => 'Snapshot']);
$docBefore = (new CertificateService())->document($cu2);
check('snapshot captured the name in effect at issue', ($docBefore['branding']['name'] ?? '') === 'Original Academy');

$rawSnap = QueryBuilder::table('certificates')->where('cert_uid', $cu2)->value('branding_snapshot');
check('branding_snapshot persisted as JSON', is_string($rawSnap) && str_contains((string) $rawSnap, 'Original Academy'));

// Rename the school AFTER issuing.
$putSetting('school_name', 'Renamed Institute');
$docAfter = (new CertificateService())->document($cu2);
check('issued certificate keeps its historical name after the rename', ($docAfter['branding']['name'] ?? '') === 'Original Academy');
check('re-render still valid PDF with historical branding', str_starts_with((new CertificatePdfService())->render($docAfter), '%PDF-'));

// A certificate issued AFTER the rename captures the new name.
$cu3 = (new CertificateService())->issue($sid2, 'bonafide', ['purpose' => 'New']);
$docNew = (new CertificateService())->document($cu3);
check('new certificate captures the updated name', ($docNew['branding']['name'] ?? '') === 'Renamed Institute');

echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll branding checks passed.\033[0m\n";
exit(0);
