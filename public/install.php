<?php

declare(strict_types=1);

/**
 * ===========================================================================
 * School ERP — Browser-based Installation Wizard
 * ---------------------------------------------------------------------------
 * Self-contained (does not depend on a configured app). Guides a non-technical
 * user through requirement checks, database setup, admin creation and seeding.
 *
 * Safety rules:
 *  - The live database — not storage/installed.lock — is the source of truth.
 *    An existing database with users can NEVER be reinstalled or have its
 *    administrator reset from this public page.
 *  - All POST requests require a CSRF token.
 *  - .env changes are backed up and written atomically; they are restored if
 *    installation fails before the database is modified.
 *  - Unexpected errors are logged with a reference id and never leak SQL or
 *    credentials to the browser.
 *
 * Lost the administrator password? Run on the server:
 *   php bin/console admin:recover --email=admin@example.com
 * ===========================================================================
 */

final class InstallerException extends RuntimeException
{
}

$root = dirname(__DIR__);
$lockFile = $root . '/storage/installed.lock';
$envPath = $root . '/.env';

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// --- Autoloading (framework classes used for DB/env safety) -----------------
if (is_file($root . '/vendor/autoload.php')) {
    require_once $root . '/vendor/autoload.php';
} else {
    require_once $root . '/core/Support/Autoloader.php';
    $autoloader = new \Core\Support\Autoloader();
    $autoloader->addNamespace('App', $root . '/app');
    $autoloader->addNamespace('Core', $root . '/core');
    $autoloader->addNamespace('Database', $root . '/database');
    $autoloader->addFile($root . '/core/Support/helpers.php');
    $autoloader->register();
}

$step = (int) ($_GET['step'] ?? 1);
if (!in_array($step, [1, 2, 3, 4], true)) {
    $step = 1;
}
$errors = [];

/* ==========================================================================
   Helpers
   ========================================================================== */
function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Build a URL that respects subdirectory installs (e.g. http://localhost/erp).
 */
function installer_url(string $path = '/'): string
{
    $dir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
    if ($dir === '.' || $dir === '/') {
        $dir = '';
    }
    if (str_ends_with($dir, '/public')) {
        $dir = substr($dir, 0, -7);
    }

    return $dir . '/' . ltrim($path, '/');
}

function installer_csrf_token(): string
{
    $key = 'installer_csrf';
    if (!isset($_SESSION[$key]) || !is_string($_SESSION[$key]) || strlen($_SESSION[$key]) < 32) {
        $_SESSION[$key] = bin2hex(random_bytes(32));
    }
    return $_SESSION[$key];
}

function installer_csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . h(installer_csrf_token()) . '">';
}

function installer_csrf_ok(): bool
{
    $token = $_POST['_token'] ?? '';
    $expected = $_SESSION['installer_csrf'] ?? '';
    return is_string($token) && is_string($expected) && $expected !== '' && hash_equals($expected, $token);
}

function render(string $title, string $body, int $current = 0): void
{
    $steps = ['Requirements', 'Database', 'Administrator', 'Finish'];
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . h($title) . ' · School ERP Installer</title>'
        . '<script src="https://cdn.tailwindcss.com"></script>'
        . '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">'
        . '<style>.btn{display:inline-flex;align-items:center;gap:.5rem;padding:.6rem 1.1rem;border-radius:.5rem;'
        . 'background:#2563eb;color:#fff;font-weight:600;font-size:.875rem;border:0;cursor:pointer}.btn:hover{background:#1d4ed8}'
        . '.inp{width:100%;border:1px solid #cbd5e1;border-radius:.5rem;padding:.55rem .75rem;font-size:.875rem}'
        . '.lbl{display:block;font-size:.8125rem;font-weight:600;color:#334155;margin:.75rem 0 .25rem}</style></head>'
        . '<body class="min-h-screen bg-slate-50 text-slate-700">'
        . '<div class="mx-auto max-w-2xl px-4 py-10">'
        . '<div class="mb-6 flex items-center gap-2"><span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-blue-600 text-white"><i class="fa-solid fa-graduation-cap"></i></span>'
        . '<span class="text-xl font-bold text-slate-900">School ERP Installer</span></div>';

    if ($current > 0) {
        echo '<ol class="mb-8 flex items-center gap-2 text-xs font-medium">';
        foreach ($steps as $i => $label) {
            $n = $i + 1;
            $cls = $n < $current ? 'bg-green-600 text-white' : ($n === $current ? 'bg-blue-600 text-white' : 'bg-slate-200 text-slate-500');
            echo '<li class="flex items-center gap-2"><span class="inline-flex h-6 w-6 items-center justify-center rounded-full ' . $cls . '">' . $n . '</span>'
                . '<span class="' . ($n === $current ? 'text-slate-900' : 'text-slate-400') . '">' . h($label) . '</span></li>';
            if ($n < count($steps)) {
                echo '<li class="h-px w-6 bg-slate-200"></li>';
            }
        }
        echo '</ol>';
    }

    echo '<div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">' . $body . '</div>'
        . '<p class="mt-6 text-center text-xs text-slate-400">School ERP v1.6.17 — remove public/install.php after installation.</p>'
        . '</div></body></html>';
}

