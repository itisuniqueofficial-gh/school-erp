<?php

declare(strict_types=1);

/**
 * ===========================================================================
 * School ERP — Browser-based Installation Wizard
 * ---------------------------------------------------------------------------
 * Self-contained (does not depend on a configured app). Guides a non-technical
 * user through requirement checks, database setup, admin creation and seeding.
 * After success it writes storage/installed.lock and refuses to run again.
 *
 * Delete this file (public/install.php) after installation for extra safety.
 * ===========================================================================
 */

session_start();

$root = dirname(__DIR__);
$lockFile = $root . '/storage/installed.lock';

/* ---- Guard: refuse if already installed ---------------------------------- */
if (is_file($lockFile)) {
    http_response_code(403);
    render('Already installed', '<div class="rounded-lg bg-amber-50 border border-amber-200 p-4 text-amber-800">'
        . 'The application is already installed. For security, delete <code>public/install.php</code> '
        . 'and <code>storage/installed.lock</code> if you need to re-install.</div>'
        . '<a href="/" class="btn mt-6">Go to application</a>');
    exit;
}

$step = (int) ($_GET['step'] ?? 1);
$errors = [];

/* ---- Helpers ------------------------------------------------------------- */
function h(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

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
            if ($n < count($steps)) { echo '<li class="h-px w-6 bg-slate-200"></li>'; }
        }
        echo '</ol>';
    }

    echo '<div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">' . $body . '</div>'
        . '<p class="mt-6 text-center text-xs text-slate-400">School ERP v1.0.0 — remove public/install.php after installation.</p>'
        . '</div></body></html>';
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
    $action = $_POST['action'] ?? '';

    if ($action === 'testdb') {
        $_SESSION['db'] = [
            'host' => trim($_POST['db_host'] ?? '127.0.0.1'),
            'port' => (int) ($_POST['db_port'] ?? 3306),
            'name' => trim($_POST['db_name'] ?? ''),
            'user' => trim($_POST['db_user'] ?? ''),
            'pass' => (string) ($_POST['db_pass'] ?? ''),
        ];
        try {
            $d = $_SESSION['db'];
            $pdo = new PDO("mysql:host={$d['host']};port={$d['port']}", $d['user'], $d['pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$d['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            header('Location: /install.php?step=3');
            exit;
        } catch (\Throwable $e) {
            $errors[] = 'Database connection failed: ' . $e->getMessage();
            $step = 2;
        }
    }

    if ($action === 'install') {
        $_SESSION['admin'] = [
            'name'  => trim($_POST['admin_name'] ?? 'Super Admin'),
            'email' => trim($_POST['admin_email'] ?? ''),
            'pass'  => (string) ($_POST['admin_pass'] ?? ''),
            'school'=> trim($_POST['school_name'] ?? 'My School'),
            'tz'    => trim($_POST['timezone'] ?? 'UTC'),
            'curr'  => trim($_POST['currency'] ?? '₹'),
        ];
        $a = $_SESSION['admin'];
        $d = $_SESSION['db'] ?? null;

        if ($d === null) { $errors[] = 'Please configure the database first.'; $step = 2; }
        elseif (!filter_var($a['email'], FILTER_VALIDATE_EMAIL)) { $errors[] = 'Enter a valid administrator email.'; $step = 3; }
        elseif (strlen($a['pass']) < 8) { $errors[] = 'Administrator password must be at least 8 characters.'; $step = 3; }
        else {
            try {
                // 1. Write .env
                $key = bin2hex(random_bytes(16));
                $envExample = (string) file_get_contents($root . '/.env.example');
                $env = strtr($envExample, [
                    'APP_KEY=' . "\n"        => "APP_KEY={$key}\n",
                    'APP_ENV=production'     => 'APP_ENV=production',
                    'DB_HOST=127.0.0.1'      => 'DB_HOST=' . $d['host'],
                    'DB_PORT=3306'           => 'DB_PORT=' . $d['port'],
                    'DB_DATABASE=school_erp' => 'DB_DATABASE=' . $d['name'],
                    'DB_USERNAME=root'       => 'DB_USERNAME=' . $d['user'],
                    'DB_PASSWORD='."\n"      => 'DB_PASSWORD=' . $d['pass'] . "\n",
                    'APP_TIMEZONE=Asia/Kolkata' => 'APP_TIMEZONE=' . $a['tz'],
                ]);
                file_put_contents($root . '/.env', $env);

                // 2. Bootstrap the framework with the new configuration.
                require $root . '/core/Support/Autoloader.php';
                $al = new \Core\Support\Autoloader();
                $al->addNamespace('App', $root . '/app');
                $al->addNamespace('Core', $root . '/core');
                $al->addNamespace('Database', $root . '/database');
                $al->addFile($root . '/core/Support/helpers.php');
                $al->register();
                \Core\Support\Env::load($root . '/.env');
                \Core\Config\Config::load($root . '/config');

                // 3. Run migrations + seed.
                (new \Database\Migrator())->migrate();
                (new \Database\Seeds\DatabaseSeeder())->run();

                // 4. Configure the super admin account with the chosen credentials.
                //    Target the stable username (email may be changed to the
                //    admin's address, so it is not a reliable key on re-run).
                \Core\Database\QueryBuilder::table('users')
                    ->where('username', 'superadmin')
                    ->update([
                        'name'     => $a['name'],
                        'email'    => $a['email'],
                        'password' => \Core\Security\Hash::make($a['pass']),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);

                // 5. Persist school profile settings.
                foreach ([['school_name', $a['school']], ['currency', $a['curr']], ['timezone', $a['tz']]] as [$k, $v]) {
                    $exists = \Core\Database\QueryBuilder::table('settings')->where('key', $k)->first();
                    if ($exists) {
                        \Core\Database\QueryBuilder::table('settings')->where('key', $k)->update(['value' => $v]);
                    }
                }

                // 6. Lock the installer.
                file_put_contents($lockFile, 'Installed at ' . date('c') . "\n");

                unset($_SESSION['db'], $_SESSION['admin']);
                $step = 4;
            } catch (\Throwable $e) {
                $errors[] = 'Installation failed: ' . $e->getMessage();
                @unlink($root . '/.env');
                $step = 3;
            }
        }
    }
}

/* ==========================================================================
   Views per step
   ========================================================================== */
$errorHtml = '';
if ($errors) {
    $errorHtml = '<div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">'
        . implode('<br>', array_map('h', $errors)) . '</div>';
}

if ($step === 4) {
    render('Installation complete', '<div class="text-center">'
        . '<span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-green-100 text-2xl text-green-600"><i class="fa-solid fa-check"></i></span>'
        . '<h1 class="mt-4 text-xl font-bold text-slate-900">Installation complete!</h1>'
        . '<p class="mt-2 text-slate-500">Your School ERP is ready. For security, delete <code>public/install.php</code> now.</p>'
        . '<a href="/login" class="btn mt-6">Go to login</a></div>', 4);
    exit;
}

if ($step === 3) {
    render('Administrator & school', $errorHtml
        . '<h1 class="text-lg font-bold text-slate-900">Administrator & school profile</h1>'
        . '<p class="mt-1 text-sm text-slate-500">Create the super administrator account and basic school details.</p>'
        . '<form method="POST" action="/install.php?step=3">'
        . '<input type="hidden" name="action" value="install">'
        . '<label class="lbl">School name</label><input class="inp" name="school_name" value="Greenfield International School" required>'
        . '<div class="grid grid-cols-2 gap-3"><div><label class="lbl">Timezone</label><input class="inp" name="timezone" value="Asia/Kolkata"></div>'
        . '<div><label class="lbl">Currency symbol</label><input class="inp" name="currency" value="₹"></div></div>'
        . '<label class="lbl">Administrator name</label><input class="inp" name="admin_name" value="Super Admin" required>'
        . '<label class="lbl">Administrator email</label><input class="inp" type="email" name="admin_email" placeholder="admin@yourschool.com" required>'
        . '<label class="lbl">Administrator password</label><input class="inp" type="password" name="admin_pass" placeholder="At least 8 characters" required>'
        . '<button class="btn mt-6" type="submit"><i class="fa-solid fa-rocket"></i> Install now</button>'
        . '</form>', 3);
    exit;
}

if ($step === 2) {
    $db = $_SESSION['db'] ?? ['host' => '127.0.0.1', 'port' => 3306, 'name' => 'school_erp', 'user' => 'root', 'pass' => ''];
    render('Database configuration', $errorHtml
        . '<h1 class="text-lg font-bold text-slate-900">Database configuration</h1>'
        . '<p class="mt-1 text-sm text-slate-500">Enter your MySQL credentials. The database will be created if it does not exist.</p>'
        . '<form method="POST" action="/install.php?step=2">'
        . '<input type="hidden" name="action" value="testdb">'
        . '<div class="grid grid-cols-2 gap-3"><div><label class="lbl">Host</label><input class="inp" name="db_host" value="' . h($db['host']) . '" required></div>'
        . '<div><label class="lbl">Port</label><input class="inp" name="db_port" value="' . h((string) $db['port']) . '" required></div></div>'
        . '<label class="lbl">Database name</label><input class="inp" name="db_name" value="' . h($db['name']) . '" required>'
        . '<label class="lbl">Username</label><input class="inp" name="db_user" value="' . h($db['user']) . '" required>'
        . '<label class="lbl">Password</label><input class="inp" type="password" name="db_pass" value="">'
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
    ? '<a href="/install.php?step=2" class="btn mt-6"><i class="fa-solid fa-arrow-right"></i> Continue</a>'
    : '<p class="mt-6 rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-800">Please resolve the failing checks above (install PHP extensions / fix folder permissions) and reload this page.</p>';

render('Requirements', '<h1 class="text-lg font-bold text-slate-900">System requirements</h1>'
    . '<p class="mt-1 text-sm text-slate-500">Verifying your server meets the requirements.</p>'
    . '<table class="mt-4 w-full text-sm"><tbody>' . $rows . '</tbody></table>' . $next, 1);
