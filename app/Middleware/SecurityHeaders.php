<?php

declare(strict_types=1);

namespace App\Middleware;

use Core\Config\Config;
use Core\Http\Request;
use Core\Http\Response;
use Core\Middleware\Middleware;

/**
 * Applies security-related HTTP response headers including CSP and HSTS.
 */
final class SecurityHeaders implements Middleware
{
    public function handle(Request $request, callable $next, ?string $argument = null): Response
    {
        $response = $next($request);

        foreach ((array) Config::get('security.headers', []) as $key => $value) {
            $response->header($key, (string) $value);
        }

        $csp = (array) Config::get('security.csp', []);
        if ($csp !== []) {
            $response->header('Content-Security-Policy', implode('; ', $csp));
        }

        if ($request->isSecure()) {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
