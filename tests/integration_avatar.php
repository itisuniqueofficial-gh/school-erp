<?php

declare(strict_types=1);

/**
 * Secure profile-picture (AvatarService) integration test.
 *
 *   php tests/integration_avatar.php
 *
 * Uses a throwaway storage dir so it never touches real uploads, and builds
 * UploadedFile instances from GD-generated temp files (store() re-encodes from
 * the temp path, so it does not require a real HTTP upload).
 */

use App\Services\AvatarService;
use Core\Http\UploadedFile;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

$dir = sys_get_temp_dir() . '/erp_avatars_test_' . bin2hex(random_bytes(4));
$svc = new AvatarService($dir);

/** Build an UploadedFile from raw bytes written to a temp file. */
function uf(string $bytes, ?int $fakeSize = null): UploadedFile {
    $tmp = tempnam(sys_get_temp_dir(), 'up_');
    file_put_contents($tmp, $bytes);
    return new UploadedFile([
        'name' => 'x', 'type' => 'application/octet-stream',
        'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK,
        'size' => $fakeSize ?? strlen($bytes),
    ]);
}
function pngBytes(int $w = 800, int $h = 600): string {
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, imagecolorallocate($im, 30, 120, 200));
    ob_start(); imagepng($im); $b = (string) ob_get_clean(); imagedestroy($im);
    return $b;
}
function jpegBytes(): string {
    $im = imagecreatetruecolor(640, 480);
    imagefill($im, 0, 0, imagecolorallocate($im, 200, 80, 60));
    ob_start(); imagejpeg($im); $b = (string) ob_get_clean(); imagedestroy($im);
    return $b;
}

// ---------------------------------------------------------------------------
section('Rejects non-image / malicious uploads');
$php = uf("<?php system(\$_GET['c']); ?>");
$r = $svc->store($php);
check('PHP script rejected (not a valid image)', $r['ok'] === false && $r['filename'] === null);

$txt = uf("just some text, not an image");
check('Plain text rejected', $svc->store($txt)['ok'] === false);

// A real PNG but with PHP appended (polyglot) — decodes as image, but re-encode
// strips the trailing payload, so the STORED file cannot contain the PHP.
$polyglot = uf(pngBytes(64, 64) . "<?php echo 'x'; ?>");
$rp = $svc->store($polyglot);
check('Polyglot PNG accepted but re-encoded', $rp['ok'] === true);
if ($rp['ok']) {
    $stored = (string) file_get_contents($svc->path($rp['filename']));
    check('Stored file contains NO PHP payload', strpos($stored, '<?php') === false);
    check('Stored file is a valid image', @getimagesizefromstring($stored) !== false);
    $svc->delete($rp['filename']);
}

section('Rejects oversized files');
$big = uf(pngBytes(64, 64), 99 * 1024 * 1024); // report 99 MB via size()
check('Oversized upload rejected', $svc->store($big)['ok'] === false);

// ---------------------------------------------------------------------------
section('Accepts valid images + generates thumbnail');
$rpng = $svc->store(uf(pngBytes(1200, 900)));
check('PNG accepted', $rpng['ok'] === true);
check('Main file written', $svc->path($rpng['filename']) !== null);
check('Thumbnail written', $svc->path($rpng['filename'], true) !== null);
[$mw, $mh] = getimagesize($svc->path($rpng['filename']));
check('Main image capped at 512px', max($mw, $mh) <= 512);
[$tw, $th] = getimagesize($svc->path($rpng['filename'], true));
check('Thumbnail capped at 160px', max($tw, $th) <= 160);
check('mime() reports image/png', $svc->mime($rpng['filename']) === 'image/png');

if (function_exists('imagejpeg')) {
    $rjpg = $svc->store(uf(jpegBytes()));
    check('JPEG accepted + stored as .jpg', $rjpg['ok'] === true && str_ends_with((string) $rjpg['filename'], '.jpg'));
    $svc->delete($rjpg['filename'] ?? null);
} else {
    echo "  \033[33mSKIP\033[0m  JPEG encode (this GD build has no libjpeg)\n";
}

// ---------------------------------------------------------------------------
section('Delete + path traversal guard');
$svc->delete($rpng['filename']);
check('Delete removes main + thumb', $svc->path($rpng['filename']) === null && $svc->path($rpng['filename'], true) === null);
check('Path traversal (../) rejected', $svc->path('../../etc/passwd') === null);
check('Bad filename shape rejected', $svc->path('notahex.png') === null);
check('delete(null) is a safe no-op', (function () use ($svc) { $svc->delete(null); return true; })());

// cleanup
@rmdir($dir);

echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll avatar-service checks passed.\033[0m\n";
exit(0);
