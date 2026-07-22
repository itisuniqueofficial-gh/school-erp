<?php

declare(strict_types=1);

/**
 * Application bootstrap.
 *
 * Registers the autoloader, loads environment + configuration, binds core
 * services into the container, starts the session, and returns the Application.
 */

use Core\Application;
use Core\Config\Config;
use Core\Http\Request;
use Core\Http\Session;
use Core\Auth\Auth;
use Core\View\View;
use Core\Support\Autoloader;
use Core\Support\Env;
use Core\Support\Logger;
use Core\Routing\Router;

$root = dirname(__DIR__);

// --- Autoloading -----------------------------------------------------------
// Prefer Composer's autoloader if present; otherwise use the built-in one.
if (is_file($root . '/vendor/autoload.php')) {
    require $root . '/vendor/autoload.php';
} else {
    require $root . '/core/Support/Autoloader.php';
    $autoloader = new Autoloader();
    $autoloader->addNamespace('App', $root . '/app');
    $autoloader->addNamespace('Core', $root . '/core');
    $autoloader->addNamespace('Database', $root . '/database');
    $autoloader->addFile($root . '/core/Support/helpers.php');
    $autoloader->register();
}

// --- Environment & Configuration -------------------------------------------
Env::load($root . '/.env');
Config::load($root . '/config');

date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));

// --- Error & Exception handling --------------------------------------------
$debug = (bool) Config::get('app.debug', false);
error_reporting($debug ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');

// --- Application container --------------------------------------------------
$app = new Application($root);

$app->singleton(Logger::class, fn (): Logger => new Logger());
$app->singleton(Request::class, fn (): Request => Request::capture());
$app->singleton(Session::class, fn (): Session => new Session());
$app->singleton(View::class, fn (): View => new View());
$app->singleton(Auth::class, fn (Application $a): Auth => new Auth($a->make(Session::class)));
$app->singleton(Router::class, fn (): Router => new Router());

// --- Global exception handler ----------------------------------------------
set_exception_handler(static function (\Throwable $e) use ($debug): void {
    // Validation exceptions from API requests become 422 JSON responses.
    if ($e instanceof \Core\Validation\ValidationException) {
        if (!headers_sent()) {
            http_response_code(422);
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'errors'  => $e->errors(),
        ]);
        return;
    }

    try {
        app(Logger::class)->error($e->getMessage(), [
            'exception' => get_class($e),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
            // The previous exception carries the real driver error (host/port).
            'detail'    => $e->getPrevious()?->getMessage(),
        ]);
    } catch (\Throwable) {
        // ignore logging failures
    }

    // Database connectivity failures get a dedicated, credential-free 503 page.
    if ($e instanceof \Core\Database\DatabaseConnectionException) {
        if (!headers_sent()) {
            http_response_code(503);
            header('Content-Type: text/html; charset=UTF-8');
            header('Retry-After: 30');
        }
        try {
            echo view('errors.database', [
                'code'    => 503,
                'title'   => 'Service Unavailable',
                'message' => 'Unable to connect to the database. Please check your database configuration or contact the administrator.',
                'detail'  => $debug ? ($e->getPrevious()?->getMessage() ?? $e->getMessage()) : null,
            ]);
        } catch (\Throwable) {
            echo '<h1>503 Service Unavailable</h1><p>Unable to connect to the database. Please contact the administrator.</p>';
        }
        exit;
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }

    if ($debug) {
        echo '<pre style="padding:20px;font-family:monospace;">';
        echo 'Uncaught ' . get_class($e) . ': ' . htmlspecialchars($e->getMessage()) . "\n\n";
        echo htmlspecialchars($e->getFile()) . ':' . $e->getLine() . "\n\n";
        echo htmlspecialchars($e->getTraceAsString());
        echo '</pre>';
    } else {
        $view = Config::get('paths.views') . '/errors/generic.php';
        if (is_file($view)) {
            try {
                echo view('errors.generic', ['code' => 500, 'title' => 'Server Error', 'message' => 'Something went wrong. Please try again later.']);
            } catch (\Throwable) {
                echo '<h1>500 Server Error</h1>';
            }
        } else {
            echo '<h1>500 Server Error</h1>';
        }
    }
    exit;
});

// --- Session ----------------------------------------------------------------
$app->make(Session::class)->start();

// --- Share common view data -------------------------------------------------
$view = $app->make(View::class);
$view->share('appUrl', Config::get('app.url'));
$view->share('appVersion', Config::get('app.version', '1.0.0'));

// DB-backed branding (falls back to config when unset or DB unavailable), so
// the school name / footer / login copy are never hard-coded in templates.
$__brandDefaults = [
    'appName'         => (string) Config::get('app.name', 'School ERP'),
    'appShortName'    => '',
    'appFooterText'   => '',
    'appCopyright'    => '',
    'appSupportEmail' => '',
    'appLoginWelcome' => '',
    'appLoginDesc'    => '',
    'appLogo'         => '',
];
try {
    $inst = new \App\Services\InstitutionService();
    $map = [
        'appName' => 'name', 'appShortName' => 'short_name', 'appFooterText' => 'footer_text',
        'appCopyright' => 'copyright', 'appSupportEmail' => 'support_email',
        'appLoginWelcome' => 'login_welcome', 'appLoginDesc' => 'login_desc', 'appLogo' => 'logo',
    ];
    foreach ($map as $shareKey => $profileKey) {
        $view->share($shareKey, $inst->profile($profileKey, $__brandDefaults[$shareKey]));
    }
} catch (\Throwable) {
    foreach ($__brandDefaults as $k => $v) {
        $view->share($k, $v);
    }
}

return $app;
