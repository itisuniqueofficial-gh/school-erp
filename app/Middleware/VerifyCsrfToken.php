<?php

declare(strict_types=1);

namespace App\Middleware;

use Core\Config\Config;
use Core\Http\Request;
use Core\Http\Response;
use Core\Middleware\Middleware;
use Core\Security\Csrf;

/**
 * Verifies CSRF tokens on state-changing requests. Read methods are exempt.
 * API routes (stateless, token-authenticated) are excluded by prefix.
 */
final class VerifyCsrfToken implements Middleware
{
    private const READ_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request, callable $next, ?string $argument = null): Response
    {
        if (in_array($request->method(), self::READ_METHODS, true)) {
            return $next($request);
        }

        // Stateless API uses bearer tokens, not CSRF.
        if (str_starts_with($request->uri(), '/api/')) {
            return $next($request);
        }

        $fieldName = (string) Config::get('security.csrf_token_name', '_token');
        $token = $request->input($fieldName) ?? $request->header('X-CSRF-TOKEN');

        if (!Csrf::verify(is_string($token) ? $token : null)) {
            if ($request->expectsJson()) {
                return Response::json(['success' => false, 'message' => 'CSRF token mismatch.'], 419);
            }
            abort(419, 'Your session has expired. Please refresh and try again.');
        }

        return $next($request);
    }
}
