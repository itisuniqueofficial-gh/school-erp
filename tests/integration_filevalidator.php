<?php

declare(strict_types=1);

/**
 * Centralized FileValidator test.
 *
 *   php tests/integration_filevalidator.php
 */

use App\Services\FileValidator;

$app = require dirname(__DIR__) . '/bootstrap/app.php';

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }

$tmp = sys_get_temp_dir() . '/fv_' . bin2hex(random_bytes(4));
@mkdir($tmp, 0775, true);
$v = new FileValidator();

// Build fixture files.
$png = $tmp . '/pic.png';
$im = imagecreatetruecolor(40, 40); imagepng($im, $png); imagedestroy($im);
$pdf = $tmp . '/doc.pdf'; file_put_contents($pdf, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
$txt = $tmp . '/notes.txt'; file_put_contents($txt, "plain notes, nothing dangerous");
$polyglot = $tmp . '/evil.png'; file_put_contents($polyglot, "\x89PNG\r\n\x1a\n<?php system(\$_GET['c']); ?>");
$fakePng = $tmp . '/fake.png'; file_put_contents($fakePng, "just plain text pretending to be a png image");
$php = $tmp . '/shell.php'; file_put_contents($php, "<?php echo 1; ?>");
$big = $tmp . '/big.png'; $imb = imagecreatetruecolor(50, 50); imagepng($imb, $big); imagedestroy($imb);

// ---------------------------------------------------------------------------
section('Valid files pass');
check('valid PNG passes image category', $v->inspect($png, 'image', 'pic.png')['ok'] === true);
check('valid PDF passes document category', $v->inspect($pdf, 'document', 'doc.pdf')['ok'] === true);
check('plain TXT passes document category', $v->inspect($txt, 'document', 'notes.txt')['ok'] === true);
check('detected mime returned', ($v->inspect($png, 'image', 'pic.png')['mime'] ?? '') === 'image/png');

// ---------------------------------------------------------------------------
section('Security rejections');
$r = $v->inspect($polyglot, 'image', 'evil.png');
check('PHP-in-PNG polyglot rejected', $r['ok'] === false);
$r2 = $v->inspect($fakePng, 'image', 'fake.png');
check('non-image content with image extension rejected', $r2['ok'] === false);
$r3 = $v->inspect($php, 'document', 'shell.php');
check('dangerous .php extension rejected', $r3['ok'] === false && str_contains($r3['reason'], 'security'));
$r4 = $v->inspect($png, 'image', 'pic.png', 100); // 100-byte cap
check('oversize rejected with size message', $r4['ok'] === false && str_contains($r4['reason'], 'Maximum allowed size'));
check('unknown category rejected', $v->inspect($png, 'nonsense', 'pic.png')['ok'] === false);
check('missing file rejected', $v->inspect($tmp . '/nope.png', 'image', 'nope.png')['ok'] === false);

// ---------------------------------------------------------------------------
section('Extension whitelist per category');
$r5 = $v->inspect($pdf, 'image', 'doc.pdf'); // pdf under image category
check('pdf not allowed under image category', $r5['ok'] === false);

// cleanup
array_map('unlink', glob($tmp . '/*') ?: []); @rmdir($tmp);

echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) { echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n"; exit(1); }
echo "\033[32mAll file-validator checks passed.\033[0m\n";
exit(0);
