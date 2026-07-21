<?php

declare(strict_types=1);

namespace Core;

use Closure;
use RuntimeException;

/**
 * Minimal service container and application kernel.
 * Provides singleton/binding resolution and holds the app-wide state.
 */
final class Application
{
    private static ?Application $instance = null;

    /** @var array<string, Closure> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public readonly string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
        self::$instance = $this;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new RuntimeException('Application has not been bootstrapped.');
        }
        return self::$instance;
    }

    /**
     * Register a shared (singleton) binding.
     */
    public function singleton(string $abstract, Closure $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }

    /**
     * Register an existing instance as a singleton.
     */
    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Register a transient binding (new instance each resolution).
     */
    public function bind(string $abstract, Closure $factory): void
    {
        $this->bindings[$abstract] = $factory;
        unset($this->instances[$abstract]);
    }

    /**
     * Resolve a binding from the container.
     */
    public function make(string $abstract): mixed
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            $object = ($this->bindings[$abstract])($this);
            $this->instances[$abstract] = $object;
            return $object;
        }

        // Attempt zero-arg autowiring for concrete classes.
        if (class_exists($abstract)) {
            $object = new $abstract();
            $this->instances[$abstract] = $object;
            return $object;
        }

        throw new RuntimeException("Unable to resolve [{$abstract}] from container.");
    }

    public function has(string $abstract): bool
    {
        return isset($this->instances[$abstract]) || isset($this->bindings[$abstract]);
    }
}