function installer_refuse(string $title, string $body): void
{
    http_response_code(403);
    render($title, '<div class="rounded-lg bg-amber-50 border border-amber-200 p-4 text-sm text-amber-900">'
        . $body . '</div>'
        . '<a href="' . h(installer_url('/login')) . '" class="btn mt-6"><i class="fa-solid fa-right-to-bracket"></i> Go to login</a>');
}

/**
 * @param array<string, string> $d
 * @return string[]
 */
function installer_validate_db(array $d): array
{
    $errors = [];
    $host = (string) ($d['host'] ?? '');
    $port = (int) ($d['port'] ?? 0);
    $name = (string) ($d['name'] ?? '');
    $user = (string) ($d['user'] ?? '');
    $pass = (string) ($d['pass'] ?? '');

    if ($host === '' || strlen($host) > 255 || str_contains($host, ';') || str_contains($host, "\0")) {
        $errors[] = 'Enter a valid database host (for example 127.0.0.1 or localhost).';
    }
    if ($port < 1 || $port > 65535) {
        $errors[] = 'Database port must be between 1 and 65535.';
    }
    if (preg_match('/^[A-Za-z0-9_]{1,64}$/', $name) !== 1) {
        $errors[] = 'Database name may contain only letters, numbers and underscores (maximum 64 characters).';
    } elseif (in_array(strtolower($name), ['information_schema', 'performance_schema', 'mysql', 'sys'], true)) {
        $errors[] = 'Choose a dedicated application database, not a MySQL system database.';
    }
    if ($user === '' || strlen($user) > 255) {
        $errors[] = 'Enter the database username.';
    }
    if (str_contains($pass, "\n") || str_contains($pass, "\r")) {
        $errors[] = 'The database password cannot contain line breaks.';
    }

    return $errors;
}

/**
 * @param array<string, string> $d
 */
function installer_pdo(array $d, ?string $database = null): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%d;%scharset=utf8mb4',
        (string) $d['host'],
        (int) $d['port'],
        $database !== null ? 'dbname=' . $database . ';' : ''
    );

    return new PDO($dsn, (string) $d['user'], (string) $d['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 5,
    ]);
}

/**
 * @return array{tables:int,users:int,has_users_table:bool,installed:bool}
 */
function installer_db_state(PDO $pdo): array
{
    $tables = (int) $pdo->query(
        'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE()'
    )->fetchColumn();

    $hasUsers = (int) $pdo->query(
        "SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'"
    )->fetchColumn() > 0;

    $users = 0;
    if ($hasUsers) {
        $users = (int) $pdo->query('SELECT COUNT(*) AS c FROM `users`')->fetchColumn();
    }

    return [
        'tables'           => $tables,
        'users'            => $users,
        'has_users_table'  => $hasUsers,
        'installed'        => $users > 0,
    ];
}

function installer_database_exists(PDO $pdo, string $name): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = ?');
    $stmt->execute([$name]);
    return (int) $stmt->fetchColumn() > 0;
}

function installer_log(\Throwable $e, string $ref): void
{
    global $root;
    $dir = $root . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents(
        $dir . '/install.log',
        '[' . date('Y-m-d H:i:s') . "] [installer ref={$ref}] " . get_class($e) . ': ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL,
        FILE_APPEND
    );
}

