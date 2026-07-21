<?php

declare(strict_types=1);

namespace App\Middleware;

use Core\Http\Request;
use Core\Http\Response;
use Core\Middleware\Middleware;

/**
 * Enforces that the authenticated user holds a given permission.
 * Usage in routes: 'can:students.view'
 */
final class Authorize implements Middleware
{
    public function handle(Request $request, callable $next, ?string $argument = null): Response
    {
        if ($argument === null || $argument === '') {
            return $next($request);
        }

        if (!auth()->can($argument)) {
            if ($request->expectsJson()) {
                return Response::json(['success' => false, 'message' => 'This action is unauthorized.'], 403);
            }
            abort(403, 'You do not have permission to access this resource.');
        }

        return $next($request);
    }
}
