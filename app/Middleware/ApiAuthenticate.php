<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\ApiTokenService;
use Core\Http\Request;
use Core\Http\Response;
use Core\Middleware\Middleware;

/**
 * Authenticates stateless REST API requests via Bearer tokens.
 * Resolves the token to a user and logs them in for the request lifetime.
 */
final class ApiAuthenticate implements Middleware
{
    public function handle(Request $request, callable $next, ?string $argument = null): Response
    {
        $token = $request->bearerToken();

        if ($token === null) {
            return Response::json(['success' => false, 'message' => 'Access token required.'], 401);
        }

        $userId = (new ApiTokenService())->resolve($token);

        if ($userId === null) {
            return Response::json(['success' => false, 'message' => 'Invalid or expired token.'], 401);
        }

        // Establish the authenticated identity for downstream authorization.
        auth()->onceUsingId($userId);

        return $next($request);
    }
}
