<?php

declare(strict_types=1);

/**
 * Profile-photo upload pipeline test (the "not a valid image" fix).
 *
 *   php tests/integration_photoupload.php
 */

use App\Services\AvatarService;
use Core\Http\UploadedFile;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

$tmpDir = sys_get_temp_dir() . '/pu_' . bin2hex(random_bytes(4));
@mkdir($tmpDir, 0775, true);
$store = $tmpDir . '/avatars';
$svc = new AvatarService($store);

$mkUpload = static function (string $path, string $type) : UploadedFile {
    return new UploadedFile(['name' => basename($path), 'type' => $type, 'tmp_name' => $path, 'error' => 0, 'size' => (int) filesize($path)]);
};

// A real 1x1 JPEG (this GD build cannot DECODE jpeg -> exercises the fallback).
$jpeg = $tmpDir . '/one.jpg';
file_put_contents($jpeg, base64_decode(
  '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRof'
. 'Hh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwh'
. 'MjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAAR'
. 'CAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAA'
. 'AgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkK'
. 'FhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWG'
. 'h4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl'
. '5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREA'
. 'AgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYk'
. 'NOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOE'
. 'hYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk'
. '5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD3+iiigD/2Q=='
));

$png = $tmpDir . '/one.png';
$im = imagecreatetruecolor(30, 30); imagepng($im, $png); imagedestroy($im);

$fake = $tmpDir . '/fake.jpg';
file_put_contents($fake, 'this is definitely not an image');

// ---------------------------------------------------------------------------
section('Valid JPEG (GD cannot decode) still uploads via capability fallback');
$hasJpegDecoder = (bool) (gd_info()['JPEG Support'] ?? false);
$r = $svc->store($mkUpload($jpeg, 'image/jpeg'), 1048576);
check('getimagesize confirmed it is a real JPEG', @getimagesize($jpeg) !== false);
check('JPEG upload succeeds (no false "not a valid image")', $r['ok'] === true);
check('stored filename returned + file exists', !empty($r['filename']) && $svc->path($r['filename']) !== null);
echo "  (note: this GD build " . ($hasJpegDecoder ? 'HAS' : 'lacks') . " a JPEG decoder — fallback path exercised when it lacks one)\n";

// ---------------------------------------------------------------------------
section('Valid PNG uploads (optimized path)');
$rp = $svc->store($mkUpload($png, 'image/png'), 1048576);
check('PNG upload succeeds', $rp['ok'] === true && !empty($rp['filename']));
check('PNG stored file exists', $svc->path($rp['filename']) !== null);

// ---------------------------------------------------------------------------
section('Invalid / security cases still rejected');
$rf = $svc->store($mkUpload($fake, 'image/jpeg'), 1048576);
check('non-image content rejected', $rf['ok'] === false && !empty($rf['message']) && empty($rf['filename']));
// oversize
$rb = $svc->store($mkUpload($png, 'image/png'), 10); // 10-byte cap
check('oversize rejected', $rb['ok'] === false && str_contains(strtolower($rb['message']), 'size'));

// cleanup (files only)
foreach (array_merge(glob($store . '/*') ?: [], glob($tmpDir . '/*') ?: []) as $p) { if (is_file($p)) { @unlink($p); } }
@rmdir($store); @rmdir($tmpDir);

echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll photo-upload checks passed.\033[0m\n";
exit(0);
