<?php

declare(strict_types=1);

/**
 * Installation, configuration, database-portability, backup and storage tests.
 *
 *   APP_TESTING=1 php bin/console migrate:fresh && APP_TESTING=1 php bin/console seed
 *   APP_TESTING=1 php tests/integration_setup_portability.php
 *
 * Runs against the isolated "<db>_test" database only. PostgreSQL itself is exercised by
 * tests/tools (translated DDL + captured queries on a real server), not here: this PHP build has
 * no pdo_pgsql, so PostgreSQL runtime behaviour is reported as NOT TESTED.
 */

use App\Services\Portability\PortableDumpService;
use App\Services\Setup\SetupException;
use App\Services\Setup\SetupService;
use Core\Config\Config;
use Core\Database\Connection;
use Core\Database\DatabaseConfig;
use Core\Database\Dialect;
use Core\Database\QueryBuilder;
use Core\Database\Schema\PostgresTranslator;
use Core\Database\SchemaInspector;
use Core\Storage\LocalStorage;
use Core\Storage\S3Storage;
use Core\Support\Env;

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$root = dirname(__DIR__);

$pass = 0; $fail = 0; $failures = [];
function check(string $l, bool $c): void { global $pass,$fail,$failures; if($c){$pass++;echo "  \033[32mPASS\033[0m  {$l}\n";}else{$fail++;$failures[]=$l;echo "  \033[31mFAIL\033[0m  {$l}\n";} }
function section(string $t): void { echo "\n\033[36m== {$t} ==\033[0m\n"; }
function throws(callable $fn, ?string $contains = null): bool { try { $fn(); return false; } catch (\Throwable $e) { return $contains === null || stripos($e->getMessage(), $contains) !== false; } }

$dbName = (string) config('database.connections.' . config('database.default') . '.database');
// Disposable databases only: the "_test" database (APP_TESTING=1) or an APP_ENV=testing environment such as CI.
if (!str_ends_with($dbName, '_test') && (string) config('app.env') !== 'testing') {
    fwrite(STDERR, "Refusing to run against non-test database '{$dbName}'. Use APP_TESTING=1 (or APP_ENV=testing).\n");
    exit(2);
}
$conn = Connection::instance();
$tmp = sys_get_temp_dir() . '/erp-setup-test-' . bin2hex(random_bytes(4));
mkdir($tmp, 0775, true);

