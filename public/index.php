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

// If the app has not been installed yet, send visitors to the web installer.
if (!is_file(dirname(__DIR__) . '/storage/installed.lock') && is_file(__DIR__ . '/install.php')) {
    header('Location: /install.php');
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