/**
 * Inspect the currently configured database without loading the framework.
 *
 * @return array{configured:bool,reachable:bool,installed:bool,users:int,tables:int,message:string}
 */
function installer_current_state(string $envPath): array
{
    $env = \Core\Support\EnvFile::read($envPath);
    $name = trim((string) ($env['DB_DATABASE'] ?? ''));
    if ($name === '') {
        return ['configured' => false, 'reachable' => false, 'installed' => false, 'users' => 0, 'tables' => 0, 'message' => 'No database is configured yet.'];
    }

    $d = [
        'host' => (string) ($env['DB_HOST'] ?? '127.0.0.1'),
        'port' => (int) ($env['DB_PORT'] ?? 3306),
        'name' => $name,
        'user' => (string) ($env['DB_USERNAME'] ?? ''),
        'pass' => (string) ($env['DB_PASSWORD'] ?? ''),
    ];

    if (installer_validate_db($d) !== []) {
        return ['configured' => true, 'reachable' => false, 'installed' => false, 'users' => 0, 'tables' => 0, 'message' => 'The existing database configuration is incomplete.'];
    }

    try {
        $pdo = installer_pdo($d, $d['name']);
        $state = installer_db_state($pdo);
        return [
            'configured' => true,
            'reachable'  => true,
            'installed'  => $state['installed'],
            'users'      => $state['users'],
            'tables'     => $state['tables'],
            'message'    => $state['installed'] ? 'The configured database contains user accounts.' : 'The configured database has no users yet.',
        ];
    } catch (\Throwable $e) {
        [, $message] = \Core\Database\DatabasePreflight::classify($e);
        return ['configured' => true, 'reachable' => false, 'installed' => false, 'users' => 0, 'tables' => 0, 'message' => $message];
    }
}

/* ==========================================================================
   Guard: an existing database always wins over a missing lock file
   ========================================================================== */
$current = installer_current_state($envPath);

if (is_file($lockFile)) {
    installer_refuse(
        'Already installed',
        'A previous installation marker exists. To protect your school data, this web installer will not reinstall, ' .
        'reset an administrator, or replace the database.<br><br>' .
        'If you lost the administrator password, run on the server:<br>' .
        '<code class="inline-block mt-1 rounded bg-white/70 px-2 py-1">php bin/console admin:recover --email=admin@example.com</code>'
    );
    exit;
}

if ($current['installed']) {
    installer_refuse(
        'Existing installation detected',
        'The configured database already contains ' . (int) $current['users'] . ' user account(s). The web installer ' .
        'will never overwrite or reset a live installation, even if storage/installed.lock is missing.<br><br>' .
        'Use the login page to sign in. If you have lost administrator access, run on the server:<br>' .
        '<code class="inline-block mt-1 rounded bg-white/70 px-2 py-1">php bin/console admin:recover --email=admin@example.com</code>'
    );
    exit;
}

