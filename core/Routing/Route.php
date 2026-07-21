<?php

declare(strict_types=1);

namespace Core\Routing;

/**
 * Represents a single registered route with method(s), URI pattern, action,
 * and middleware. Supports {param} and {param?} placeholders.
 */
final class Route
{
    /** @var string[] */
    private array $methods;

    private string $uri;
    private mixed $action;

    /** @var string[] */
    private array $middleware;

    private ?string $name = null;
    private string $regex;

    /** @var string[] */
    private array $paramNames = [];

    public function __construct(string $method, string $uri, mixed $action, array $middleware = [])
    {
        $this->methods = explode('|', strtoupper($method));
        $this->uri = $uri;
        $this->action = $action;
        $this->middleware = $middleware;
        $this->compile();
    }

    /**
     * Convert the URI pattern into a regular expression.
     */
    private function compile(): void
    {
        $this->regex = $this->buildRegex();
    }

    private function buildRegex(): string
    {
        $this->paramNames = [];
        $segments = explode('/', trim($this->uri, '/'));
        $parts = [];
        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)(\?)?\}$/', $segment, $m)) {
                $this->paramNames[] = $m[1];
                $optional = ($m[2] ?? '') === '?';
                $parts[] = $optional ? '(?:/([^/]+))?' : '/([^/]+)';
            } else {
                $parts[] = '/' . preg_quote($segment, '/');
            }
        }
        $regex = implode('', $parts);
        if ($regex === '') {
            $regex = '/';
        }
        return '#^' . $regex . '$#';
    }

    public function matches(string $method, string $uri): bool
    {
        if (!in_array($method, $this->methods, true)) {
            return false;
        }
        $uri = $uri === '/' ? '/' : rtrim($uri, '/');
        return (bool) preg_match($this->regex, $uri);
    }

    /**
     * @return array<string, string>
     */
    public function extractParams(string $uri): array
    {
        $uri = $uri === '/' ? '/' : rtrim($uri, '/');
        $params = [];
        if (preg_match($this->regex, $uri, $matches)) {
            array_shift($matches);
            foreach ($this->paramNames as $i => $paramName) {
                $params[$paramName] = $matches[$i] ?? '';
            }
        }
        return $params;
    }

    public function middleware(string|array $middleware): self
    {
        $this->middleware = array_merge($this->middleware, (array) $middleware);
        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /** @return string[] */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function getAction(): mixed
    {
        return $this->action;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /** @return string[] */
    public function getMethods(): array
    {
        return $this->methods;
    }
}
