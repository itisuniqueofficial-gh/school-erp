<?php

declare(strict_types=1);

namespace App\Services\Setup;

use Core\Config\Config;
use Core\Database\Connection;
use Core\Database\DatabaseConfig;
use Core\Database\DatabasePreflight;
use Core\Support\Env;
use Core\Support\EnvFile;
use PDO;

/**
 * The single backend behind BOTH installers (public/install.php and
 * `php bin/console install`). Neither front end contains installation logic of
 * its own; they only collect input and render this service's results.
 *
 * Safety rules enforced here (not in the UI):
 *  - A database that already has user accounts is never installed over,
 *    seeded, or given a new administrator. Existing installs are only upgraded
 *    (additive migrations) through the separate upgrade path.
 *  - Nothing is dropped, truncated or recreated. A missing database is only
 *    created on explicit request; an existing one is never replaced.
 *  - .env is replaced atomically with a timestamped backup, mode 0600, and is
 *    restored if installation fails before the database was touched.
 *  - Error text returned to callers never contains credentials, hosts or SQL.
 */
final class SetupService
{
    public const VERSION = '1.7.0';
    private const SYSTEM_DATABASES = ['information_schema', 'performance_schema', 'mysql', 'sys', 'postgres', 'template0', 'template1'];

    public function __construct(private readonly string $root)
    {
    }

    // ------------------------------------------------------------------ environment

    /**
     * @return array<int,array{key:string,label:string,status:string,detail:string,required:bool}> status: pass|fail|warn
     */
    public function requirements(): array
    {
        $r = [];
        $add = static function (string $key, string $label, bool $ok, string $detail, bool $required = true) use (&$r): void {
            $r[] = ['key' => $key, 'label' => $label, 'status' => $ok ? 'pass' : ($required ? 'fail' : 'warn'), 'detail' => $detail, 'required' => $required];
        };
        $add('php', 'PHP 8.2 or newer', version_compare(PHP_VERSION, '8.2.0', '>='), PHP_VERSION);
        foreach (['pdo', 'mbstring', 'openssl', 'fileinfo', 'json', 'ctype', 'session'] as $ext) {
            $add("ext_{$ext}", "PHP extension: {$ext}", extension_loaded($ext), extension_loaded($ext) ? 'loaded' : 'missing');
        }
        $drivers = $this->databaseDrivers();
        $add('db_driver', 'A database driver (pdo_mysql or pdo_pgsql)', in_array(true, $drivers, true),
            implode(', ', array_map(static fn ($k, $v) => $k . ($v ? ' ✓' : ' ✗'), array_keys($drivers), $drivers)));
        foreach (['gd' => 'images/QR codes', 'curl' => 'S3/R2 storage and webhooks', 'zip' => 'document bundles', 'iconv' => 'text conversion'] as $ext => $why) {
            $add("opt_{$ext}", "PHP extension: {$ext} (optional, {$why})", extension_loaded($ext), extension_loaded($ext) ? 'loaded' : 'missing', false);
        }
        foreach (['storage', 'storage/logs', 'storage/cache', 'storage/sessions', 'storage/uploads', 'storage/backups'] as $dir) {
            $p = $this->root . '/' . $dir;
            $ok = is_dir($p) ? is_writable($p) : (is_dir(dirname($p)) && is_writable(dirname($p)));
            $add('dir_' . $dir, "Writable: {$dir}", $ok, $ok ? 'ok' : 'not writable by the web server user');
        }
        $envOk = is_file($this->envPath()) ? is_writable($this->envPath()) : is_writable($this->root);
        $add('env_file', 'Can write the .env configuration file', $envOk, $envOk ? 'ok' : 'make .env (or the project folder) writable, or create .env by hand');
        return $r;
    }

    /** @return array<string,bool> */
    public function databaseDrivers(): array
    {
        return ['pdo_mysql (MySQL / MariaDB)' => extension_loaded('pdo_mysql'), 'pdo_pgsql (PostgreSQL)' => extension_loaded('pdo_pgsql')];
    }

    public function requirementsMet(): bool
    {
        foreach ($this->requirements() as $c) {
            if ($c['status'] === 'fail') {
                return false;
            }
        }
        return true;
    }

