<?php

declare(strict_types=1);

namespace App\Middleware;

use Core\Config\Config;
use Core\Http\Request;
use Core\Http\Response;
use Core\Middleware\Middleware;

/**
 * Simple file-based fixed-window rate limiter keyed by client IP + route.
 * Usage in routes: 'throttle:60,60' (max requests, window seconds).
 */
final class RateLimit implements Middleware
{
    public function handle(Request $request, callable $next, ?string $argument = null): Response
    {
        [$max, $window] = array_pad(
            $argument !== null ? array_map('intval', explode(',', $argument)) : [],
            2,
            null
        );
        $max ??= (int) Config::get('security.rate_limit.max', 60);
        $window ??= (int) Config::get('security.rate_limit.window', 60);

        $dir = (string) Config::get('paths.cache') . '/ratelimit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $key = hash('sha256', $request->ip() . '|' . $request->uri());
        $file = $dir . '/' . $key . '.json';

        $now = time();
        $data = ['count' => 0, 'reset' => $now + $window];
        if (is_file($file)) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (is_array($decoded) && ($decoded['reset'] ?? 0) > $now) {
                $data = $decoded;
            }
        }

        $data['count']++;
        @file_put_contents($file, json_encode($data), LOCK_EX);

        $remaining = max(0, $max - $data['count']);
        $retryAfter = max(0, $data['reset'] - $now);

        if ($data['count'] > $max) {
            $response = $request->expectsJson()
                ? Response::json(['success' => false, 'message' => 'Too many requests.'], 429)
                : Response::html('<h1>429 Too Many Requests</h1><p>Please slow down and try again shortly.</p>', 429);
            return $response
                ->header('Retry-After', (string) $retryAfter)
                ->header('X-RateLimit-Limit', (string) $max)
                ->header('X-RateLimit-Remaining', '0');
        }

        $response = $next($request);
        return $response
            ->header('X-RateLimit-Limit', (string) $max)
            ->header('X-RateLimit-Remaining', (string) $remaining);
    }
}
