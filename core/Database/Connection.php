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

    private static ?string $capture = null;

    private ?PDO $pdo = null;

    /** @var array<string, mixed> */
    private array $config;

    private ?DatabaseConfig $dbConfig = null;

    private Dialect $dialect;

    private function __construct(?array $config = null)
    {
        $default = (string) Config::get('database.default', 'mysql');
        $this->config = $config ?? (array) Config::get("database.connections.{$default}", []);
        try {
            $this->dbConfig = DatabaseConfig::fromArray($this->config);
            $this->dialect = new Dialect($this->dbConfig->engine);
        } catch (\Throwable) {
            // sqlite/sqlsrv or an invalid config: keep the legacy path; connect() reports the problem.
            $this->dbConfig = null;
            $this->dialect = new Dialect('mysql');
        }
    }

    public function dialect(): Dialect
    {
        return $this->dialect;
    }

    public function engine(): string
    {
        return $this->dbConfig?->engine ?? (string) ($this->config['driver'] ?? 'mysql');
    }

    public function isPostgres(): bool
    {
        return $this->dialect->isPostgres();
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

    /**
     * A separate, non-singleton connection to another database (a restore-test scratch database, a staging
     * copy). The application keeps using Connection::instance().
     */
    public static function forConfig(DatabaseConfig $cfg): self
    {
        return new self($cfg->toArray());
    }

    /** Drop the singleton (used after the configuration changed, e.g. by the installer). */
    public static function reset(): void
    {
        self::$instance = null;
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
        $cfg = $this->dbConfig;
        $retries = max(1, (int) ($c['connect_retries'] ?? (int) (getenv('DB_CONNECT_RETRIES') ?: 1)));
        $delayMs = max(0, (int) ($c['connect_retry_delay_ms'] ?? 500));

        for ($attempt = 1; ; $attempt++) {
            try {
                if ($cfg !== null) {
                    $this->pdo = $cfg->connect();
                } else {
                    $this->pdo = new PDO(
                        sprintf('%s:host=%s;port=%d;dbname=%s', $c['driver'] ?? 'mysql', $c['host'] ?? '127.0.0.1', (int) ($c['port'] ?? 3306), $c['database'] ?? ''),
                        $c['username'] ?? 'root',
                        $c['password'] ?? '',
                        $c['options'] ?? []
                    );
                }
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
            return ['ok' => true, 'error' => null, 'state' => DatabasePreflight::OK, 'message' => 'Database connection successful.'];
        } catch (\Throwable $e) {
            $prev = $e->getPrevious();
            [$state, $message] = DatabasePreflight::classify($e);
            return ['ok' => false, 'error' => ($prev ?? $e)->getMessage(), 'state' => $state, 'message' => $message];
        }
    }

    /**
     * Run a prepared statement and return the PDOStatement.
     *
     * @param array<int|string, mixed> $bindings
     */
    public function statement(string $sql, array $bindings = []): PDOStatement
    {
        if (self::$capture === null) {
            // Test tooling only: record every statement so the PostgreSQL translation can be validated offline.
            $f = getenv('APP_TESTING') === '1' ? getenv('ERP_SQL_CAPTURE') : false;
            self::$capture = is_string($f) && $f !== '' ? $f : '';
        }
        if (self::$capture !== '') {
            @file_put_contents(self::$capture, json_encode($sql) . "\n", FILE_APPEND | LOCK_EX);
        }
        $stmt = $this->pdo()->prepare($this->dialect->translate($sql));
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

    /**
     * Id generated by the last INSERT. PostgreSQL has no "last insert id", so
     * the table's identity sequence is asked (pass the table name). Tables
     * without a generated id (pivot tables) return "0" on both engines.
     */
    public function lastInsertId(?string $table = null): string
    {
        if ($this->dialect->isPostgres()) {
            if ($table === null) {
                return '0';
            }
            $seq = $this->pdo()->query("SELECT pg_get_serial_sequence('\"" . str_replace('"', '', $table) . "\"', 'id')")->fetchColumn();
            return $seq ? (string) $this->pdo()->query('SELECT currval(' . $this->pdo()->quote((string) $seq) . ')')->fetchColumn() : '0';
        }
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
        // Join an already-open transaction instead of failing on PDO's lack of
        // nesting. Commit/rollback stays with the outermost caller, so a
        // multi-step operation (e.g. admission = student + PRN + roll number)
        // is atomic even when its steps use transaction() themselves.
        if ($this->pdo()->inTransaction()) {
            return $callback();
        }
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
