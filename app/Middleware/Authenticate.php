<?php

declare(strict_types=1);

namespace App\Middleware;

use Core\Http\Request;
use Core\Http\Response;
use Core\Middleware\Middleware;

/**
 * Ensures the request is authenticated. Attempts remember-me before rejecting.
 */
final class Authenticate implements Middleware
{
    public function handle(Request $request, callable $next, ?string $argument = null): Response
    {
        if (!auth()->check()) {
            auth()->attemptRemember();
        }

        if (!auth()->check()) {
            if ($request->expectsJson()) {
                return Response::json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }
            session()->flash('error', 'Please sign in to continue.');
            return (new Response('', 302))->header('Location', url('/login'));
        }

        return $next($request);
    }
}
