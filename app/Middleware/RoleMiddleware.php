<?php

declare(strict_types=1);

namespace App\Middleware;

use Core\Http\Request;
use Core\Http\Response;
use Core\Middleware\Middleware;

/**
 * Enforces that the authenticated user holds at least one of the given roles.
 * Usage in routes: 'role:super_admin,school_admin'
 */
final class RoleMiddleware implements Middleware
{
    public function handle(Request $request, callable $next, ?string $argument = null): Response
    {
        if ($argument === null || $argument === '') {
            return $next($request);
        }

        $roles = array_map('trim', explode(',', $argument));

        if (!auth()->hasAnyRole($roles)) {
            if ($request->expectsJson()) {
                return Response::json(['success' => false, 'message' => 'This action is unauthorized.'], 403);
            }
            abort(403, 'Your role does not permit access to this resource.');
        }

        return $next($request);
    }
}
