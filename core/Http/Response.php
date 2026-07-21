<?php

declare(strict_types=1);

namespace Core\Http;

/**
 * HTTP response abstraction supporting HTML, JSON, redirects, and downloads.
 */
final class Response
{
    private string $content = '';
    private int $status = 200;

    /** @var array<string, string> */
    private array $headers = [];

    public function __construct(string $content = '', int $status = 200, array $headers = [])
    {
        $this->content = $content;
        $this->status = $status;
        $this->headers = $headers;
    }

    public static function make(string $content = '', int $status = 200): self
    {
        return new self($content, $status);
    }

    public static function html(string $html, int $status = 200): self
    {
        return (new self($html, $status))->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * @param mixed $data
     */
    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        $response = new self(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            $status,
            $headers
        );
        return $response->header('Content-Type', 'application/json; charset=UTF-8');
    }

    public static function noContent(int $status = 204): self
    {
        return new self('', $status);
    }

    public function header(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function setStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Send the response to the client.
     */
    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $key => $value) {
                header("{$key}: {$value}", true);
            }
        }
        echo $this->content;
    }
}