    /** @return array<string,mixed> */
    public function environment(array $server = []): array
    {
        return [
            'os'              => PHP_OS_FAMILY . ' (' . php_uname('r') . ')',
            'php'             => PHP_VERSION . ' ' . PHP_SAPI,
            'web_server'      => (string) ($server['SERVER_SOFTWARE'] ?? (PHP_SAPI === 'cli' ? 'CLI (no web server)' : 'unknown')),
            'project_root'    => $this->root,
            'base_path'       => $this->detectBasePath($server),
            'env_file'        => is_file($this->envPath()) ? 'present' : 'absent',
            'locked'          => $this->isLocked(),
            'storage_path'    => $this->storagePath(),
            'database_drivers' => $this->databaseDrivers(),
        ];
    }

    /**
     * Sub-directory aware base path: "" for a domain root, "/school-erp" for
     * http://localhost/school-erp/ (works with or without /public in the URL).
     */
    public function detectBasePath(array $server): string
    {
        $script = str_replace('\\', '/', (string) ($server['SCRIPT_NAME'] ?? ''));
        $dir = rtrim(dirname($script), '/');
        if ($dir === '.' || $dir === '/') {
            $dir = '';
        }
        return str_ends_with($dir, '/public') ? substr($dir, 0, -7) : $dir;
    }

    public function envPath(): string
    {
        return $this->root . '/.env';
    }

    public function lockPath(): string
    {
        return $this->root . '/storage/installed.lock';
    }

    public function isLocked(): bool
    {
        return is_file($this->lockPath());
    }

    public function storagePath(): string
    {
        $env = EnvFile::read($this->envPath());
        $p = trim((string) ($env['STORAGE_PATH'] ?? getenv('STORAGE_PATH') ?: ''));
        return $p !== '' ? $p : $this->root . '/storage';
    }

    // ------------------------------------------------------------------ database

    /**
     * Connect with the given settings and describe what is there. Never
     * throws; returns a credential-free message instead.
     *
     * @return array{ok:bool,state:string,message:string,version:?string,database_exists:bool,tables:int,users:int,has_users_table:bool,installed:bool}
     */
    public function inspectConnection(DatabaseConfig $c): array
    {
        $r = ['ok' => false, 'state' => DatabasePreflight::UNKNOWN, 'message' => '', 'version' => null, 'database_exists' => false,
            'tables' => 0, 'users' => 0, 'has_users_table' => false, 'installed' => false];
        if (!$c->driverAvailable()) {
            $r['message'] = 'The PHP extension pdo_' . $c->pdoDriver() . ' is not enabled on this server. Enable it in php.ini (or your hosting control panel) and retry.';
            return $r;
        }
        try {
            $server = $c->connect(false);
            $r['version'] = (string) $server->query('SELECT VERSION()')->fetchColumn();
            $r['database_exists'] = $this->databaseExists($server, $c);
            $r['ok'] = true;
            $r['state'] = DatabasePreflight::OK;
            $r['message'] = 'Connected to the database server.';
            if (!$r['database_exists']) {
                $r['message'] = 'Connected. The database "' . $c->database . '" does not exist yet.';
                return $r;
            }
            $pdo = $c->connect(true);
            $pg = $c->isPostgres();
            $schema = $pg ? 'current_schema()' : 'DATABASE()';
            $r['tables'] = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = {$schema} AND table_type = 'BASE TABLE'")->fetchColumn();
            $r['has_users_table'] = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = {$schema} AND table_name = 'users'")->fetchColumn() > 0;
            if ($r['has_users_table']) {
                $r['users'] = (int) $pdo->query($pg ? 'SELECT COUNT(*) FROM "users"' : 'SELECT COUNT(*) FROM `users`')->fetchColumn();
            }
            $r['installed'] = $r['users'] > 0;
        } catch (\Throwable $e) {
            [$state, $msg] = DatabasePreflight::classify($e);
            $r['ok'] = false;
            $r['state'] = $state;
            $r['message'] = $msg;
            $this->log($e, 'db-test');
        }
        return $r;
    }

