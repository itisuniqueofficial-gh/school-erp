<?php

declare(strict_types=1);

namespace Core\Routing;

use Core\Http\Request;
use Core\Http\Response;

/**
 * HTTP router with route groups, middleware, named routes and parameter binding.
 */
final class Router
{
    /** @var array<int, Route> */
    private array $routes = [];

    /** @var array<string, Route> */
    private array $named = [];

    /** @var array<string, class-string> Middleware alias => class */
    private array $middlewareAliases = [];

    /** Current group attributes stack. @var array<int, array<string, mixed>> */
    private array $groupStack = [];

    public function get(string $uri, mixed $action): Route
    {
        return $this->addRoute('GET', $uri, $action);
    }

    public function post(string $uri, mixed $action): Route
    {
        return $this->addRoute('POST', $uri, $action);
    }

    public function put(string $uri, mixed $action): Route
    {
        return $this->addRoute('PUT', $uri, $action);
    }

    public function patch(string $uri, mixed $action): Route
    {
        return $this->addRoute('PATCH', $uri, $action);
    }

    public function delete(string $uri, mixed $action): Route
    {
        return $this->addRoute('DELETE', $uri, $action);
    }

    public function any(string $uri, mixed $action): Route
    {
        return $this->addRoute('GET|POST|PUT|PATCH|DELETE', $uri, $action);
    }

    public function match(array $methods, string $uri, mixed $action): Route
    {
        return $this->addRoute(implode('|', array_map('strtoupper', $methods)), $uri, $action);
    }

    /**
     * Register middleware aliases (name => class).
     *
     * @param array<string, class-string> $aliases
     */
    public function aliasMiddleware(array $aliases): void
    {
        $this->middlewareAliases = array_merge($this->middlewareAliases, $aliases);
    }

    /**
     * Define a route group with shared attributes (prefix, middleware, namespace).
     *
     * @param array<string, mixed> $attributes
     */
    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    private function addRoute(string $method, string $uri, mixed $action): Route
    {
        $prefix = '';
        $middleware = [];
        $namespace = '';

        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= '/' . trim((string) $group['prefix'], '/');
            }
            if (isset($group['middleware'])) {
                $mw = (array) $group['middleware'];
                $middleware = array_merge($middleware, $mw);
            }
            if (isset($group['namespace'])) {
                $namespace = trim((string) $group['namespace'], '\\');
            }
        }

        $uri = '/' . trim($prefix . '/' . trim($uri, '/'), '/');
        $uri = $uri === '/' ? '/' : rtrim($uri, '/');

        if (is_string($action) && $namespace !== '' && str_contains($action, '@')) {
            $action = $namespace . '\\' . $action;
        }

        $route = new Route($method, $uri, $action, $middleware);
        $this->routes[] = $route;

        return $route;
    }

    public function name(string $name, Route $route): void
    {
        $this->named[$name] = $route;
    }

    /**
     * Dispatch the request through the matched route and its middleware pipeline.
     */
    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $uri = $request->uri();

        foreach ($this->routes as $route) {
            if ($route->matches($method, $uri)) {
                $params = $route->extractParams($uri);
                return $this->runThroughMiddleware($route, $request, $params);
            }
        }

        // No route matched.
        if ($request->expectsJson()) {
            return Response::json(['message' => 'Not Found'], 404);
        }
        abort(404, 'The page you requested could not be found.');
    }

    /**
     * @param array<string, string> $params
     */
    private function runThroughMiddleware(Route $route, Request $request, array $params): Response
    {
        $middlewareList = $route->getMiddleware();

        // Build the core handler that invokes the controller/closure.
        $core = fn (Request $req): Response => $this->runAction($route, $req, $params);

        // Compose middleware pipeline (onion model).
        $pipeline = array_reduce(
            array_reverse($middlewareList),
            function (callable $next, string $mw): callable {
                return function (Request $req) use ($mw, $next): Response {
                    [$name, $arg] = array_pad(explode(':', $mw, 2), 2, null);
                    $class = $this->middlewareAliases[$name] ?? $name;
                    if (!class_exists($class)) {
                        return $next($req);
                    }
                    /** @var object $instance */
                    $instance = new $class();
                    return $instance->handle($req, $next, $arg);
                };
            },
            $core
        );

        return $pipeline($request);
    }

    /**
     * @param array<string, string> $params
     */
    private function runAction(Route $route, Request $request, array $params): Response
    {
        $action = $route->getAction();

        if ($action instanceof \Closure) {
            $result = $action($request, ...array_values($params));
            return $this->toResponse($result);
        }

        if (is_string($action) && str_contains($action, '@')) {
            [$controller, $method] = explode('@', $action, 2);
            if (!class_exists($controller)) {
                abort(500, "Controller {$controller} not found.");
            }
            $instance = new $controller();
            if (!method_exists($instance, $method)) {
                abort(500, "Method {$method} not found on {$controller}.");
            }
            $result = $instance->{$method}($request, ...array_values($params));
            return $this->toResponse($result);
        }

        abort(500, 'Invalid route action.');
    }

    private function toResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }
        if (is_array($result) || is_object($result)) {
            return Response::json($result);
        }
        return Response::html((string) $result);
    }

    /** @return array<int, Route> */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