/* ---- Requirement checks -------------------------------------------------- */
function requirementChecks(string $root): array
{
    $checks = [];
    $checks[] = ['PHP >= 8.3', version_compare(PHP_VERSION, '8.3.0', '>='), PHP_VERSION];
    foreach (['pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'json'] as $ext) {
        $checks[] = ["Extension: {$ext}", extension_loaded($ext), extension_loaded($ext) ? 'loaded' : 'missing'];
    }
    foreach (['storage', 'storage/logs', 'storage/cache', 'storage/sessions', 'storage/uploads', 'storage/backups'] as $dir) {
        $path = $root . '/' . $dir;
        $writable = is_dir($path) && is_writable($path);
        $checks[] = ["Writable: {$dir}", $writable, $writable ? 'ok' : 'not writable'];
    }
    return $checks;
}

/* ==========================================================================
   POST handlers
   ========================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if (!installer_csrf_ok()) {
        $errors[] = 'Your session expired or the form was not submitted from this installer. Reload the page and try again.';
        $step = $action === 'install' ? 3 : 2;
    } elseif ($action === 'testdb') {
        $d = [
            'host' => trim((string) ($_POST['db_host'] ?? '127.0.0.1')),
            'port' => (int) ($_POST['db_port'] ?? 3306),
            'name' => trim((string) ($_POST['db_name'] ?? '')),
            'user' => trim((string) ($_POST['db_user'] ?? '')),
            'pass' => (string) ($_POST['db_pass'] ?? ''),
        ];
        $_SESSION['db'] = $d;
        $step = 2;

        $validation = installer_validate_db($d);
        if ($validation !== []) {
            $errors = $validation;
        } else {
            try {
                $server = installer_pdo($d);
                $created = false;

                if (!installer_database_exists($server, $d['name'])) {
                    try {
                        $server->exec(
                            'CREATE DATABASE IF NOT EXISTS `' . $d['name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
                        );
                        $created = true;
                    } catch (\Throwable $e) {
                        installer_log($e, 'db-' . substr(bin2hex(random_bytes(4)), 0, 8));
                        $errors[] = 'The database "' . $d['name'] . '" does not exist and this database user cannot create it. '
                            . 'Create it in phpMyAdmin/cPanel first, or use an account with CREATE DATABASE permission. No changes were made.';
                    }
                }

                if ($errors === []) {
                    $pdo = installer_pdo($d, $d['name']);
                    $state = installer_db_state($pdo);
                    $_SESSION['db_state'] = $state;

                    if ($state['installed']) {
                        $errors[] = 'An existing School ERP installation was found in database "' . $d['name'] . '" ('
                            . (int) $state['users'] . ' user account(s)). To protect your data, the web installer will not '
                            . 'reinstall over it. Use "php bin/console admin:recover" to recover an administrator, or choose a '
                            . 'different, empty database.';
                    } else {
                        if ($state['tables'] > 0) {
                            $_SESSION['installer_notice'] = 'Database "' . $d['name'] . '" contains ' . (int) $state['tables']
                                . ' table(s) but no users. Installation will resume additively; no tables will be dropped.';
                        } elseif ($created) {
                            $_SESSION['installer_notice'] = 'Database "' . $d['name'] . '" was created successfully.';
                        }
                        header('Location: ' . installer_url('/install.php?step=3'));
                        exit;
                    }
                }
            } catch (\Throwable $e) {
                installer_log($e, 'db-' . substr(bin2hex(random_bytes(4)), 0, 8));
                [, $message] = \Core\Database\DatabasePreflight::classify($e);
                $errors[] = 'Database connection failed. ' . $message;
            }
        }
    } elseif ($action === 'install') {
        $a = [
            'name'   => trim((string) ($_POST['admin_name'] ?? '')),
            'email'  => strtolower(trim((string) ($_POST['admin_email'] ?? ''))),
            'pass'   => (string) ($_POST['admin_pass'] ?? ''),
            'school' => trim((string) ($_POST['school_name'] ?? '')),
            'tz'     => trim((string) ($_POST['timezone'] ?? 'UTC')),
            'curr'   => trim((string) ($_POST['currency'] ?? '₹')),
        ];
        $_SESSION['admin'] = [
            'name'   => $a['name'],
            'email'  => $a['email'],
            'school' => $a['school'],
            'tz'     => $a['tz'],
            'curr'   => $a['curr'],
        ];
        $step = 3;
        $d = $_SESSION['db'] ?? null;

        if (is_file($lockFile)) {
            $errors[] = 'The installer was locked by another process. No changes were made.';
        }
        if (!is_array($d) || installer_validate_db($d) !== []) {
            $errors[] = 'Please configure and test the database first.';
        }
        if (!filter_var($a['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid administrator email address.';
        }
        if (strlen($a['pass']) < 8) {
            $errors[] = 'The administrator password must be at least 8 characters.';
        }
        if (strlen($a['pass']) > 4096) {
            $errors[] = 'The administrator password is too long.';
        }
        if ($a['name'] === '' || strlen($a['name']) > 150) {
            $errors[] = 'Enter an administrator name (maximum 150 characters).';
        }
        if ($a['school'] === '' || strlen($a['school']) > 190) {
            $errors[] = 'Enter a school name (maximum 190 characters).';
        }
        if (!in_array($a['tz'], timezone_identifiers_list(), true)) {
            $errors[] = 'Choose a valid timezone.';
        }
        if ($a['curr'] === '' || strlen($a['curr']) > 8) {
            $errors[] = 'Enter a currency symbol (maximum 8 characters).';
        }

        $envResult = null;
        $dbTouched = false;
        $protectedAbort = false;

        if ($errors === [] && is_array($d)) {
            $ref = substr(bin2hex(random_bytes(6)), 0, 12);

            try {
                // Re-check immediately before writing anything: another process
                // may have installed into this database since the test step.
                $pdo = installer_pdo($d, $d['name']);
                $state = installer_db_state($pdo);
                if ($state['installed']) {
                    throw new InstallerException(
                        'An existing installation was detected in this database. No files or data were changed.'
                    );
                }

                $key = bin2hex(random_bytes(16));
                $envResult = \Core\Support\EnvFile::update($envPath, [
                    'APP_ENV'       => 'production',
                    'APP_DEBUG'     => 'false',
                    'APP_KEY'       => $key,
                    'APP_TIMEZONE'  => $a['tz'],
                    'DB_CONNECTION' => 'mysql',
                    'DB_HOST'       => (string) $d['host'],
                    'DB_PORT'       => (string) (int) $d['port'],
                    'DB_DATABASE'   => (string) $d['name'],
                    'DB_USERNAME'   => (string) $d['user'],
                    'DB_PASSWORD'   => (string) $d['pass'],
                ], $root . '/.env.example');

                // Bootstrap the framework with the newly written configuration.
                \Core\Support\Env::load($envPath);
                \Core\Config\Config::load($root . '/config');
                date_default_timezone_set($a['tz']);

                $dbTouched = true;
                (new \Database\Migrator())->migrate();

                if ((new \App\Services\InstallService())->isInstalled()) {
                    $protectedAbort = true;
                    throw new InstallerException(
                        'An existing installation was detected after migration. No seeding or administrator changes were made.'
                    );
                }

                // Disable demo accounts for browser installations.
                (new \Database\Seeds\DatabaseSeeder())->run(false);

                // Always target the seeded superadmin on a fresh install. The
                // recovery service verifies email conflicts and only touches
                // this single account.
                (new \App\Services\AdminRecoveryService())->resetOrCreate(
                    $a['email'],
                    $a['pass'],
                    $a['name'],
                    true,
                    'superadmin',
                    'installer'
                );

                // Persist the school profile (update or insert if a template
                // migration did not create the keys).
                foreach ([['school_name', $a['school']], ['currency', $a['curr']], ['timezone', $a['tz']]] as [$k, $v]) {
                    $exists = \Core\Database\QueryBuilder::table('settings')->where('key', $k)->first();
                    if ($exists !== null) {
                        \Core\Database\QueryBuilder::table('settings')->where('key', $k)->update([
                            'value'      => $v,
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                    } else {
                        \Core\Database\QueryBuilder::table('settings')->insert([
                            'group'      => 'general',
                            'key'        => $k,
                            'value'      => $v,
                            'type'       => 'string',
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                    }
                }

                $lockWarning = '';
                if (@file_put_contents($lockFile, 'Installed at ' . date('c') . "\nVersion 1.6.16\n", LOCK_EX) === false) {
                    $lockWarning = 'The database was installed, but storage/installed.lock could not be written. '
                        . 'The database guard still prevents re-installation; fix storage permissions.';
                }

                unset($_SESSION['db'], $_SESSION['admin'], $_SESSION['db_state'], $_SESSION['installer_notice']);
                $_SESSION['installer_complete'] = true;
                $_SESSION['installer_lock_warning'] = $lockWarning;
                $step = 4;
            } catch (\Throwable $e) {
                installer_log($e, $ref);

                $safeMessage = ($e instanceof InstallerException
                    || $e instanceof \InvalidArgumentException
                    || $e instanceof \DomainException)
                    ? $e->getMessage()
                    : 'An unexpected error occurred while installing.';

                $keepEnv = $dbTouched && !$protectedAbort;
                if (!$keepEnv && $envResult !== null) {
                    try {
                        \Core\Support\EnvFile::restore($envPath, $envResult['backup']);
                        $safeMessage .= ' Your previous .env configuration was restored.';
                    } catch (\Throwable $restoreError) {
                        installer_log($restoreError, $ref);
                        $safeMessage .= ' The previous .env could not be restored automatically; restore it from '
                            . ($envResult['backup'] ?? 'your backup') . '.';
                    }
                } elseif ($keepEnv) {
                    $safeMessage .= ' The database was partially changed, so the new .env was kept. '
                        . 'Re-run the installer or finish with: php bin/console install';
                }

                $errors[] = $safeMessage . ' Reference: ' . $ref . '. Details were written to storage/logs/install.log.';
                $step = 3;
            }
        }
    } else {
        $errors[] = 'Unknown installer action. Reload the page and try again.';
        $step = 1;
    }
}

/* ==========================================================================
   Views per step
   ========================================================================== */
$notice = '';
if (isset($_SESSION['installer_notice']) && is_string($_SESSION['installer_notice']) && $_SESSION['installer_notice'] !== '') {
    $notice = '<div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-800">'
        . h($_SESSION['installer_notice']) . '</div>';
    unset($_SESSION['installer_notice']);
}

$errorHtml = '';
if ($errors) {
    $errorHtml = '<div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">'
        . implode('<br>', array_map('h', $errors)) . '</div>';
}

if ($step === 4) {
    if (($_SESSION['installer_complete'] ?? false) !== true) {
        header('Location: ' . installer_url('/install.php?step=1'));
        exit;
    }

    $lockWarning = (string) ($_SESSION['installer_lock_warning'] ?? '');
    unset($_SESSION['installer_lock_warning']);

    render('Installation complete', '<div class="text-center">'
        . '<span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-green-100 text-2xl text-green-600"><i class="fa-solid fa-check"></i></span>'
        . '<h1 class="mt-4 text-xl font-bold text-slate-900">Installation complete!</h1>'
        . '<p class="mt-2 text-slate-500">Your School ERP is ready. Demo accounts were not installed. For security, delete <code>public/install.php</code> now.</p>'
        . ($lockWarning !== '' ? '<p class="mt-3 rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm text-amber-900">' . h($lockWarning) . '</p>' : '')
        . '<p class="mt-3 text-xs text-slate-500">Lost the administrator password later? Run <code>php bin/console admin:recover --email=...</code> on the server.</p>'
        . '<a href="' . h(installer_url('/login')) . '" class="btn mt-6">Go to login</a></div>', 4);
    exit;
}

if ($step === 3) {
    if (!isset($_SESSION['db']) || !is_array($_SESSION['db'])) {
        header('Location: ' . installer_url('/install.php?step=2'));
        exit;
    }

    $a = $_SESSION['admin'] ?? [];
    render('Administrator & school', $errorHtml . $notice
        . '<h1 class="text-lg font-bold text-slate-900">Administrator & school profile</h1>'
        . '<p class="mt-1 text-sm text-slate-500">Create the super administrator account and basic school details. Only this one administrator is created.</p>'
        . '<form method="POST" action="' . h(installer_url('/install.php?step=3')) . '">'
        . installer_csrf_field()
        . '<input type="hidden" name="action" value="install">'
        . '<label class="lbl">School name</label><input class="inp" name="school_name" value="' . h((string) ($a['school'] ?? 'Greenfield International School')) . '" required>'
        . '<div class="grid grid-cols-2 gap-3"><div><label class="lbl">Timezone</label><input class="inp" name="timezone" value="' . h((string) ($a['tz'] ?? 'Asia/Kolkata')) . '"></div>'
        . '<div><label class="lbl">Currency symbol</label><input class="inp" name="currency" value="' . h((string) ($a['curr'] ?? '₹')) . '"></div></div>'
        . '<label class="lbl">Administrator name</label><input class="inp" name="admin_name" value="' . h((string) ($a['name'] ?? 'Super Admin')) . '" required>'
        . '<label class="lbl">Administrator email</label><input class="inp" type="email" name="admin_email" value="' . h((string) ($a['email'] ?? '')) . '" placeholder="admin@yourschool.com" required>'
        . '<label class="lbl">Administrator password</label><input class="inp" type="password" name="admin_pass" placeholder="At least 8 characters" minlength="8" required>'
        . '<p class="mt-2 text-xs text-slate-500">Existing data is never reset. If this database already contains users the installer stops before writing anything.</p>'
        . '<button class="btn mt-6" type="submit"><i class="fa-solid fa-rocket"></i> Install now</button>'
        . '</form>', 3);
    exit;
}

if ($step === 2) {
    $env = \Core\Support\EnvFile::read($envPath);
    $db = $_SESSION['db'] ?? [
        'host' => (string) ($env['DB_HOST'] ?? '127.0.0.1'),
        'port' => (int) ($env['DB_PORT'] ?? 3306),
        'name' => (string) ($env['DB_DATABASE'] ?? 'school_erp'),
        'user' => (string) ($env['DB_USERNAME'] ?? 'root'),
        'pass' => '',
    ];
    render('Database configuration', $errorHtml . $notice
        . '<h1 class="text-lg font-bold text-slate-900">Database configuration</h1>'
        . '<p class="mt-1 text-sm text-slate-500">Enter your MySQL credentials. The database will be created only if it does not exist and your user has permission.</p>'
        . '<form method="POST" action="' . h(installer_url('/install.php?step=2')) . '">'
        . installer_csrf_field()
        . '<input type="hidden" name="action" value="testdb">'
        . '<div class="grid grid-cols-2 gap-3"><div><label class="lbl">Host</label><input class="inp" name="db_host" value="' . h((string) ($db['host'] ?? '')) . '" required></div>'
        . '<div><label class="lbl">Port</label><input class="inp" name="db_port" value="' . h((string) ($db['port'] ?? 3306)) . '" required></div></div>'
        . '<label class="lbl">Database name</label><input class="inp" name="db_name" value="' . h((string) ($db['name'] ?? '')) . '" required>'
        . '<label class="lbl">Username</label><input class="inp" name="db_user" value="' . h((string) ($db['user'] ?? '')) . '" required>'
        . '<label class="lbl">Password</label><input class="inp" type="password" name="db_pass" value="" placeholder="Re-enter to retry" autocomplete="new-password">'
        . '<p class="mt-2 text-xs text-slate-500">If the database already has users, installation stops. Lost admin access is recovered with <code>php bin/console admin:recover</code>, never this page.</p>'
        . '<button class="btn mt-6" type="submit"><i class="fa-solid fa-plug"></i> Test connection & continue</button>'
        . '</form>', 2);
    exit;
}

/* Step 1 — requirements */
$checks = requirementChecks($root);
$allPass = array_reduce($checks, static fn ($carry, $c) => $carry && $c[1], true);
$rows = '';
foreach ($checks as [$label, $ok, $detail]) {
    $icon = $ok ? '<i class="fa-solid fa-check text-green-600"></i>' : '<i class="fa-solid fa-xmark text-red-600"></i>';
    $rows .= '<tr class="border-b border-slate-100"><td class="py-2">' . h($label) . '</td>'
        . '<td class="py-2 text-slate-400 text-sm">' . h($detail) . '</td>'
        . '<td class="py-2 text-right">' . $icon . '</td></tr>';
}
$next = $allPass
    ? '<a href="' . h(installer_url('/install.php?step=2')) . '" class="btn mt-6"><i class="fa-solid fa-arrow-right"></i> Continue</a>'
    : '<p class="mt-6 rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-800">Please resolve the failing checks above (install PHP extensions / fix folder permissions) and reload this page.</p>';

$preflight = '';
if ($current['configured'] && !$current['reachable']) {
    $preflight = '<div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">'
        . 'The existing database configuration could not be used: ' . h($current['message']) . '</div>';
} elseif ($current['reachable'] && $current['tables'] > 0 && !$current['installed']) {
    $preflight = '<div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">'
        . 'The configured database has ' . (int) $current['tables'] . ' table(s) but no users. Installation will resume additively; existing tables are not dropped.</div>';
}

render('Requirements', $preflight . '<h1 class="text-lg font-bold text-slate-900">System requirements</h1>'
    . '<p class="mt-1 text-sm text-slate-500">Verifying your server meets the requirements.</p>'
    . '<table class="mt-4 w-full text-sm"><tbody>' . $rows . '</tbody></table>' . $next, 1);
