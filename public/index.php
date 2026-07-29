<?php

declare(strict_types=1);

/**
 * ===========================================================================
 * School ERP - Front Controller
 * ---------------------------------------------------------------------------
 * All HTTP requests are routed through this single entry point.
 * ===========================================================================
 */

use Core\Http\Request;
use Core\Routing\Router;

/** @var \Core\Application $app */
$app = require dirname(__DIR__) . '/bootstrap/app.php';

// Installer gate. The lock file lives on the (often ephemeral) container
// filesystem, so we ALSO treat the system as installed when the database
// already holds an installed instance (users present). This keeps env-driven
// deploys (Koyeb/Docker) from bouncing back to the installer after every
// redeploy, while a genuinely fresh database still routes to the installer.
$installLock = dirname(__DIR__) . '/storage/installed.lock';
if (!is_file($installLock) && is_file(__DIR__ . '/install.php')) {
    $dbInstalled = false;
    try {
        $dbInstalled = (new \App\Services\InstallService())->isInstalled();
    } catch (\Throwable) {
        $dbInstalled = false;
    }
    if ($dbInstalled) {
        @touch($installLock); // persist for subsequent requests on this instance
    } else {
        header('Location: /install.php');
        exit;
    }
}

// Startup data-safety guard: never serve a half-working UI on an unavailable or
// unmigrated database. Show a clear critical error instead of empty data.
if (($guardReason = \App\Support\StartupGuard::check()) !== null) {
    \App\Support\StartupGuard::respond($guardReason);
    exit;
}

/** @var Router $router */
$router = $app->make(Router::class);

// Register middleware aliases used throughout the route definitions.
$router->aliasMiddleware([
    'auth'     => \App\Middleware\Authenticate::class,
    'guest'    => \App\Middleware\RedirectIfAuthenticated::class,
    'csrf'     => \App\Middleware\VerifyCsrfToken::class,
    'can'      => \App\Middleware\Authorize::class,
    'role'     => \App\Middleware\RoleMiddleware::class,
    'security' => \App\Middleware\SecurityHeaders::class,
    'throttle' => \App\Middleware\RateLimit::class,
    'maintenance' => \App\Middleware\MaintenanceMode::class,
    'module'   => \App\Middleware\EnsureModuleEnabled::class,
    'api.auth' => \App\Middleware\ApiAuthenticate::class,
]);

// Load route definitions.
require dirname(__DIR__) . '/routes/web.php';
require dirname(__DIR__) . '/routes/api.php';

// Dispatch the request and send the response.
$request = $app->make(Request::class);
$response = $router->dispatch($request);
$response->send();