    /**
     * Create the database (only when it does not exist). The caller must have
     * asked for it explicitly. Never drops or alters anything.
     *
     * @return array{created:bool,message:string}
     */
    public function createDatabase(DatabaseConfig $c): array
    {
        $name = $c->database;
        if ($name === '' || in_array(strtolower($name), self::SYSTEM_DATABASES, true)) {
            throw new SetupException('Choose a dedicated database name, not a system database.');
        }
        if (preg_match('/^[A-Za-z0-9_]{1,63}$/', $name) !== 1) {
            throw new SetupException('When creating a database, use only letters, digits and underscores (maximum 63 characters).');
        }
        try {
            $pdo = $c->connect(false);
            if ($this->databaseExists($pdo, $c)) {
                return ['created' => false, 'message' => 'The database already exists; it was not modified.'];
            }
            if ($c->isPostgres()) {
                $pdo->exec('CREATE DATABASE "' . $name . '" ENCODING \'UTF8\' TEMPLATE template0');
            } else {
                $pdo->exec('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            }
            return ['created' => true, 'message' => 'Database "' . $name . '" created.'];
        } catch (\Throwable $e) {
            $this->log($e, 'db-create');
            throw new SetupException('The database "' . $name . '" does not exist and this database user is not allowed to create it. '
                . 'Create it in phpMyAdmin / cPanel / pgAdmin first (or use an account with CREATE DATABASE permission). No changes were made.');
        }
    }

    // ------------------------------------------------------------------ configuration

    /**
     * State of the database currently configured in .env (the live source of
     * truth for "is this already installed?").
     *
     * @return array{configured:bool,reachable:bool,installed:bool,users:int,tables:int,message:string,engine:?string}
     */
    public function currentState(): array
    {
        $none = ['configured' => false, 'reachable' => false, 'installed' => false, 'users' => 0, 'tables' => 0, 'message' => 'No database is configured yet.', 'engine' => null];
        $env = EnvFile::read($this->envPath());
        if (trim((string) ($env['DB_DATABASE'] ?? '')) === '' && trim((string) ($env['DATABASE_URL'] ?? '')) === '') {
            return $none;
        }
        try {
            $cfg = DatabaseConfig::fromEnv(static fn (string $k, mixed $d = null): mixed => $env[$k] ?? $d);
        } catch (\Throwable) {
            return ['configured' => true] + ['message' => 'The existing database configuration is invalid.'] + $none;
        }
        $i = $this->inspectConnection($cfg);
        return ['configured' => true, 'reachable' => $i['ok'] && $i['database_exists'], 'installed' => $i['installed'], 'users' => $i['users'],
            'tables' => $i['tables'], 'engine' => $cfg->engine,
            'message' => $i['installed'] ? 'The configured database contains user accounts.' : ($i['ok'] ? 'The configured database has no users yet.' : $i['message'])];
    }

    /**
     * Replace the database settings in .env, but only after the NEW
     * credentials were proven to work, so a typo can never break a working site.
     *
     * @return array{path:string,backup:?string,created:bool}
     */
    public function switchDatabase(DatabaseConfig $new): array
    {
        $i = $this->inspectConnection($new);
        if (!$i['ok'] || !$i['database_exists']) {
            throw new SetupException('The new database settings were NOT saved. ' . ($i['database_exists'] || !$i['ok'] ? $i['message'] : 'That database does not exist.'));
        }
        $res = EnvFile::update($this->envPath(), $this->dbEnv($new), $this->root . '/.env.example');
        @chmod($this->envPath(), 0600);
        return $res;
    }

    /** @return array<string,string> */
    private function dbEnv(DatabaseConfig $c): array
    {
        // Clear optional keys first so a previous socket/SSL setting cannot linger.
        $blank = array_fill_keys(['DB_SOCKET', 'DB_COLLATION', 'DB_SCHEMA', 'DB_SSL_CA', 'DB_SSL_CERT', 'DB_SSL_KEY', 'DATABASE_URL'], '');
        return array_merge($blank, $c->toEnv());
    }

    /** Make freshly written settings visible to the running process. */
    public function applyRuntime(array $values): void
    {
        foreach ($values as $k => $v) {
            Env::set((string) $k, $v);
            putenv($k . '=' . $v);
            $_ENV[$k] = $v;
        }
        Config::load($this->root . '/config');
        Connection::reset();
    }

    // ------------------------------------------------------------------ install pipeline

    /**
     * Fresh installation. Returns the step report; throws SetupException with
     * a safe message when it had to stop.
     *
     * @param array{name:string,email:string,password:string} $admin
     * @param array{school:string,timezone:string,currency:string} $school
     * @param array{create_database?:bool,storage_path?:string} $options
     * @return array<int,array{step:string,status:string,detail:string}>
     */
    public function install(DatabaseConfig $cfg, array $admin, array $school, array $options = []): array
    {
        $steps = [];
        $step = static function (string $s, string $status, string $detail) use (&$steps): void {
            $steps[] = ['step' => $s, 'status' => $status, 'detail' => $detail];
        };

        $this->validateAdmin($admin, $school);
        if ($this->isLocked()) {
            throw new SetupException('This installation is locked. The installer will not reinstall, reset an administrator or replace the database. To recover an administrator run: php bin/console admin:recover');
        }
        $cur = $this->currentState();
        if ($cur['installed']) {
            throw new SetupException('The database configured in .env already contains ' . $cur['users'] . ' user account(s). The installer never overwrites a live installation. Use "php bin/console upgrade" to apply updates.');
        }

        $i = $this->inspectConnection($cfg);
        if (!$i['ok']) {
            throw new SetupException('Database connection failed. ' . $i['message']);
        }
        $step('Database connection', 'ok', ($i['version'] ?? '') . ' (' . $cfg->engine . ')');

        if (!$i['database_exists']) {
            if (empty($options['create_database'])) {
                throw new SetupException('The database "' . $cfg->database . '" does not exist. Create it first or allow the installer to create it.');
            }
            $c = $this->createDatabase($cfg);
            $step('Create database', 'ok', $c['message']);
            $i = $this->inspectConnection($cfg);
        }
        if ($i['installed']) {
            throw new SetupException('That database already contains ' . $i['users'] . ' user account(s). The installer will not install over it. Choose a different, empty database.');
        }
        if ($i['tables'] > 0) {
            $step('Existing tables', 'warn', $i['tables'] . ' table(s) found but no users. Installation continues additively; nothing is dropped.');
        }

        // Write configuration (atomic, backed up, mode 0600).
        $appEnv = [
            'APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'APP_TIMEZONE' => $school['timezone'],
        ];
        if (!empty($options['storage_path'])) {
            $appEnv['STORAGE_PATH'] = $this->validateStoragePath((string) $options['storage_path']);
        }
        $existing = EnvFile::read($this->envPath());
        if (trim((string) ($existing['APP_KEY'] ?? '')) === '') {
            $appEnv['APP_KEY'] = bin2hex(random_bytes(16));
        }
        $envResult = EnvFile::update($this->envPath(), array_merge($appEnv, $this->dbEnv($cfg)), $this->root . '/.env.example');
        @chmod($this->envPath(), 0600);
        $step('Write .env', 'ok', ($envResult['backup'] !== null ? 'previous file backed up as ' . basename($envResult['backup']) : 'created') . '; permissions 0600');

        $dbTouched = false;
        try {
            $this->applyRuntime(array_merge($appEnv, $this->dbEnv($cfg)));
            date_default_timezone_set($school['timezone']);

            $dbTouched = true;
            $ran = (new \Database\Migrator())->migrate();
            $step('Migrate schema', 'ok', count($ran) . ' migration(s) applied');

            if ((new \App\Services\InstallService())->isInstalled()) {
                throw new SetupException('An existing installation appeared in this database while installing. No seeding or administrator changes were made.');
            }
            (new \Database\Seeds\DatabaseSeeder())->run(false);
            $step('Seed roles & defaults', 'ok', 'no demo accounts created');

            (new \App\Services\AdminRecoveryService())->resetOrCreate($admin['email'], $admin['password'], $admin['name'], true, 'superadmin', 'installer');
            $step('Create administrator', 'ok', $admin['email']);

            $this->saveSchoolSettings($school);
            $step('School settings', 'ok', $school['school']);

            $this->ensureStorage();
            $step('Storage directories', 'ok', $this->storagePath());

            $v = $this->verify();
            $step('Verify installation', $v['ok'] ? 'ok' : 'warn', implode('; ', $v['messages']));
            if (!$v['ok']) {
                throw new SetupException('Installation finished but verification failed: ' . implode('; ', $v['messages']));
            }

            if (@file_put_contents($this->lockPath(), 'Installed at ' . date('c') . "\nVersion " . self::VERSION . "\n", LOCK_EX) === false) {
                $step('Installation lock', 'warn', 'storage/installed.lock could not be written; the database guard still blocks reinstalling. Fix storage permissions.');
            } else {
                $step('Installation lock', 'ok', 'storage/installed.lock written');
            }
        } catch (\Throwable $e) {
            $this->log($e, 'install');
            $msg = $e instanceof SetupException || $e instanceof \InvalidArgumentException || $e instanceof \DomainException
                ? $e->getMessage() : 'An unexpected error occurred while installing.';
            if (!$dbTouched) {
                try {
                    EnvFile::restore($this->envPath(), $envResult['backup']);
                    $msg .= ' Your previous .env was restored.';
                } catch (\Throwable) {
                    $msg .= ' The previous .env could not be restored automatically; restore it from ' . basename((string) $envResult['backup']) . '.';
                }
            } else {
                $msg .= ' The database was partially changed, so the new .env was kept. Fix the problem and re-run the installer; it resumes additively.';
            }
            throw new SetupException($msg, 0, $e);
        }
        return $steps;
    }

    /**
     * Post-install checks (also usable standalone as `status`).
     *
     * @return array{ok:bool,messages:string[]}
     */
    public function verify(): array
    {
        $m = [];
        $ok = true;
        $status = (new \Database\Migrator())->status();
        $pending = count($status['pending']);
        $m[] = count($status['applied']) . ' migrations applied, ' . $pending . ' pending';
        $ok = $ok && $pending === 0;
        $users = (int) Connection::instance()->scalar('SELECT COUNT(*) FROM `users`');
        $m[] = $users . ' user(s)';
        $ok = $ok && $users >= 1;
        $admins = (int) Connection::instance()->scalar(
            'SELECT COUNT(*) FROM `user_roles` ur JOIN `roles` r ON r.`id` = ur.`role_id` WHERE r.`slug` = ?',
            ['super_admin']
        );
        $m[] = $admins . ' super administrator(s)';
        $ok = $ok && $admins >= 1;
        $perms = (int) Connection::instance()->scalar('SELECT COUNT(*) FROM `permissions`');
        $m[] = $perms . ' permissions';
        $ok = $ok && $perms > 0;
        return ['ok' => $ok, 'messages' => $m];
    }

    /** @return array<string,mixed> */
    public function status(): array
    {
        $out = ['locked' => $this->isLocked(), 'configured' => false, 'reachable' => false, 'engine' => null, 'version' => null,
            'migrations_applied' => 0, 'migrations_pending' => 0, 'users' => 0, 'installed' => false, 'storage' => $this->storagePath()];
        try {
            $conn = Connection::instance();
            $out['configured'] = true;
            $out['engine'] = $conn->engine();
            $out['version'] = (string) $conn->scalar('SELECT VERSION()');
            $out['reachable'] = true;
            $s = (new \Database\Migrator())->status();
            $out['migrations_applied'] = count($s['applied']);
            $out['migrations_pending'] = count($s['pending']);
            $out['installed'] = (new \App\Services\InstallService())->isInstalled();
            $out['users'] = (int) $conn->scalar('SELECT COUNT(*) FROM `users`');
        } catch (\Throwable $e) {
            [, $out['message']] = DatabasePreflight::classify($e);
        }
        return $out;
    }

    // ------------------------------------------------------------------ helpers

    /** @param array<string,string> $admin @param array<string,string> $school */
    public function validateAdmin(array $admin, array $school): void
    {
        if (!filter_var($admin['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            throw new SetupException('Enter a valid administrator email address.');
        }
        $pw = (string) ($admin['password'] ?? '');
        if (strlen($pw) < 8 || strlen($pw) > 4096) {
            throw new SetupException('The administrator password must be at least 8 characters.');
        }
        if (trim((string) ($admin['name'] ?? '')) === '' || strlen((string) $admin['name']) > 150) {
            throw new SetupException('Enter an administrator name (maximum 150 characters).');
        }
        if (trim((string) ($school['school'] ?? '')) === '' || strlen((string) $school['school']) > 190) {
            throw new SetupException('Enter a school name (maximum 190 characters).');
        }
        if (!in_array((string) ($school['timezone'] ?? ''), timezone_identifiers_list(), true)) {
            throw new SetupException('Choose a valid timezone.');
        }
        if (trim((string) ($school['currency'] ?? '')) === '' || strlen((string) $school['currency']) > 8) {
            throw new SetupException('Enter a currency symbol (maximum 8 characters).');
        }
    }

    private function saveSchoolSettings(array $school): void
    {
        $now = date('Y-m-d H:i:s');
        foreach ([['school_name', $school['school']], ['currency', $school['currency']], ['timezone', $school['timezone']]] as [$k, $v]) {
            $q = \Core\Database\QueryBuilder::table('settings')->where('key', $k);
            if ($q->first() !== null) {
                \Core\Database\QueryBuilder::table('settings')->where('key', $k)->update(['value' => $v, 'updated_at' => $now]);
            } else {
                \Core\Database\QueryBuilder::table('settings')->insert(['group' => 'general', 'key' => $k, 'value' => $v, 'type' => 'string', 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    /**
     * An absolute, writable directory (created if missing). Used for STORAGE_PATH so uploads
     * can live outside the web root. Existing contents are never touched.
     */
    public function validateStoragePath(string $path): string
    {
        $path = rtrim(str_replace('\\', '/', trim($path)), '/');
        if ($path === '' || (!str_starts_with($path, '/') && preg_match('#^[A-Za-z]:/#', $path) !== 1)) {
            throw new SetupException('The storage folder must be an absolute path such as /home/youruser/erp-storage.');
        }
        if (str_contains($path, '..') || str_contains($path, "\0") || preg_match('/[\r\n]/', $path) === 1) {
            throw new SetupException('The storage folder path is not valid.');
        }
        if (!is_dir($path) && !@mkdir($path, 0775, true) && !is_dir($path)) {
            throw new SetupException('The storage folder does not exist and could not be created. Create it and make it writable by the web server.');
        }
        if (!is_writable($path)) {
            throw new SetupException('The storage folder is not writable by the web server user.');
        }
        return $path;
    }

    /** Create the runtime storage layout (never touches existing files). */
    public function ensureStorage(): void
    {
        $base = $this->storagePath();
        foreach (['', '/logs', '/cache', '/sessions', '/backups', '/temp', '/uploads', '/uploads/avatars', '/uploads/documents'] as $d) {
            if (!is_dir($base . $d)) {
                @mkdir($base . $d, 0775, true);
            }
        }
        // Block direct web access if the storage folder is (mis)placed under the web root.
        $ht = $base . '/.htaccess';
        if (!is_file($ht) && str_starts_with(realpath($base) ?: $base, realpath($this->root . '/public') ?: "\0")) {
            @file_put_contents($ht, "Require all denied\n");
        }
    }

    private function databaseExists(PDO $server, DatabaseConfig $c): bool
    {
        if ($c->isPostgres()) {
            $st = $server->prepare('SELECT COUNT(*) FROM pg_database WHERE datname = ?');
        } else {
            $st = $server->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = ?');
        }
        $st->execute([$c->database]);
        return (int) $st->fetchColumn() > 0;
    }

    private function log(\Throwable $e, string $ref): void
    {
        $dir = rtrim((string) (\Core\Config\Config::get('paths.logs') ?: $this->root . '/storage/logs'), '/');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        // The exception text is logged server-side only; it is passed through redact() first.
        @file_put_contents($dir . '/install.log', '[' . date('Y-m-d H:i:s') . "] [setup {$ref}] " . get_class($e) . ': ' . self::redact($e->getMessage()) . PHP_EOL, FILE_APPEND);
    }

    /** Remove anything that looks like a credential from a message. */
    public static function redact(string $s): string
    {
        $s = (string) preg_replace('#([a-z][a-z0-9+.-]*://)[^/@\s]*@#i', '$1***@', $s);
        return (string) preg_replace('/(password|passwd|pwd)\s*[=:]\s*\S+/i', '$1=***', $s);
    }
}
