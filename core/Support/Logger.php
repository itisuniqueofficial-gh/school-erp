<?php

declare(strict_types=1);

namespace Core\Support;

use Core\Config\Config;

/**
 * Simple PSR-3-style file logger writing to daily log files.
 */
final class Logger
{
    private string $directory;

    public function __construct()
    {
        $this->directory = (string) Config::get('paths.logs');
        if ($this->directory && !is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }
    }

    public function emergency(string $message, array $context = []): void { $this->log('EMERGENCY', $message, $context); }
    public function alert(string $message, array $context = []): void     { $this->log('ALERT', $message, $context); }
    public function critical(string $message, array $context = []): void  { $this->log('CRITICAL', $message, $context); }
    public function error(string $message, array $context = []): void     { $this->log('ERROR', $message, $context); }
    public function warning(string $message, array $context = []): void   { $this->log('WARNING', $message, $context); }
    public function notice(string $message, array $context = []): void    { $this->log('NOTICE', $message, $context); }
    public function info(string $message, array $context = []): void      { $this->log('INFO', $message, $context); }
    public function debug(string $message, array $context = []): void     { $this->log('DEBUG', $message, $context); }

    /**
     * @param array<string, mixed> $context
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if (!is_dir($this->directory)) {
            return;
        }
        $file = $this->directory . '/erp-' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = $context ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        $line = "[{$timestamp}] {$level}: {$message}{$contextStr}" . PHP_EOL;
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}
