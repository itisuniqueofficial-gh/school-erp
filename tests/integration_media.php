<?php

declare(strict_types=1);

/**
 * Public/private media split test.
 *
 * The positive branding-serve path is checked in-process here; the security
 * negatives (private student photo NOT exposed publicly, /media/avatar still
 * requires auth) are verified over HTTP in the CI/smoke curl checks because
 * abort() terminates the process and cannot be caught in-process.
 */

require __DIR__ . '/../bootstrap/app.php';

use App\Controllers\MediaController;
use App\Services\AvatarService;
use Core\Database\QueryBuilder;
use Core\Http\Request;
use Core\Http\UploadedFile;

$pass = 0; $fail = 0;
$check = static function (string $l, bool $c) use (&$pass, &$fail): void {
    if ($c) { $pass++; } else { $fail++; echo "FAIL  {$l}\n"; }
};

// Store a logo and register it as the school_logo setting.
$tmp = sys_get_temp_dir() . '/ml_' . bin2hex(random_bytes(3)) . '.png';
$im = imagecreatetruecolor(64, 64); imagepng($im, $tmp); imagedestroy($im);
$res = (new AvatarService())->store(new UploadedFile(['name' => 'l.png', 'type' => 'image/png', 'tmp_name' => $tmp, 'error' => 0, 'size' => (int) filesize($tmp)]), 5242880);
@unlink($tmp);
$logo = (string) $res['filename'];
if (QueryBuilder::table('settings')->where('key', 'school_logo')->first() !== null) {
    QueryBuilder::table('settings')->where('key', 'school_logo')->update(['value' => $logo, 'updated_at' => date('Y-m-d H:i:s')]);
} else {
    QueryBuilder::table('settings')->insert(['group' => 'general', 'key' => 'school_logo', 'value' => $logo, 'type' => 'string', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
}

// Also store an UNREGISTERED file (stands in for a private student photo).
$tmp2 = sys_get_temp_dir() . '/mp_' . bin2hex(random_bytes(3)) . '.png';
$im2 = imagecreatetruecolor(64, 64); imagepng($im2, $tmp2); imagedestroy($im2);
$priv = (string) (new AvatarService())->store(new UploadedFile(['name' => 'p.png', 'type' => 'image/png', 'tmp_name' => $tmp2, 'error' => 0, 'size' => (int) filesize($tmp2)]), 5242880)['filename'];
@unlink($tmp2);

// brand_logo_url() must point at the PUBLIC branding route (not the auth'd avatar route).
$url = brand_logo_url();
$check('brand_logo_url uses the public /media/branding route', is_string($url) && str_contains($url, '/media/branding/'));
$check('brand_logo_url is cache-busted', is_string($url) && str_contains($url, '?v='));

// Registered branding file is served with image bytes and HTTP 200.
$ctrl = new MediaController();
$resp = $ctrl->branding(Request::capture(), pathinfo($logo, PATHINFO_FILENAME));
$check('registered logo served (HTTP 200)', $resp->getStatus() === 200);
$check('served with non-empty image bytes', strlen($resp->getContent()) > 0 && str_starts_with($resp->getContent(), "\x89PNG"));

// The unregistered (private) file must NOT match the branding allow-list.
$registered = QueryBuilder::table('settings')->whereIn('key', ['school_logo', 'school_stamp', 'principal_signature'])->get();
$allowedIds = [];
foreach ($registered as $r) { $val = (string) ($r['value'] ?? ''); if ($val !== '') { $allowedIds[] = pathinfo($val, PATHINFO_FILENAME); } }
$check('registered logo is on the public allow-list', in_array(pathinfo($logo, PATHINFO_FILENAME), $allowedIds, true));
$check('private photo is NOT on the public allow-list', !in_array(pathinfo($priv, PATHINFO_FILENAME), $allowedIds, true));

// cleanup private fixture (leave logo for the smoke if reused)
(new AvatarService())->delete($priv);

echo "PASS: {$pass}   FAIL: {$fail}\n";
exit($fail === 0 ? 0 : 1);
