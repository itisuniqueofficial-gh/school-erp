<?php

declare(strict_types=1);

/**
 * Installer & administrator-recovery safety.
 *
 *   APP_TESTING=1 php tests/integration_installer_safety.php
 *
 * Exercises only the dedicated "_test" database:
 *  - fresh vs existing installation detection;
 *  - production seeding without demo accounts;
 *  - repeat seeding never duplicates users or destroys data;
 *  - .env updates are backed up, round-trip correctly and can be restored;
 *  - one-admin recovery resets only the selected account and revokes tokens;
 *  - recovery-created admins are not duplicated on repeat.
 */

putenv('APP_TESTING=1');
$_SERVER['APP_TESTING'] = '1';
$_ENV['APP_TESTING'] = '1';

$root = dirname(__DIR__);
$app = require $root . '/bootstrap/app.php';

use App\Services\AdminRecoveryService;
use App\Services\InstallService;
use Core\Config\Config;
use Core\Database\QueryBuilder;
use Core\Security\Hash;
use Core\Support\EnvFile;
use Database\Migrator;
use Database\Seeds\DatabaseSeeder;

$pass = 0;
$fail = 0;
$failures = [];
function check(string $label, bool $condition): void
{
    global $pass, $fail, $failures;
    if ($condition) {
        $pass++;
        echo "  \033[32mPASS\033[0m  {$label}\n";
        return;
    }
    $fail++;
    $failures[] = $label;
    echo "  \033[31mFAIL\033[0m  {$label}\n";
}
function section(string $title): void
{
    echo "\n\033[36m== {$title} ==\033[0m\n";
}

$dbName = (string) Config::get('database.connections.' . Config::get('database.default') . '.database');
if (!str_ends_with($dbName, '_test')) {
    fwrite(STDERR, "Refusing to run: target database '{$dbName}' is not a *_test database.\n");
    exit(1);
}

// ---------------------------------------------------------------------------
section('.env reader/writer safety');
$envPath = sys_get_temp_dir() . '/erp_env_safety_' . bin2hex(random_bytes(4)) . '.env';
file_put_contents($envPath, "APP_KEY=old\nDB_PASSWORD=old\n# preserved comment\n");
$envResult = EnvFile::update($envPath, [
    'APP_KEY'       => 'new-key-123',
    'DB_PASSWORD'   => 'p @ss$word"x#y',
    'APP_BASE_PATH' => '/erp',
]);
$envValues = EnvFile::read($envPath);
check('existing keys are replaced', ($envValues['APP_KEY'] ?? null) === 'new-key-123');
check('special characters round-trip', ($envValues['DB_PASSWORD'] ?? null) === 'p @ss$word"x#y');
check('missing keys are appended', ($envValues['APP_BASE_PATH'] ?? null) === '/erp');
check('a timestamped backup is created', is_string($envResult['backup']) && is_file($envResult['backup']));
check('comments are preserved', str_contains((string) file_get_contents($envPath), '# preserved comment'));
EnvFile::restore($envPath, $envResult['backup']);
$restored = EnvFile::read($envPath);
check('restore rolls back both keys', ($restored['APP_KEY'] ?? null) === 'old' && ($restored['DB_PASSWORD'] ?? null) === 'old');

$newlineRejected = false;
try {
    EnvFile::encode("line1\nline2");
} catch (\InvalidArgumentException) {
    $newlineRejected = true;
}
check('newlines in values are rejected', $newlineRejected);
@unlink($envPath);
if (is_string($envResult['backup'])) {
    @unlink($envResult['backup']);
}

// ---------------------------------------------------------------------------
section('Fresh installation detection');
$migrator = new Migrator();
$migrator->fresh();
$migrator->migrate();
$install = new InstallService();
check('fresh migrated DB is not installed', $install->isInstalled() === false);
check('fresh migrated DB should seed', $install->shouldSeed() === true);

// ---------------------------------------------------------------------------
section('Production seed excludes demo accounts');
$seeder = new DatabaseSeeder();
$seeder->run(false);
check('exactly one user is created', (int) QueryBuilder::table('users')->count() === 1);
check('the only user is the super administrator', (int) QueryBuilder::table('users')->where('email', 'superadmin@school.test')->count() === 1);
check('no demo student account exists', (int) QueryBuilder::table('users')->where('email', 'student@school.test')->count() === 0);
check('no demo teacher account exists', (int) QueryBuilder::table('users')->where('email', 'teacher@school.test')->count() === 0);
check('installed state is now detected', $install->isInstalled() === true);

// ---------------------------------------------------------------------------
section('Repeated install/seed preserves users and data');
$customKey = 'installer_safety_probe_' . bin2hex(random_bytes(3));
QueryBuilder::table('settings')->insert([
    'group'      => 'general',
    'key'        => $customKey,
    'value'      => 'keep-me',
    'type'       => 'string',
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s'),
]);
$usersBefore = (int) QueryBuilder::table('users')->count();
$classesBefore = (int) QueryBuilder::table('classes')->count();

