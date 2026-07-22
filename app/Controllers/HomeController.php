<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Http\Request;
use Core\Http\Response;
use Core\Routing\Controller;

/**
 * Handles the public landing page and health-check endpoint.
 */
final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        // Redirect authenticated users to their portal home.
        if (auth()->check()) {
            $user = auth()->user();
            $role = $user['roles'][0] ?? 'student';
            $home = config("auth.home_routes.{$role}", '/admin/dashboard');
            return $this->redirect($home);
        }

        return $this->view('home.index', [
            'title' => 'Welcome',
        ]);
    }

    /**
     * Production health check for Koyeb / load balancers / uptime monitors.
     * Returns HTTP 200 when healthy and 503 when a critical dependency (the
     * database) is unavailable, so orchestrators can react correctly.
     */
    public function health(Request $request): Response
    {
        $start = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
        $root  = dirname(__DIR__, 2);

        // Database connectivity (non-throwing).
        $db = \Core\Database\Connection::instance()->ping();

        // Storage + cache writability.
        $storageWritable = is_writable($root . '/storage') && is_writable($root . '/storage/uploads');
        $cacheWritable   = is_writable($root . '/storage/cache');

        $checks = [
            'database' => $db['ok'] ? 'ok' : 'down',
            'storage'  => $storageWritable ? 'ok' : 'unwritable',
            'cache'    => $cacheWritable ? 'ok' : 'unwritable',
        ];
        $healthy = $db['ok'] && $storageWritable && $cacheWritable;

        $diskFree  = @disk_free_space($root);
        $diskTotal = @disk_total_space($root);

        return $this->json([
            'status'    => $healthy ? 'ok' : 'degraded',
            'app'       => config('app.name'),
            'version'   => config('app.version'),
            'env'       => config('app.env'),
            'checks'    => $checks,
            'php'       => PHP_VERSION,
            'memory'    => [
                'usage_mb' => round(memory_get_usage(true) / 1048576, 2),
                'peak_mb'  => round(memory_get_peak_usage(true) / 1048576, 2),
            ],
            'disk'      => [
                'free_mb'  => $diskFree !== false ? round($diskFree / 1048576, 2) : null,
                'total_mb' => $diskTotal !== false ? round($diskTotal / 1048576, 2) : null,
            ],
            'response_ms' => round((microtime(true) - $start) * 1000, 2),
            'timestamp'   => now(),
        ], $healthy ? 200 : 503);
    }
}
