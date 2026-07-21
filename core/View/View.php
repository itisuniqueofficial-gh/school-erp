<?php

declare(strict_types=1);

namespace Core\View;

use Core\Config\Config;
use RuntimeException;

/**
 * Lightweight native-PHP template engine with layout inheritance and sections.
 *
 * Views are plain PHP files under resources/views. Dot-notation maps to path:
 *   'admin.students.index' => resources/views/admin/students/index.php
 *
 * Templates may call $this->extend('layouts.app'), $this->section('content'),
 * $this->endSection(), and $this->yield('content').
 */
final class View
{
    private string $viewPath;

    /** @var array<string, mixed> Shared data available to all views. */
    private array $shared = [];

    /** @var array<string, string> Rendered named sections. */
    private array $sections = [];

    /** @var string[] Stack of section names currently being captured. */
    private array $sectionStack = [];

    private ?string $layout = null;

    public function __construct(?string $viewPath = null)
    {
        $this->viewPath = $viewPath ?? (string) Config::get('paths.views');
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /**
     * Render a view template to a string.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        $file = $this->resolve($template);
        if (!is_file($file)) {
            throw new RuntimeException("View [{$template}] not found at {$file}");
        }

        // Reset per-render layout state (sections persist through the layout
        // chain of this render, but must not leak between top-level renders).
        $this->layout = null;
        $this->sections = [];
        $this->sectionStack = [];

        $content = $this->renderFile($file, array_merge($this->shared, $data));

        // Resolve layout inheritance, supporting multiple nested levels
        // (e.g. a page extends 'guest' which itself extends 'base').
        while ($this->layout !== null) {
            $layout = $this->layout;
            $this->layout = null;

            // The current content becomes the 'content' section if not already set.
            if (!isset($this->sections['content'])) {
                $this->sections['content'] = $content;
            }

            $layoutFile = $this->resolve($layout);
            if (!is_file($layoutFile)) {
                throw new RuntimeException("Layout [{$layoutFile}] not found.");
            }
            $content = $this->renderFile($layoutFile, array_merge($this->shared, $data));
        }

        return $content;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderFile(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    private function resolve(string $template): string
    {
        $relative = str_replace('.', '/', $template);
        return rtrim($this->viewPath, '/') . '/' . $relative . '.php';
    }

    // --- Template API (called from within views via $this) ----------------

    public function extend(string $layout): void
    {
        $this->layout = $layout;
    }

    public function section(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    public function endSection(): void
    {
        $name = array_pop($this->sectionStack);
        if ($name === null) {
            throw new RuntimeException('endSection() called without a matching section().');
        }
        $this->sections[$name] = (string) ob_get_clean();
    }

    /**
     * Output a section's content, or a default if not defined.
     */
    public function yield(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]);
    }

    /**
     * Include a partial view inline.
     *
     * @param array<string, mixed> $data
     */
    public function include(string $template, array $data = []): void
    {
        $file = $this->resolve($template);
        if (is_file($file)) {
            echo $this->renderFile($file, array_merge($this->shared, $data));
        }
    }
}