// ---------------------------------------------------------------------------
section('DatabaseConfig: connection URL, env and manual parsing');
$u = DatabaseConfig::fromUrl('postgres://erp_user:p%40ss%3Aw%2Frd@db.example.com:6543/school_erp?sslmode=verify-full&schema=erp');
check('postgres URL -> pgsql engine', $u->engine === 'pgsql' && $u->pdoDriver() === 'pgsql' && $u->isPostgres());
check('URL credentials are percent-decoded', $u->username === 'erp_user' && $u->password() === 'p@ss:w/rd');
check('URL host/port/database/ssl/schema parsed', $u->host === 'db.example.com' && $u->port === 6543 && $u->database === 'school_erp' && $u->sslMode === 'verify-full' && $u->schema === 'erp');
check('pgsql DSN has sslmode and no password', str_contains($u->dsn(), 'sslmode=verify-full') && !str_contains($u->dsn(), 'p@ss') && str_contains($u->dsn(), 'dbname=school_erp'));
$m = DatabaseConfig::fromUrl('mariadb://root:secret@127.0.0.1/erp');
check('mariadb URL uses the mysql PDO driver and default port 3306', $m->engine === 'mariadb' && $m->pdoDriver() === 'mysql' && $m->port === 3306 && str_starts_with($m->dsn(), 'mysql:host=127.0.0.1;port=3306;dbname=erp'));
check('mysql URL default port', DatabaseConfig::fromUrl('mysql://u:p@h/db')->port === 3306 && DatabaseConfig::fromUrl('postgresql://u:p@h/db')->port === 5432);
$sock = DatabaseConfig::fromArray(['engine' => 'mysql', 'socket' => '/var/run/mysqld/mysqld.sock', 'database' => 'erp', 'username' => 'u']);
check('socket DSN replaces host/port', str_starts_with($sock->dsn(), 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=erp') && !str_contains($sock->dsn(), 'host='));
check('describe() never contains the password', !str_contains(json_encode($u->describe()), 'p@ss') && ($u->describe()['password'] ?? '') === '********');
check('invalid engine rejected', throws(fn () => DatabaseConfig::fromUrl('oracle://u:p@h/db'), 'Unsupported'));
$echoed = false;
try { DatabaseConfig::fromUrl('not a url secret123'); } catch (\Throwable $e) { $echoed = str_contains($e->getMessage(), 'secret123'); }
check('garbage URL rejected without echoing it', throws(fn () => DatabaseConfig::fromUrl('not a url secret123')) && !$echoed);
check('port out of range rejected', throws(fn () => DatabaseConfig::fromArray(['engine' => 'mysql', 'host' => 'h', 'port' => 70000, 'database' => 'd']), 'port'));
check('database name injection rejected', throws(fn () => DatabaseConfig::fromArray(['engine' => 'mysql', 'host' => 'h', 'database' => 'x`; DROP DATABASE y;--']), 'Database name'));
check('DSN-injection via host rejected', throws(fn () => DatabaseConfig::fromArray(['engine' => 'mysql', 'host' => 'h;dbname=other', 'database' => 'd'])));
check('invalid SSL mode rejected', throws(fn () => DatabaseConfig::fromArray(['engine' => 'pgsql', 'host' => 'h', 'database' => 'd', 'ssl_mode' => 'maybe']), 'SSL'));
$envVals = ['DB_CONNECTION' => 'pgsql', 'DB_HOST' => 'h', 'DB_PORT' => '', 'DB_DATABASE' => 'd', 'DB_USERNAME' => 'u', 'DB_PASSWORD' => 'pw', 'DB_SSL' => 'true', 'DB_SSL_VERIFY' => 'false'];
$fe = DatabaseConfig::fromEnv(fn (string $k, mixed $d = null): mixed => $envVals[$k] ?? $d);
check('fromEnv: engine/port default and legacy DB_SSL=true, DB_SSL_VERIFY=false => require', $fe->engine === 'pgsql' && $fe->port === 5432 && $fe->sslMode === 'require');
$envVals2 = ['DATABASE_URL' => 'mysql://a:b@h2:3307/d2', 'DB_HOST' => 'ignored'];
$fe2 = DatabaseConfig::fromEnv(fn (string $k, mixed $d = null): mixed => $envVals2[$k] ?? $d);
check('fromEnv: DATABASE_URL takes precedence', $fe2->host === 'h2' && $fe2->port === 3307 && $fe2->database === 'd2');
$rt = DatabaseConfig::fromEnv(fn (string $k, mixed $d = null): mixed => $u->toEnv()[$k] ?? $d);
check('toEnv()/fromEnv() round trip is lossless', $rt->toArray() === $u->toArray());
check('legacy DB_SSL=true verifies the certificate by default', DatabaseConfig::fromEnv(fn (string $k, mixed $d = null): mixed => ['DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'd', 'DB_SSL' => 'true'][$k] ?? $d)->sslMode === 'verify-ca');
check('the running application config came through DatabaseConfig', $conn->engine() === 'mysql' || $conn->engine() === 'mariadb');

// ---------------------------------------------------------------------------
section('Dialect: MySQL SQL -> PostgreSQL SQL');
$pg = new Dialect('pgsql');
$my = new Dialect('mysql');
check('MySQL/MariaDB SQL is never modified', $my->translate('SELECT `a` FROM `t` WHERE `n` LIKE ?') === 'SELECT `a` FROM `t` WHERE `n` LIKE ?');
check('backticks become double quotes', $pg->translate('SELECT `a`, `t`.`b` FROM `t`') === 'SELECT "a", "t"."b" FROM "t"');
check('LIKE becomes ILIKE (case-insensitive like utf8mb4_unicode_ci)', $pg->translate('WHERE `n` LIKE ? AND `m` NOT LIKE ?') === 'WHERE "n" ILIKE ? AND "m" NOT ILIKE ?');
check('string literals are left untouched', $pg->translate("SELECT 'a `b` LIKE c' AS x, `y`") === "SELECT 'a `b` LIKE c' AS x, \"y\"");
check("'' escapes inside literals are respected", $pg->translate("SELECT 'it''s `x`', `y`") === "SELECT 'it''s `x`', \"y\"");
check('CAST(x AS UNSIGNED) is made safe for non-numeric text', str_contains($pg->translate('SELECT CAST(`roll_no` AS UNSIGNED) FROM `t`'), "regexp_replace((\"roll_no\")::text, '[^0-9]', '', 'g')") || str_contains($pg->translate('SELECT CAST(`roll_no` AS UNSIGNED) FROM `t`'), "regexp_replace((`roll_no`)::text"));
check('nested CAST arguments are balanced', str_contains($pg->translate('CAST(SUBSTRING(`a`, ?) AS UNSIGNED)'), 'AS BIGINT)'));
check('DATE_FORMAT -> to_char', $pg->translate("DATE_FORMAT(`date`, '%Y-%m')") === "to_char(\"date\", 'YYYY-MM')");
check('IFNULL/CURDATE/DATABASE()', $pg->translate('IFNULL(a,0), CURDATE(), DATABASE()') === 'COALESCE(a,0), CURRENT_DATE, current_schema()');
check('upsert no-op: MySQL', str_contains($my->upsertNoop('t', ['a', 'b'], ['a']), 'ON DUPLICATE KEY UPDATE `a` = `a`'));
check('upsert no-op: PostgreSQL', $pg->upsertNoop('t', ['a', 'b'], ['a']) === 'INSERT INTO "t" ("a","b") VALUES (?,?) ON CONFLICT ("a") DO NOTHING');
check('upsert GREATEST: PostgreSQL uses EXCLUDED', str_contains($pg->upsertGreatest('t', ['k', 'v'], ['k'], 'v'), 'DO UPDATE SET "v" = GREATEST("t"."v", EXCLUDED."v")'));
check('stripComments keeps quoted "--"', Dialect::stripComments("SELECT '--x' -- tail\nFROM t") === "SELECT '--x' \nFROM t");

// ---------------------------------------------------------------------------
section('PostgresTranslator: schema translation');
$t = new PostgresTranslator();
$sql = "CREATE TABLE IF NOT EXISTS `demo` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `status` ENUM('active','left') NOT NULL DEFAULT 'active',  -- note, with comma
  `qty` INT UNSIGNED NOT NULL DEFAULT 0,
  `flag` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT NULL,
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `skey` INT UNSIGNED GENERATED ALWAYS AS (COALESCE(`qty`, 0)) VIRTUAL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_demo` (`qty`, `status`),
  KEY `idx_demo` (`created_at`),
  CONSTRAINT `fk_demo` FOREIGN KEY (`qty`) REFERENCES `other` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC";
$out = $t->translate($sql);
$all = implode("\n", $out);
check('identity column replaces AUTO_INCREMENT', str_contains($all, '"id" BIGINT GENERATED BY DEFAULT AS IDENTITY'));
check('ENUM becomes VARCHAR + CHECK', str_contains($all, 'VARCHAR(20) NOT NULL DEFAULT \'active\' CHECK ("status" IN (\'active\',\'left\'))'));
check('unsigned/TINYINT/DATETIME/DECIMAL mapped', str_contains($all, '"qty" BIGINT NOT NULL') && str_contains($all, '"flag" SMALLINT') && str_contains($all, 'TIMESTAMP(0)') && str_contains($all, 'NUMERIC(10,2)'));
check('VIRTUAL generated column becomes STORED', str_contains($all, 'GENERATED ALWAYS AS (COALESCE("qty", 0)) STORED'));
check('KEY / UNIQUE KEY become schema-unique named indexes', str_contains($all, 'CREATE UNIQUE INDEX IF NOT EXISTS "demo_uq_demo" ON "demo" ("qty", "status")') && str_contains($all, 'CREATE INDEX IF NOT EXISTS "demo_idx_demo"'));
check('no MySQL-only tokens survive', preg_match('/ENGINE|AUTO_INCREMENT|UNSIGNED|`|ROW_FORMAT|CHARSET|--/', $all) === 0);
check('FOREIGN KEY constraint is kept', str_contains($all, 'CONSTRAINT "fk_demo" FOREIGN KEY ("qty") REFERENCES "other" ("id") ON DELETE CASCADE'));
$alt = implode("\n", $t->translate('ALTER TABLE `student_enrollments` MODIFY `section_id` INT UNSIGNED NULL'));
check('MODIFY ... NULL -> ALTER COLUMN TYPE + DROP NOT NULL', str_contains($alt, 'ALTER COLUMN "section_id" TYPE BIGINT') && str_contains($alt, 'ALTER COLUMN "section_id" DROP NOT NULL'));
check('ALTER ADD COLUMN drops AFTER and is idempotent', implode('', $t->translate('ALTER TABLE `t` ADD COLUMN `uid` CHAR(12) DEFAULT NULL AFTER `id`')) === 'ALTER TABLE "t" ADD COLUMN IF NOT EXISTS "uid" CHAR(12) DEFAULT NULL');
check('index names are prefixed with the table (global namespace in PostgreSQL)', PostgresTranslator::indexName('students', 'idx_x') === 'students_idx_x' && strlen(PostgresTranslator::indexName('t', str_repeat('x', 80))) <= 63);
check('INSERT IGNORE -> ON CONFLICT DO NOTHING', str_ends_with(implode('', $t->translate('INSERT IGNORE INTO `p` (`a`) VALUES (1)')), 'ON CONFLICT DO NOTHING'));
$mig = new Database\Migrator();
$bad = 0; $stmts = 0;
foreach (array_merge(glob($root . '/database/migrations/*.sql'), glob($root . '/database/migrations/*.php')) as $f) {
    foreach ($mig->postgresStatementsForFile($f) as $st) {
        $stmts++;
        if (preg_match('/\bENGINE\s*=|AUTO_INCREMENT|`|\bUNSIGNED\b|ROW_FORMAT|\bINSERT IGNORE\b|ON DUPLICATE KEY/i', $st) === 1) { $bad++; }
    }
}
check("all migrations translate ({$stmts} statements) with no MySQL-only syntax left", $stmts > 300 && $bad === 0);

// ---------------------------------------------------------------------------
section('Credential hygiene');
check('redact() masks URL passwords and password= pairs', SetupService::redact('failed mysql://root:hunter2@db/x password=hunter2') === 'failed mysql://***@db/x password=***');
$setup = new SetupService($root);
$i = $setup->inspectConnection(DatabaseConfig::fromArray(['engine' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'database' => $dbName, 'username' => 'erp_local', 'password' => 'definitely-wrong-pw-XYZ']));
check('wrong password: reported as auth failure without echoing credentials', !$i['ok'] && !str_contains(json_encode($i), 'definitely-wrong-pw-XYZ') && !str_contains(json_encode($i), 'erp_local') && $i['state'] === 'auth_failed');
$i = $setup->inspectConnection(DatabaseConfig::fromArray(['engine' => 'mysql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'x', 'username' => 'u', 'password' => 'p', 'timeout' => 2]));
check('closed port: reported as unreachable', !$i['ok'] && $i['state'] === 'unreachable' && !str_contains($i['message'], '127.0.0.1'));
$i = $setup->inspectConnection(DatabaseConfig::fromArray(['engine' => 'pgsql', 'host' => '127.0.0.1', 'database' => 'x', 'username' => 'u']));
check('missing PHP driver is explained (not a stack trace)', extension_loaded('pdo_pgsql') || (!$i['ok'] && str_contains($i['message'], 'pdo_pgsql')));

// ---------------------------------------------------------------------------
section('SetupService: environment detection and diagnostics');
check('requirements list covers PHP, extensions, driver, writable dirs', count($setup->requirements()) >= 15 && $setup->requirementsMet());
check('base path: site root', $setup->detectBasePath(['SCRIPT_NAME' => '/index.php']) === '');
check('base path: http://localhost/school-erp/public/index.php', $setup->detectBasePath(['SCRIPT_NAME' => '/school-erp/public/index.php']) === '/school-erp');
check('base path: http://localhost/school-erp/install.php', $setup->detectBasePath(['SCRIPT_NAME' => '/school-erp/install.php']) === '/school-erp');
check('environment() reports OS, PHP, root, drivers', isset($setup->environment()['os'], $setup->environment()['database_drivers']) && $setup->environment()['project_root'] === $root);
$i = $setup->inspectConnection(DatabaseConfig::fromEnv(fn (string $k, mixed $d = null): mixed => Env::get($k, $d)));
check('inspectConnection on the test database', $i['ok'] && $i['database_exists'] && $i['tables'] > 50 && $i['version'] !== '');
$i2 = $setup->inspectConnection(DatabaseConfig::fromArray(array_merge(DatabaseConfig::fromEnv(fn (string $k, mixed $d = null): mixed => Env::get($k, $d))->toArray(), ['database' => 'no_such_db_' . bin2hex(random_bytes(3))])));
check('missing database is detected while the server is reachable', $i2['ok'] === true && $i2['database_exists'] === false);
check('system databases cannot be created over', throws(fn () => $setup->createDatabase(DatabaseConfig::fromArray(['engine' => 'mysql', 'host' => '127.0.0.1', 'database' => 'mysql', 'username' => 'u'])), 'system database'));

// ---------------------------------------------------------------------------
section('SetupService: install pipeline (isolated project root, test database)');
mkdir($tmp . '/storage/logs', 0775, true);
symlink($root . '/config', $tmp . '/config');
copy($root . '/.env.example', $tmp . '/.env.example');
$cfg = DatabaseConfig::fromEnv(fn (string $k, mixed $d = null): mixed => Env::get($k, $d));
$fresh = new SetupService($tmp);
// start from an empty, unmigrated test database (disposable by construction)
(new Database\Migrator())->fresh();
$savedEnv = ['APP_ENV' => Env::get('APP_ENV'), 'APP_DEBUG' => Env::get('APP_DEBUG'), 'APP_TIMEZONE' => Env::get('APP_TIMEZONE')];
$admin = ['name' => 'Test Admin', 'email' => 'owner@example.test', 'password' => 'Str0ng!Passphrase'];
$school = ['school' => 'Test School', 'timezone' => 'Asia/Kolkata', 'currency' => '₹'];
check('weak/invalid admin input is rejected before anything is written', throws(fn () => $fresh->install($cfg, ['name' => 'x', 'email' => 'nope', 'password' => 'short'], $school)) && !is_file($tmp . '/.env'));
check('invalid storage path is rejected before anything is written', throws(fn () => $fresh->install($cfg, $admin, $school, ['storage_path' => 'relative/dir']), 'absolute') && !is_file($tmp . '/.env'));
$storageDir = $tmp . '/external-storage';
$report = $fresh->install($cfg, $admin, $school, ['storage_path' => $storageDir]);
check('install reports every step OK', count($report) >= 8 && count(array_filter($report, fn ($r) => $r['status'] === 'ok')) >= 8);
check('exactly one user (the administrator); no demo accounts', (int) QueryBuilder::table('users')->count() === 1 && QueryBuilder::table('users')->where('email', 'owner@example.test')->count() === 1 && QueryBuilder::table('users')->where('email', 'student@school.test')->count() === 0);
check('administrator password is hashed and verifies', \Core\Security\Hash::verify($admin['password'], (string) QueryBuilder::table('users')->where('email', 'owner@example.test')->value('password')) && QueryBuilder::table('users')->where('email', 'owner@example.test')->value('password') !== $admin['password']);
check('administrator holds the super_admin role', (int) $conn->scalar('SELECT COUNT(*) FROM `user_roles` ur JOIN `roles` r ON r.`id`=ur.`role_id` JOIN `users` u ON u.`id`=ur.`user_id` WHERE r.`slug`=? AND u.`email`=?', ['super_admin', 'owner@example.test']) === 1);
check('all migrations applied, none pending', (new Database\Migrator())->status()['pending'] === []);
check('school settings saved', QueryBuilder::table('settings')->where('key', 'school_name')->value('value') === 'Test School');
$envFile = (string) file_get_contents($tmp . '/.env');
check('.env written with 0600 permissions', (fileperms($tmp . '/.env') & 0777) === 0600);
check('.env holds engine, database, and STORAGE_PATH', str_contains($envFile, 'DB_CONNECTION=') && str_contains($envFile, 'DB_DATABASE=' . $dbName) && str_contains($envFile, 'STORAGE_PATH='));
check('.env.example was not modified', file_get_contents($tmp . '/.env.example') === file_get_contents($root . '/.env.example'));
check('external storage folder created with the expected layout', is_dir($storageDir . '/uploads/documents') && is_dir($storageDir . '/backups'));
check('installation lock written', is_file($tmp . '/storage/installed.lock'));
check('second install is refused (locked) and changes nothing', throws(fn () => $fresh->install($cfg, $admin, $school), 'locked') && (int) QueryBuilder::table('users')->count() === 1);
unlink($tmp . '/storage/installed.lock');
$beforeEnv = (string) file_get_contents($tmp . '/.env');
check('even without the lock file, an installed database is never installed over', throws(fn () => $fresh->install($cfg, ['name' => 'Evil', 'email' => 'evil@example.test', 'password' => 'Another!Passw0rd'], $school), 'already contains')
    && QueryBuilder::table('users')->where('email', 'evil@example.test')->count() === 0 && file_get_contents($tmp . '/.env') === $beforeEnv);
check('verify() passes on the installed database', $fresh->verify()['ok']);
foreach ($savedEnv as $k => $v) { Env::set($k, $v); }
Env::set('STORAGE_PATH', ''); putenv('STORAGE_PATH='); Config::load($root . '/config'); Connection::reset(); $conn = Connection::instance();
check('config switch: new settings are tested first, bad ones never saved', (function () use ($fresh, $tmp) {
    $before = (string) file_get_contents($tmp . '/.env');
    try { $fresh->switchDatabase(DatabaseConfig::fromArray(['engine' => 'mysql', 'host' => '127.0.0.1', 'database' => 'x', 'username' => 'nobody', 'password' => 'bad'])); return false; }
    catch (SetupException $e) { return file_get_contents($tmp . '/.env') === $before; }
})());

// ---------------------------------------------------------------------------
section('Portable backup: export, verify, tamper detection, encryption');
(new Database\Migrator())->fresh();
(new Database\Migrator())->migrate();
(new Database\Seeds\DatabaseSeeder())->run(true);
QueryBuilder::table('settings')->insert(['group' => 'test', 'key' => 'unicode_' . bin2hex(random_bytes(2)), 'value' => 'नमस्ते ₹ — "quotes" \'single\' \\ back', 'type' => 'string', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
$dump = new PortableDumpService();
$schema = SchemaInspector::make();
$srcCounts = $schema->rowCounts(array_diff($schema->tables(), ['migrations']));
$file = $tmp . '/backup.erpdump';
$r = $dump->export($file);
check('export wrote a file and re-verified it', is_file($file) && $r['rows'] > 100 && $r['tables'] > 80 && $r['sha256'] === hash_file('sha256', $file));
check('backup file is mode 0600 and gzip', (fileperms($file) & 0777) === 0600 && file_get_contents($file, false, null, 0, 2) === "\x1f\x8b");
$v = $dump->verify($file);
check('verify() recomputes every checksum and passes', $v['ok'] && $v['rows'] === $r['rows'] && $v['engine'] === $conn->engine());
check('export refuses to overwrite an existing file', throws(fn () => $dump->export($file), 'overwrite'));
// tamper: change one byte inside the compressed stream
$raw = (string) file_get_contents($file);
$bad1 = $tmp . '/tampered.erpdump';
file_put_contents($bad1, substr($raw, 0, intdiv(strlen($raw), 2)) . chr(ord($raw[intdiv(strlen($raw), 2)]) ^ 0xFF) . substr($raw, intdiv(strlen($raw), 2) + 1));
check('a corrupted file is reported invalid', !@$dump->verify($bad1)['ok']);
// tamper: edit a value but keep the gzip valid
$lines = explode("\n", (string) gzdecode($raw));
foreach ($lines as $k => $l) {
    if (str_contains($l, '"t":"users"')) {
        $o = json_decode($l, true);
        $o['r'][1] = 'tampered';
        $lines[$k] = json_encode($o, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        break;
    }
}
$bad2 = $tmp . '/edited.erpdump';
file_put_contents($bad2, gzencode(implode("\n", $lines)));
$ve = $dump->verify($bad2);
check('an edited row is caught by the checksum', !$ve['ok'] && str_contains($ve['message'], 'checksum mismatch for users'));
$bad3 = $tmp . '/truncated.erpdump';
$kept = array_slice(explode("\n", (string) gzdecode($raw)), 0, 50);
file_put_contents($bad3, gzencode(implode("\n", $kept)));
check('a truncated dump (no footer) is reported invalid', str_contains($dump->verify($bad3)['message'], 'footer missing'));
check('not-a-dump files are rejected', !$dump->verify(__FILE__)['ok'] && !$dump->verify($tmp . '/missing')['ok']);
$enc = $tmp . '/backup.erpdump.enc';
$re = $dump->export($enc, 'correct horse battery');
check('encrypted export is not readable as gzip/plain text', $re['encrypted'] && !str_contains((string) file_get_contents($enc), 'school-erp') && str_starts_with((string) file_get_contents($enc), 'ERPENC1'));
check('encrypted dump verifies with the right passphrase', $dump->verify($enc, 'correct horse battery')['ok']);
check('wrong or missing passphrase is refused', !$dump->verify($enc, 'wrong')['ok'] && !$dump->verify($enc)['ok']);
check('inspect() reports engine, counts and non-portable objects', ($dump->inspect()['tables'] ?? 0) > 80 && isset($dump->inspect()['non_portable']['triggers']));

// ---------------------------------------------------------------------------
section('Portable restore: import into a fresh database and verify integrity');
$beforeSums = $v['stats'];
check('import into a database that holds school data is refused', str_contains($dump->import($file)['message'], 'already contains data'));
(new Database\Migrator())->fresh();
$notMigrated = (new Database\Migrator())->migrate();
$res = $dump->import($bad2);
check('import of an invalid backup is refused before touching the database', !$res['ok'] && str_contains($res['message'], 'not valid') && (int) QueryBuilder::table('users')->count() === 0);
$res = $dump->import($file);
check('import succeeded: counts match and no orphaned foreign keys', $res['ok'] && $res['count_mismatches'] === [] && $res['orphans'] === []);
$after = $schema->rowCounts(array_diff($schema->tables(), ['migrations']));
check('every table has exactly the original row count', $after === $srcCounts);
$re2 = $dump->export($tmp . '/roundtrip.erpdump');
$same = true; $diff = [];
foreach ($beforeSums as $tname => $s) { if (($re2['rows'] ?? 0) && ($dump->verify($tmp . '/roundtrip.erpdump')['stats'][$tname]['sha256'] ?? '') !== $s['sha256']) { $same = false; $diff[] = $tname; } }
check('round trip is byte-exact per table (SHA-256 of every row matches)' . ($diff ? ' differs: ' . implode(',', array_slice($diff, 0, 5)) : ''), $same);
check('administrator can still authenticate (password hash preserved)', \Core\Security\Hash::verify('Password123!', (string) QueryBuilder::table('users')->where('email', 'superadmin@school.test')->value('password')));
check('unicode / quote / backslash values survive', QueryBuilder::table('settings')->where('group', 'test')->value('value') === 'नमस्ते ₹ — "quotes" \'single\' \\ back');
check('generated columns were recomputed by the database', (int) $conn->scalar('SELECT COUNT(*) FROM `student_enrollments` WHERE `section_key` <> COALESCE(`section_id`, 0)') === 0);
check('PRNs, registry and enrollments intact', (int) $conn->scalar('SELECT COUNT(*) FROM `students` s JOIN `prn_registry` r ON r.`student_id` = s.`id` AND r.`prn` = s.`prn`') === (int) QueryBuilder::table('students')->count());
check('importing again is refused (target no longer empty)', str_contains($dump->import($file)['message'], 'already contains data'));
$mAfter = array_column($conn->select('SELECT `migration` FROM `migrations`'), 'migration');
check('migration history is the target\'s own and complete', count($mAfter) === count(glob($root . '/database/migrations/*.sql')) + count(glob($root . '/database/migrations/*.php')));

// ---------------------------------------------------------------------------
section('Storage: local driver, path safety, S3-compatible driver');
$ls = new LocalStorage($tmp . '/disk');
$ls->put('documents/1/a.pdf', 'PDFDATA');
check('local put/get/exists/delete', $ls->exists('documents/1/a.pdf') && $ls->get('documents/1/a.pdf') === 'PDFDATA' && (function () use ($ls) { $ls->delete('documents/1/a.pdf'); $ls->delete('documents/1/a.pdf'); return !$ls->exists('documents/1/a.pdf'); })());
check('path traversal / absolute / NUL keys rejected', throws(fn () => $ls->put('../evil', 'x')) && throws(fn () => $ls->get('/etc/passwd')) && throws(fn () => $ls->exists("a\0b")) && throws(fn () => $ls->put('a/../../b', 'x')));
check('local file permissions are not world-readable', (function () use ($ls, $tmp) { $ls->put('perm.txt', 'x'); return (fileperms($tmp . '/disk/perm.txt') & 0007) === 0; })());
// AWS Signature V4 published test vector "get-vanilla"
$sig = S3Storage::authorization('GET', '/', '', ['host' => 'example.amazonaws.com', 'x-amz-date' => '20150830T123600Z'], hash('sha256', ''), 'AKIDEXAMPLE', 'wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY', 'us-east-1', 'service', '20150830T123600Z');
check('SigV4 matches the AWS "get-vanilla" test vector', str_ends_with($sig, 'Signature=5fa00fa31553b73ebf1942676e86291e8372ff2a2260956d9b8aae1d763fbf31'));
$port = random_int(20000, 40000);
$mockDir = $tmp . '/s3';
mkdir($mockDir, 0775, true);
$proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:{$port}", $root . '/tests/tools/s3_mock_router.php'], [1 => ['file', '/tmp/s3mock-test.log', 'w'], 2 => ['file', '/tmp/s3mock-test.log', 'a']], $p, null, ['S3_MOCK_DIR' => $mockDir] + getenv());
usleep(700000);
$s3 = new S3Storage("http://127.0.0.1:{$port}", 'auto', 'erp-bucket', 'AKIAMOCK', 'mock-secret-key', true, 'prefix');
try {
    $s3->put('documents/7/x y.pdf', "%PDF-1.4 binary\x00\xff", 'application/pdf');
    check('S3 put + exists + get round trip (signature accepted by the verifying mock)', $s3->exists('documents/7/x y.pdf') && $s3->get('documents/7/x y.pdf') === "%PDF-1.4 binary\x00\xff");
    check('S3 object lands under bucket/prefix and key encoding', is_file($mockDir . '/erp-bucket/prefix/documents/7/x y.pdf'));
    check('S3 missing object', !$s3->exists('nope') && throws(fn () => $s3->get('nope')));
    $s3->delete('documents/7/x y.pdf');
    check('S3 delete', !$s3->exists('documents/7/x y.pdf'));
    $bad = new S3Storage("http://127.0.0.1:{$port}", 'auto', 'erp-bucket', 'AKIAMOCK', 'WRONG-secret', true);
    check('wrong secret key is rejected by the server', throws(fn () => $bad->put('a.txt', 'x'), 'rejected'));
    check('S3 keys cannot traverse', throws(fn () => $s3->put('../x', 'y')));
} finally {
    proc_terminate($proc);
    proc_close($proc);
}
check('S3 config validation', throws(fn () => new S3Storage('', 'r', 'b', 'k', 's')) && throws(fn () => new S3Storage('ftp://x', 'r', 'b', 'k', 's')));

// document store keeps a per-file disk
$docCols = (new SchemaInspector($conn))->columns('student_documents');
check('student_documents remembers each file\'s storage disk', in_array('storage_disk', $docCols, true));
$svc = new \App\Services\DocumentService();
$docId = (int) QueryBuilder::table('student_documents')->insert(['student_id' => (int) QueryBuilder::table('students')->value('id'), 'title' => 't', 'type' => 'other', 'file_path' => '1/zz.pdf', 'mime_type' => 'application/pdf', 'file_size' => 3, 'storage_disk' => 'local', 'created_at' => date('Y-m-d H:i:s')]);
$docRow = $svc->find($docId);
$localPath = rtrim((string) config('paths.uploads'), '/') . '/documents/1/zz.pdf';
@mkdir(dirname($localPath), 0775, true);
file_put_contents($localPath, 'LOCALPDF');
check('DocumentService::contents() reads a local document', $svc->contents($docRow) === 'LOCALPDF');
check('DocumentService::contents() returns null for a missing file', $svc->contents(['storage_disk' => 'local', 'file_path' => '9/none.pdf']) === null);
@unlink($localPath);
QueryBuilder::table('student_documents')->where('id', $docId)->delete();

// ---------------------------------------------------------------------------
// cleanup temp files
$rm = function (string $d) use (&$rm): void { foreach (scandir($d) ?: [] as $n) { if ($n === '.' || $n === '..') { continue; } $f = $d . '/' . $n; if (is_link($f)) { unlink($f); } elseif (is_dir($f)) { $rm($f); rmdir($f); } else { unlink($f); } } };
$rm($tmp);
@rmdir($tmp);

echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) {
    echo "Failures:\n - " . implode("\n - ", $failures) . "\n";
    exit(1);
}
echo "\033[32mAll setup / portability checks passed.\033[0m\n";
