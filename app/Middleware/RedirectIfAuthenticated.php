<?php

declare(strict_types=1);

namespace App\Middleware;

use Core\Config\Config;
use Core\Http\Request;
use Core\Http\Response;
use Core\Middleware\Middleware;

/**
 * Redirects already-authenticated users away from guest-only pages (e.g. login).
 */
final class RedirectIfAuthenticated implements Middleware
{
    public function handle(Request $request, callable $next, ?string $argument = null): Response
    {
        if (auth()->check()) {
            $user = auth()->user();
            $role = $user['roles'][0] ?? 'student';
            $home = Config::get("auth.home_routes.{$role}", '/admin/dashboard');
            return (new Response('', 302))->header('Location', url($home));
        }

        return $next($request);
    }
}
