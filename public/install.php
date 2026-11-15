<?php

declare(strict_types=1);

/**
 * ===========================================================================
 * School ERP — Browser-based Installation Wizard
 * ---------------------------------------------------------------------------
 * Guides a non-technical user through requirement checks, database setup (MySQL,
 * MariaDB or PostgreSQL; manual fields or a connection URL), admin creation and
 * seeding. It contains NO installation logic of its own: everything is done by
 * App\Services\Setup\SetupService, the same backend as `php bin/console install`.
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

use App\Services\Setup\SetupException;
use App\Services\Setup\SetupService;
use Core\Database\DatabaseConfig;

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

$setup = new SetupService($root);
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
        echo '<ol class="mb-8 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs font-medium">';
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
        . '<p class="mt-6 text-center text-xs text-slate-400">School ERP v1.7.0 — remove public/install.php after installation.</p>'
        . '</div></body></html>';
}

function installer_refuse(string $title, string $body): void
{
    http_response_code(403);
    render($title, '<div class="rounded-lg bg-amber-50 border border-amber-200 p-4 text-sm text-amber-900">'
        . $body . '</div>'
        . '<a href="' . h(installer_url('/login')) . '" class="btn mt-6"><i class="fa-solid fa-right-to-bracket"></i> Go to login</a>');
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
        '[' . date('Y-m-d H:i:s') . "] [installer ref={$ref}] " . get_class($e) . ': ' . SetupService::redact($e->getMessage())
        . ' in ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL,
        FILE_APPEND
    );
}

/* ==========================================================================
   Guard: an existing database always wins over a missing lock file
   ========================================================================== */
$current = $setup->currentState();