$seeder->run(false);

check('repeat seed does not duplicate users', (int) QueryBuilder::table('users')->count() === $usersBefore);
check('repeat seed keeps one super administrator', (int) QueryBuilder::table('users')->where('email', 'superadmin@school.test')->count() === 1);
check('existing classes are not recreated', (int) QueryBuilder::table('classes')->count() === $classesBefore);
check('custom settings survive a repeat seed', (int) QueryBuilder::table('settings')->where('key', $customKey)->count() === 1);

// ---------------------------------------------------------------------------
section('Administrator recovery resets only the selected account');
$recovery = new AdminRecoveryService();
$super = QueryBuilder::table('users')->where('username', 'superadmin')->first();
$superId = (int) ($super['id'] ?? 0);

$other = $recovery->resetOrCreate('other-admin@example.test', 'OtherPass!234', 'Other Admin', true, null, 'test');
$otherHash = (string) QueryBuilder::table('users')->where('id', $other['id'])->value('password');

QueryBuilder::table('remember_tokens')->insert([
    'user_id'    => $superId,
    'selector'   => bin2hex(random_bytes(6)),
    'token'      => hash('sha256', bin2hex(random_bytes(16))),
    'expires_at' => date('Y-m-d H:i:s', time() + 3600),
    'created_at' => date('Y-m-d H:i:s'),
]);

$recovered = $recovery->resetOrCreate(
    'owner@example.test',
    'RecoveredPass!234',
    'Recovered Owner',
    false,
    'superadmin',
    'test'
);
$freshSuper = QueryBuilder::table('users')->where('id', $superId)->first();

check('recovery updates the existing super-admin id', $recovered['id'] === $superId);
check('recovery changes the email', ($freshSuper['email'] ?? null) === 'owner@example.test');
check('recovery sets a working password', Hash::verify('RecoveredPass!234', (string) ($freshSuper['password'] ?? '')));
check('recovery reactivates the account', (int) ($freshSuper['is_active'] ?? 0) === 1);
check('recovery keeps the super_admin role', (int) QueryBuilder::table('user_roles')->where('user_id', $superId)->count() === 1);
check('recovery revokes remember-me tokens', (int) QueryBuilder::table('remember_tokens')->where('user_id', $superId)->count() === 0);
check('other users are untouched by recovery', (string) QueryBuilder::table('users')->where('id', $other['id'])->value('password') === $otherHash);
check('recovery is audit logged', (int) QueryBuilder::table('audit_logs')->where('action', 'admin.recovery_reset')->count() >= 1);

$missingRejected = false;
try {
    $recovery->resetOrCreate('nobody@example.test', 'NoSuchUser!234', null, false, null, 'test');
} catch (\DomainException) {
    $missingRejected = true;
}
check('unknown admin is refused unless --create is used', $missingRejected);

// ---------------------------------------------------------------------------
section('Recovery-created admins are not duplicated');
$usersBeforeCreate = (int) QueryBuilder::table('users')->count();
$created = $recovery->resetOrCreate('new-admin@example.test', 'NewAdminPass!234', 'New Admin', true, null, 'test');
check('--create creates a new admin when no account exists', $created['created'] === true);
check('created user count grows by exactly one', (int) QueryBuilder::table('users')->count() === $usersBeforeCreate + 1);
check('created admin has the super_admin role', (int) QueryBuilder::table('user_roles')->where('user_id', $created['id'])->count() === 1);
check('created admin password works', Hash::verify('NewAdminPass!234', (string) QueryBuilder::table('users')->where('id', $created['id'])->value('password')));

$again = $recovery->resetOrCreate('new-admin@example.test', 'ChangedPass!234', 'New Admin', true, null, 'test');
check('repeat recovery reuses the same account', $again['created'] === false && $again['id'] === $created['id']);
check('repeat recovery does not add a user', (int) QueryBuilder::table('users')->count() === $usersBeforeCreate + 1);
check('repeat recovery updates the password', Hash::verify('ChangedPass!234', (string) QueryBuilder::table('users')->where('id', $created['id'])->value('password')));

// ---------------------------------------------------------------------------
section('No public web admin-recovery route');
$routes = (string) file_get_contents($root . '/routes/web.php');
check('routes/web.php exposes no password-recovery takeover endpoint', stripos($routes, 'admin/recover') === false && stripos($routes, 'admin:recover') === false);

// ---------------------------------------------------------------------------
section('Reset the _test database to the standard seeded fixture');
$migrator->fresh();
$migrator->migrate();
(new DatabaseSeeder())->run();

echo "\n\033[36m==================== RESULT ====================\033[0m\n";
echo "PASS: {$pass}   FAIL: {$fail}\n";
if ($fail > 0) {
    echo "\nFailures:\n - " . implode("\n - ", $failures) . "\n";
    exit(1);
}
echo "\033[32mAll installer-safety checks passed.\033[0m\n";
exit(0);
