<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\InstitutionService;
use Core\Http\Request;
use Core\Http\Response;
use Core\Middleware\Middleware;

/**
 * Blocks access to a route when its module is disabled for this institution.
 * Enforced server-side so a disabled module cannot be reached by typing the URL.
 *
 * Usage in routes: ->middleware('module:fees')
 */
final class EnsureModuleEnabled implements Middleware
{
    public function handle(Request $request, callable $next, ?string $argument = null): Response
    {
        if ($argument === null || $argument === '' || (new InstitutionService())->moduleEnabled($argument)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return Response::json(['success' => false, 'message' => 'This module is not enabled for your institution.'], 403);
        }
        abort(403, 'This module is not enabled for your institution.');
    }
}
