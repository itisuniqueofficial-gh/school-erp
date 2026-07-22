<?php

declare(strict_types=1);

namespace Core\Database;

use Core\Config\Config;
use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * PDO connection manager providing a singleton connection and query helpers.
 * All queries use prepared statements to prevent SQL injection.
 */
final class Connection
{
    private static ?Connection $instance = null;

    private ?PDO $pdo = null;

    /** @var array<string, mixed> */
    private array $config;

    private function __construct()
    {
        $default = (string) Config::get('database.default', 'mysql');
        $this->config = (array) Config::get("database.connections.{$default}", []);
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Inject a pre-built PDO instance. Intended for testing against an
     * in-memory database. Resets the singleton to use the given connection.
     */
    public static function setPdoForTesting(\PDO $pdo): void
    {
        self::$instance = new self();
        self::$instance->pdo = $pdo;
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->connect();
        }
        /** @var PDO */
        return $this->pdo;
    }

    private function connect(): void
    {
        $c = $this->config;
        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $c['driver'] ?? 'mysql',
            $c['host'] ?? '127.0.0.1',
            (int) ($c['port'] ?? 3306),
            $c['database'] ?? '',
            $c['charset'] ?? 'utf8mb4'
        );

        $retries = max(1, (int) ($c['connect_retries'] ?? (int) (getenv('DB_CONNECT_RETRIES') ?: 1)));
        $delayMs = max(0, (int) ($c['connect_retry_delay_ms'] ?? 500));

        for ($attempt = 1; ; $attempt++) {
            try {
                $this->pdo = new PDO(
                    $dsn,
                    $c['username'] ?? 'root',
                    $c['password'] ?? '',
                    $c['options'] ?? []
                );
                return;
            } catch (PDOException $e) {
                if ($attempt < $retries) {
                    if ($delayMs > 0) {
                        usleep($delayMs * 1000);
                    }
                    continue;
                }
                // Surface a generic, credential-free message; keep the real PDO
                // error as the previous exception for server-side logging only.
                throw new DatabaseConnectionException(
                    'Unable to connect to the database.',
                    (int) $e->getCode(),
                    $e
                );
            }
        }
    }

    /**
     * Test database connectivity without throwing. Returns a result array for
     * health checks: ['ok' => bool, 'error' => ?string].
     *
     * @return array{ok:bool,error:?string}
     */
    public function ping(): array
    {
        try {
            $this->pdo()->query('SELECT 1');
            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            $prev = $e->getPrevious();
            return ['ok' => false, 'error' => ($prev ?? $e)->getMessage()];
        }
    }

    /**
     * Run a prepared statement and return the PDOStatement.
     *
     * @param array<int|string, mixed> $bindings
     */
    public function statement(string $sql, array $bindings = []): PDOStatement
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($bindings);
        return $stmt;
    }

    /**
     * Fetch all rows.
     *
     * @param array<int|string, mixed> $bindings
     * @return array<int, array<string, mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        return $this->statement($sql, $bindings)->fetchAll();
    }

    /**
     * Fetch a single row.
     *
     * @param array<int|string, mixed> $bindings
     * @return array<string, mixed>|null
     */
    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = $this->statement($sql, $bindings)->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Fetch a single scalar value.
     *
     * @param array<int|string, mixed> $bindings
     */
    public function scalar(string $sql, array $bindings = []): mixed
    {
        return $this->statement($sql, $bindings)->fetchColumn();
    }

    /**
     * Run an INSERT/UPDATE/DELETE and return affected row count.
     *
     * @param array<int|string, mixed> $bindings
     */
    public function affectingStatement(string $sql, array $bindings = []): int
    {
        return $this->statement($sql, $bindings)->rowCount();
    }

    public function lastInsertId(): string
    {
        return $this->pdo()->lastInsertId();
    }

    public function beginTransaction(): void
    {
        $this->pdo()->beginTransaction();
    }

    public function commit(): void
    {
        if ($this->pdo()->inTransaction()) {
            $this->pdo()->commit();
        }
    }

    public function rollBack(): void
    {
        if ($this->pdo()->inTransaction()) {
            $this->pdo()->rollBack();
        }
    }

    /**
     * Execute a callback within a database transaction.
     *
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback();
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }
}
