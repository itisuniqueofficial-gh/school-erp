<?php

declare(strict_types=1);

namespace Core\Middleware;

use Core\Http\Request;
use Core\Http\Response;

/**
 * Contract for HTTP middleware. Each middleware receives the request and a
 * $next callable to continue the pipeline, optionally with a route argument.
 */
interface Middleware
{
    /**
     * @param callable(Request):Response $next
     */
    public function handle(Request $request, callable $next, ?string $argument = null): Response;
}
