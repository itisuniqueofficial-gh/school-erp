<?php

declare(strict_types=1);

namespace App\Middleware;

use Core\Config\Config;
use Core\Http\Request;
use Core\Http\Response;
use Core\Middleware\Middleware;
use Core\Support\Env;

/**
 * Puts the site into maintenance mode when APP_MAINTENANCE=true.
 * Super admins (by session) may still access the app to perform maintenance.
 */
final class MaintenanceMode implements Middleware
{
    public function handle(Request $request, callable $next, ?string $argument = null): Response
    {
        $enabled = filter_var(Env::get('APP_MAINTENANCE', false), FILTER_VALIDATE_BOOLEAN);

        if (!$enabled) {
            return $next($request);
        }

        // Allow authenticated super admins through so they can manage the outage.
        if (auth()->check() && auth()->hasRole('super_admin')) {
            return $next($request);
        }

        // Allow the login route so an admin can sign in.
        if (in_array($request->uri(), ['/login'], true)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return Response::json(['success' => false, 'message' => 'Service temporarily unavailable for maintenance.'], 503);
        }

        $html = view('errors.generic', [
            'code'    => 503,
            'title'   => 'Under Maintenance',
            'message' => 'We are performing scheduled maintenance and will be back shortly. Thank you for your patience.',
        ]);
        return (new Response($html, 503))->header('Retry-After', '3600');
    }
}