if ($setup->isLocked()) {
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

/* ==========================================================================
   POST handlers
   ========================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if (!installer_csrf_ok()) {
        $errors[] = 'Your session expired or the form was not submitted from this installer. Reload the page and try again.';
        $step = $action === 'install' ? 3 : 2;
    } elseif ($action === 'testdb') {
        $step = 2;
        $form = [
            'method' => (($_POST['method'] ?? 'manual') === 'url') ? 'url' : 'manual',
            'engine' => (string) ($_POST['db_engine'] ?? 'mysql'),
            'host' => trim((string) ($_POST['db_host'] ?? '127.0.0.1')), 'port' => trim((string) ($_POST['db_port'] ?? '')),
            'name' => trim((string) ($_POST['db_name'] ?? '')), 'user' => trim((string) ($_POST['db_user'] ?? '')),
            'socket' => trim((string) ($_POST['db_socket'] ?? '')), 'ssl' => (string) ($_POST['db_ssl'] ?? 'disable'),
            'schema' => trim((string) ($_POST['db_schema'] ?? '')), 'create' => isset($_POST['create_db']),
        ];
        $_SESSION['db_form'] = $form;   // never contains the password or the URL
        try {
            $cfg = $form['method'] === 'url'
                ? DatabaseConfig::fromUrl((string) ($_POST['db_url'] ?? ''))
                : DatabaseConfig::fromArray([
                    'engine' => $form['engine'], 'host' => $form['host'], 'port' => $form['port'], 'database' => $form['name'],
                    'username' => $form['user'], 'password' => (string) ($_POST['db_pass'] ?? ''), 'socket' => $form['socket'],
                    'ssl_mode' => $form['ssl'], 'schema' => $form['schema'],
                ]);
            if ($cfg->database === '') {
                throw new InvalidArgumentException('Enter the database name.');
            }
            $i = $setup->inspectConnection($cfg);
            if (!$i['ok']) {
                $errors[] = 'Database connection failed. ' . $i['message'];
            } else {
                $created = false;
                if (!$i['database_exists']) {
                    if (!$form['create']) {
                        $errors[] = 'Connected, but the database "' . $cfg->database . '" does not exist. Tick "Create the database" or create it in your hosting panel first. No changes were made.';
                    } else {
                        $setup->createDatabase($cfg);
                        $created = true;
                        $i = $setup->inspectConnection($cfg);
                    }
                }
                if ($errors === []) {
                    if ($i['installed']) {
                        $errors[] = 'An existing School ERP installation was found in database "' . $cfg->database . '" (' . (int) $i['users']
                            . ' user account(s)). The web installer will not install over it. Use "php bin/console admin:recover" to recover an administrator, or choose a different, empty database.';
                    } else {
                        $_SESSION['db_cfg'] = $cfg->toArray();   // server-side session only; removed after install
                        $_SESSION['installer_notice'] = $created
                            ? 'Database "' . $cfg->database . '" was created. ' . $i['version']
                            : ($i['tables'] > 0
                                ? 'Database "' . $cfg->database . '" contains ' . (int) $i['tables'] . ' table(s) but no users. Installation resumes additively; no tables will be dropped.'
                                : 'Connected to ' . $i['version'] . '.');
                        header('Location: ' . installer_url('/install.php?step=3'));
                        exit;
                    }
                }
            }
        } catch (InvalidArgumentException | SetupException $e) {
            $errors[] = $e->getMessage();
        } catch (\Throwable $e) {
            installer_log($e, 'db-' . substr(bin2hex(random_bytes(4)), 0, 8));
            $errors[] = 'Database connection failed. Check the settings and try again.';
        }
    } elseif ($action === 'install') {
        $a = [
            'name'   => trim((string) ($_POST['admin_name'] ?? '')),
            'email'  => strtolower(trim((string) ($_POST['admin_email'] ?? ''))),
            'pass'   => (string) ($_POST['admin_pass'] ?? ''),
            'school' => trim((string) ($_POST['school_name'] ?? '')),
            'tz'     => trim((string) ($_POST['timezone'] ?? 'UTC')),
            'curr'   => trim((string) ($_POST['currency'] ?? '₹')),
            'storage' => trim((string) ($_POST['storage_path'] ?? '')),
        ];
        $_SESSION['admin'] = ['name' => $a['name'], 'email' => $a['email'], 'school' => $a['school'], 'tz' => $a['tz'], 'curr' => $a['curr'], 'storage' => $a['storage']];
        $step = 3;
        $ref = substr(bin2hex(random_bytes(6)), 0, 12);
        try {
            if (!isset($_SESSION['db_cfg']) || !is_array($_SESSION['db_cfg'])) {
                throw new SetupException('Please configure and test the database first.');
            }
            $steps = $setup->install(
                DatabaseConfig::fromArray($_SESSION['db_cfg']),
                ['name' => $a['name'], 'email' => $a['email'], 'password' => $a['pass']],
                ['school' => $a['school'], 'timezone' => $a['tz'], 'currency' => $a['curr']],
                ['create_database' => !empty($_SESSION['db_form']['create']), 'storage_path' => $a['storage']]
            );
            $_SESSION['installer_steps'] = $steps;
            $_SESSION['installer_complete'] = true;
            unset($_SESSION['db_cfg'], $_SESSION['db_form'], $_SESSION['admin'], $_SESSION['installer_notice']);
            $step = 4;
        } catch (SetupException $e) {
            installer_log($e, $ref);
            $errors[] = $e->getMessage() . ' Reference: ' . $ref . '.';
        } catch (\Throwable $e) {
            installer_log($e, $ref);
            $errors[] = 'An unexpected error occurred while installing. Reference: ' . $ref . '. Details were written to storage/logs/install.log.';
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
    $errorHtml = '<div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">'
        . implode('<br>', array_map('h', $errors)) . '</div>';
}

if ($step === 4) {
    if (($_SESSION['installer_complete'] ?? false) !== true) {
        header('Location: ' . installer_url('/install.php?step=1'));
        exit;
    }
    $rows = '';
    foreach ((array) ($_SESSION['installer_steps'] ?? []) as $r) {
        $c = $r['status'] === 'ok' ? 'text-green-700' : 'text-amber-700';
        $rows .= '<tr class="border-b border-slate-100"><td class="py-1.5 pr-3 text-sm">' . h((string) $r['step']) . '</td><td class="py-1.5 text-xs text-slate-500">'
            . h((string) $r['detail']) . '</td><td class="py-1.5 text-right text-xs font-semibold ' . $c . '">' . h(strtoupper((string) $r['status'])) . '</td></tr>';
    }
    unset($_SESSION['installer_steps']);
    render('Installation complete', '<div class="text-center">'
        . '<span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-green-100 text-2xl text-green-600"><i class="fa-solid fa-check"></i></span>'
        . '<h1 class="mt-4 text-xl font-bold text-slate-900">Installation complete!</h1>'
        . '<p class="mt-2 text-slate-500">Your School ERP is ready. Demo accounts were not installed. For security, delete <code>public/install.php</code> now.</p></div>'
        . '<table class="mt-5 w-full"><tbody>' . $rows . '</tbody></table>'
        . '<p class="mt-3 text-xs text-slate-500">Lost the administrator password later? Run <code>php bin/console admin:recover --email=...</code> on the server.</p>'
        . '<div class="text-center"><a href="' . h(installer_url('/login')) . '" class="btn mt-6">Go to login</a></div>', 4);
    exit;
}

if ($step === 3) {
    if (!isset($_SESSION['db_cfg']) || !is_array($_SESSION['db_cfg'])) {
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
        . '<label class="lbl">School name</label><input class="inp" name="school_name" value="' . h((string) ($a['school'] ?? '')) . '" placeholder="Your school name" required>'
        . '<div class="grid grid-cols-2 gap-3"><div><label class="lbl">Timezone</label><input class="inp" name="timezone" value="' . h((string) ($a['tz'] ?? 'Asia/Kolkata')) . '"></div>'
        . '<div><label class="lbl">Currency symbol</label><input class="inp" name="currency" value="' . h((string) ($a['curr'] ?? '₹')) . '"></div></div>'
        . '<label class="lbl">Administrator name</label><input class="inp" name="admin_name" value="' . h((string) ($a['name'] ?? 'Administrator')) . '" required>'
        . '<label class="lbl">Administrator email</label><input class="inp" type="email" name="admin_email" value="' . h((string) ($a['email'] ?? '')) . '" placeholder="admin@yourschool.com" required>'
        . '<label class="lbl">Administrator password</label><input class="inp" type="password" name="admin_pass" placeholder="At least 8 characters" minlength="8" required autocomplete="new-password">'
        . '<details class="mt-4 text-sm"><summary class="cursor-pointer font-medium text-slate-700">File storage (optional)</summary>'
        . '<label class="lbl">Storage folder for uploads, documents and backups</label>'
        . '<input class="inp" name="storage_path" value="' . h((string) ($a['storage'] ?? '')) . '" placeholder="Leave empty to use ' . h($root) . '/storage">'
        . '<p class="mt-1 text-xs text-slate-500">Use an absolute path <strong>outside</strong> the web root (for example <code>/home/youruser/erp-storage</code>) on cPanel / VPS. '
        . 'Amazon S3 / Cloudflare R2 can be enabled later in <code>.env</code> (see docs/STORAGE.md).</p></details>'
        . '<p class="mt-3 text-xs text-slate-500">Existing data is never reset. If this database already contains users the installer stops before writing anything.</p>'
        . '<button class="btn mt-6" type="submit"><i class="fa-solid fa-rocket"></i> Install now</button>'
        . '</form>', 3);
    exit;
}

if ($step === 2) {
    $env = \Core\Support\EnvFile::read($envPath);
    $f = $_SESSION['db_form'] ?? [
        'method' => 'manual', 'engine' => (string) ($env['DB_CONNECTION'] ?? 'mysql'), 'host' => (string) ($env['DB_HOST'] ?? '127.0.0.1'),
        'port' => (string) ($env['DB_PORT'] ?? '3306'), 'name' => (string) ($env['DB_DATABASE'] ?? 'school_erp'), 'user' => (string) ($env['DB_USERNAME'] ?? 'root'),
        'socket' => '', 'ssl' => 'disable', 'schema' => '', 'create' => false,
    ];
    $drivers = $setup->databaseDrivers();
    $opt = static function (string $v, string $label, bool $ok) use ($f): string {
        return '<option value="' . h($v) . '"' . (($f['engine'] ?? '') === $v ? ' selected' : '') . ($ok ? '' : ' disabled') . '>' . h($label . ($ok ? '' : ' (PHP driver not installed)')) . '</option>';
    };
    $ssl = '';
    foreach (['disable' => 'Off', 'prefer' => 'Prefer', 'require' => 'Require', 'verify-ca' => 'Verify CA', 'verify-full' => 'Verify full'] as $v => $l) {
        $ssl .= '<option value="' . $v . '"' . (($f['ssl'] ?? 'disable') === $v ? ' selected' : '') . '>' . $l . '</option>';
    }
    render('Database configuration', $errorHtml . $notice
        . '<h1 class="text-lg font-bold text-slate-900">Database configuration</h1>'
        . '<p class="mt-1 text-sm text-slate-500">Choose the database engine and enter its connection details, or paste a connection URL.</p>'
        . '<form method="POST" action="' . h(installer_url('/install.php?step=2')) . '">'
        . installer_csrf_field()
        . '<input type="hidden" name="action" value="testdb">'
        . '<label class="lbl">Database engine</label><select class="inp" name="db_engine" id="db_engine">'
        . $opt('mysql', 'MySQL', $drivers['pdo_mysql (MySQL / MariaDB)']) . $opt('mariadb', 'MariaDB', $drivers['pdo_mysql (MySQL / MariaDB)'])
        . $opt('pgsql', 'PostgreSQL', $drivers['pdo_pgsql (PostgreSQL)']) . '</select>'
        . '<fieldset class="mt-3"><legend class="lbl">Connection method</legend>'
        . '<label class="mr-4 text-sm"><input type="radio" name="method" value="manual"' . (($f['method'] ?? 'manual') === 'manual' ? ' checked' : '') . '> Enter details</label>'
        . '<label class="text-sm"><input type="radio" name="method" value="url"' . (($f['method'] ?? '') === 'url' ? ' checked' : '') . '> Connection URL</label></fieldset>'
        . '<div class="grid grid-cols-2 gap-3"><div><label class="lbl">Host</label><input class="inp" name="db_host" value="' . h((string) $f['host']) . '"></div>'
        . '<div><label class="lbl">Port</label><input class="inp" name="db_port" id="db_port" value="' . h((string) $f['port']) . '"></div></div>'
        . '<label class="lbl">Database name</label><input class="inp" name="db_name" value="' . h((string) $f['name']) . '">'
        . '<label class="lbl">Username</label><input class="inp" name="db_user" value="' . h((string) $f['user']) . '" autocomplete="off">'
        . '<label class="lbl">Password</label><input class="inp" type="password" name="db_pass" value="" placeholder="Re-enter to retry" autocomplete="new-password">'
        . '<label class="lbl">…or connection URL</label><input class="inp" type="password" name="db_url" value="" placeholder="postgres://user:pass@host:5432/database?sslmode=require" autocomplete="off">'
        . '<p class="mt-1 text-xs text-slate-500">Used only when "Connection URL" is selected. Percent-encode special characters in the password (@ becomes %40). It is never shown again.</p>'
        . '<details class="mt-3 text-sm"><summary class="cursor-pointer font-medium text-slate-700">Advanced (socket, SSL, schema)</summary>'
        . '<label class="lbl">Unix socket (optional, replaces host/port)</label><input class="inp" name="db_socket" value="' . h((string) ($f['socket'] ?? '')) . '" placeholder="/var/run/mysqld/mysqld.sock">'
        . '<label class="lbl">SSL mode</label><select class="inp" name="db_ssl">' . $ssl . '</select>'
        . '<label class="lbl">PostgreSQL schema (optional)</label><input class="inp" name="db_schema" value="' . h((string) ($f['schema'] ?? '')) . '" placeholder="public"></details>'
        . '<label class="mt-4 flex items-center gap-2 text-sm"><input type="checkbox" name="create_db" value="1"' . (!empty($f['create']) ? ' checked' : '') . '> Create the database if it does not exist (needs permission; never drops anything)</label>'
        . '<p class="mt-2 text-xs text-slate-500">If the database already has users, installation stops. Lost admin access is recovered with <code>php bin/console admin:recover</code>, never this page.</p>'
        . '<button class="btn mt-6" type="submit"><i class="fa-solid fa-plug"></i> Test connection & continue</button>'
        . '</form>'
        . '<script>document.getElementById("db_engine").addEventListener("change",function(){var p=document.getElementById("db_port");'
        . 'if(p.value===""||p.value==="3306"||p.value==="5432"){p.value=this.value==="pgsql"?"5432":"3306";}});</script>', 2);
    exit;
}

/* Step 1 — environment & requirements */
$checks = $setup->requirements();
$allPass = $setup->requirementsMet();
$rows = '';
foreach ($checks as $c) {
    $icon = $c['status'] === 'pass' ? '<i class="fa-solid fa-check text-green-600"></i>'
        : ($c['status'] === 'warn' ? '<i class="fa-solid fa-triangle-exclamation text-amber-500"></i>' : '<i class="fa-solid fa-xmark text-red-600"></i>');
    $rows .= '<tr class="border-b border-slate-100"><td class="py-2">' . h($c['label']) . '</td>'
        . '<td class="py-2 text-slate-400 text-sm">' . h($c['detail']) . '</td>'
        . '<td class="py-2 text-right">' . $icon . '</td></tr>';
}
$envInfo = $setup->environment($_SERVER);
$info = '<dl class="mt-4 grid grid-cols-3 gap-x-3 gap-y-1 rounded-lg bg-slate-100 p-3 text-xs">';
foreach (['os' => 'System', 'php' => 'PHP', 'web_server' => 'Web server', 'project_root' => 'Folder', 'base_path' => 'URL path', 'storage_path' => 'Storage'] as $k => $l) {
    $info .= '<dt class="font-semibold text-slate-600">' . h($l) . '</dt><dd class="col-span-2 break-all text-slate-700">' . h((string) ($envInfo[$k] === '' ? '/ (site root)' : $envInfo[$k])) . '</dd>';
}
$info .= '</dl>';
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
    . '<p class="mt-1 text-sm text-slate-500">Detected environment and requirements. At least one database driver is needed.</p>'
    . $info . '<table class="mt-4 w-full text-sm"><tbody>' . $rows . '</tbody></table>' . $next, 1);
