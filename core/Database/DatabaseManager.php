<?php

declare(strict_types=1);

namespace Core\Database;

use Core\Config\Config;
use PDO;

final class DatabaseManager
{
    private static ?self $instance = null;
    /** @var array<string, PDO> */
    private array $connections = [];

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function connection(?string $name = null): PDO
    {
        $name = $name ?? (string) Config::get('database.default', 'mysql');
        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }
        $this->connections[$name] = $this->make($name);
        return $this->connections[$name];
    }

    private function make(string $name): PDO
    {
        $config = (array) Config::get("database.connections.{$name}", []);
        $driver = $config['driver'] ?? $name;

        if ($driver === 'sqlite') {
            $db = $config['database'] ?? ':memory:';
            $dsn = $db === ':memory:' ? 'sqlite::memory:' : 'sqlite:' . $db;
            $options = $config['options'] ?? [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, null, null, $options);
            // Enable foreign keys for SQLite
            $pdo->exec('PRAGMA foreign_keys = ON');
            return $pdo;
        }

        if ($driver === 'pgsql') {
            $dsn = sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                $config['host'] ?? '127.0.0.1',
                (int) ($config['port'] ?? 5432),
                $config['database'] ?? 'school_erp'
            );
            $options = $config['options'] ?? [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            return new PDO($dsn, $config['username'] ?? 'postgres', $config['password'] ?? '', $options);
        }

        if ($driver === 'sqlsrv') {
            $dsn = sprintf(
                'sqlsrv:Server=%s,%d;Database=%s',
                $config['host'] ?? '127.0.0.1',
                (int) ($config['port'] ?? 1433),
                $config['database'] ?? 'school_erp'
            );
            $options = $config['options'] ?? [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ];
            return new PDO($dsn, $config['username'] ?? '', $config['password'] ?? '', $options);
        }

        // MySQL/MariaDB
        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $driver === 'mariadb' ? 'mysql' : $driver,
            $config['host'] ?? '127.0.0.1',
            (int) ($config['port'] ?? 3306),
            $config['database'] ?? '',
            $config['charset'] ?? 'utf8mb4'
        );
        $options = $config['options'] ?? [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_PERSISTENT => false,
        ];
        return new PDO($dsn, $config['username'] ?? 'root', $config['password'] ?? '', $options);
    }

    public function getDriver(?string $name = null): string
    {
        $name = $name ?? (string) Config::get('database.default', 'mysql');
        $config = (array) Config::get("database.connections.{$name}", []);
        return $config['driver'] ?? $name;
    }

    public function isMySql(): bool
    {
        $d = $this->getDriver();
        return in_array($d, ['mysql', 'mariadb'], true);
    }

    public function isPgsql(): bool
    {
        return $this->getDriver() === 'pgsql';
    }

    public function isSqlite(): bool
    {
        return $this->getDriver() === 'sqlite';
    }

    public function isSqlsrv(): bool
    {
        return $this->getDriver() === 'sqlsrv';
    }

    public function quoteIdentifier(string $identifier): string
    {
        if ($this->isPgsql()) {
            return '"' . str_replace('"', '""', $identifier) . '"';
        }
        if ($this->isSqlsrv()) {
            return '[' . str_replace(']', ']]', $identifier) . ']';
        }
        // MySQL/MariaDB/SQLite use backticks or just return as-is
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
