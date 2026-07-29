<?php

declare(strict_types=1);

/**
 * Installer idempotency & recovery test.
 *
 * Proves the migration runner is safe to re-run over a fully- or partially-
 * applied database (the real-world Koyeb / interrupted-install scenario):
 *  - a second run on a complete DB is a clean no-op;
 *  - if a migration's tracking row is lost but its objects already exist
 *    (duplicate column / table / index), the re-run tolerates those and
 *    completes instead of throwing SQLSTATE 42S21 / 42S01 / 42000.
 */

require __DIR__ . '/../bootstrap/app.php';

use Core\Database\Connection;
use Database\Migrator;

$pass = 0; $fail = 0;
$check = static function (string $l, bool $c) use (&$pass, &$fail): void {
    if ($c) { $pass++; } else { $fail++; echo "FAIL  {$l}\n"; }
};

$conn = Connection::instance();
$m = new Migrator();

// 1. Second run on a fully-migrated DB is a no-op.
$ran = $m->migrate();
$check('re-running migrate on a complete DB is a no-op', $ran === []);

// 2. Partial recovery: forget a migration that ALTERs existing tables (016),
//    leaving its columns/indexes in place. Re-running must tolerate the
//    duplicate-column / duplicate-key errors and re-record it — never throw.
$conn->statement("DELETE FROM `migrations` WHERE `migration` = '016_section_optional.sql'");
$threw = false;
$ranAgain = [];
try {
    $ranAgain = $m->migrate();
} catch (\Throwable $e) {
    $threw = true;
    echo '  exception: ' . $e->getMessage() . "\n";
}
$check('re-applying a partially-applied ALTER migration does not throw', $threw === false);
$check('the migration is re-recorded after recovery', in_array('016_section_optional.sql', $ranAgain, true));
$check('016 present in migrations table again', (int) ($conn->selectOne("SELECT COUNT(*) c FROM `migrations` WHERE `migration`='016_section_optional.sql'")['c'] ?? 0) === 1);

// 3. Partial recovery for a CREATE TABLE migration (table already exists -> 1050).
$conn->statement("DELETE FROM `migrations` WHERE `migration` = '022_certificates.sql'");
$threw2 = false;
try {
    $m->migrate();
} catch (\Throwable $e) {
    $threw2 = true;
    echo '  exception: ' . $e->getMessage() . "\n";
}
$check('re-applying a migration whose table already exists does not throw', $threw2 === false);
$check('certificates table still intact', (int) ($conn->selectOne("SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='certificates'")['c'] ?? 0) === 1);

// 4. install.log captures activity.
$logDir = (string) \Core\Config\Config::get('paths.logs');
$check('install.log exists', is_file(rtrim($logDir, '/') . '/install.log'));

// 5. DB-aware installed detection (backs the ephemeral-filesystem-safe gate):
//    a migrated + seeded database reports installed, so a redeploy on a fresh
//    container filesystem is NOT bounced back to the installer.
$check('InstallService reports installed on a seeded DB', (new \App\Services\InstallService())->isInstalled() === true);

echo "PASS: {$pass}   FAIL: {$fail}\n";
exit($fail === 0 ? 0 : 1);
