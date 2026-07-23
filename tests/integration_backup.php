<?php

declare(strict_types=1);

/**
 * Backup verification test: a backup you cannot trust is not data safety.
 * Verifies BackupService::verify() correctly distinguishes valid, restorable
 * dumps from empty, non-schema, and critically-incomplete ones.
 */

require __DIR__ . '/../bootstrap/app.php';

use App\Services\BackupService;
use Core\Config\Config;

$pass = 0;
$fail = 0;
$check = static function (string $label, bool $cond) use (&$pass, &$fail): void {
    if ($cond) {
        $pass++;
    } else {
        $fail++;
        echo "FAIL  {$label}\n";
    }
};

$svc = new BackupService();
$dir = (string) Config::get('paths.backups');
if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
}

$written = [];
$put = static function (string $name, string $body) use ($dir, &$written): string {
    $path = rtrim($dir, '/') . '/' . $name;
    file_put_contents($path, $body);
    $written[] = $path;
    return $name;
};

// 1. A valid dump covering all critical tables.
$valid = "-- MySQL dump\n";
foreach (['migrations', 'users', 'students', 'certificates', 'settings'] as $t) {
    $valid .= "CREATE TABLE `{$t}` (`id` int);\n";
    $valid .= "INSERT INTO `{$t}` VALUES (1);\n";
}
$vName = $put('backup-test-valid.sql', $valid);
$rv = $svc->verify($vName);
$check('valid dump passes', $rv['ok'] === true);
$check('valid dump counts tables', $rv['tables'] === 5);
$check('valid dump counts inserts', $rv['inserts'] === 5);
$check('valid dump has no missing critical', $rv['missing'] === []);

// 2. Empty file is rejected.
$eName = $put('backup-test-empty.sql', '');
$re = $svc->verify($eName);
$check('empty dump rejected', $re['ok'] === false);
$check('empty dump message mentions empty', stripos($re['message'], 'empty') !== false);

// 3. Non-schema text (no CREATE TABLE) is rejected.
$nName = $put('backup-test-notsql.sql', "hello world\nthis is not a sql dump\n");
$rn = $svc->verify($nName);
$check('non-schema dump rejected', $rn['ok'] === false);
$check('non-schema reports zero tables', $rn['tables'] === 0);

// 4. Dump missing a critical table (no `students`) is rejected and names it.
$partial = "CREATE TABLE `migrations` (`id` int);\nCREATE TABLE `users` (`id` int);\n"
    . "CREATE TABLE `certificates` (`id` int);\nCREATE TABLE `settings` (`id` int);\n";
$pName = $put('backup-test-partial.sql', $partial);
$rp = $svc->verify($pName);
$check('partial dump rejected', $rp['ok'] === false);
$check('partial dump names missing table', in_array('students', $rp['missing'], true));

// 5. Missing file is handled gracefully (no exception).
$rm = $svc->verify('does-not-exist-xyz.sql');
$check('missing file rejected safely', $rm['ok'] === false && stripos($rm['message'], 'not found') !== false);

// 6. Path traversal is neutralized (basename applied) -> treated as not found.
$rt = $svc->verify('../../etc/passwd');
$check('path traversal neutralized', $rt['ok'] === false);

// Cleanup our fixtures only (never touch real backups).
foreach ($written as $f) {
    @unlink($f);
}

echo "PASS: {$pass}   FAIL: {$fail}\n";
exit($fail === 0 ? 0 : 1);
