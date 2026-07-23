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

echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll branding checks passed.\033[0m\n";
exit(0);
