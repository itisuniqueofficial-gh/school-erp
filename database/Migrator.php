<?php

declare(strict_types=1);

namespace Database;

use Core\Config\Config;
use Core\Database\Connection;
use Core\Database\Schema\PostgresTranslator;
use Core\Database\Schema\Schema;
use Core\Database\SchemaInspector;

/**
 * Version-controlled schema migrations for MySQL, MariaDB and PostgreSQL.
 *
 * A migration is a file in database/migrations, applied once in filename order:
 *   NNN_name.sql  MySQL-flavoured SQL (translated automatically for PostgreSQL)
 *   NNN_name.php  returns ['up' => fn (Schema $s), 'down' => fn (Schema $s)] using the portable builder
 * Applied migrations are recorded in `migrations` (name, batch, SHA-256 checksum, duration) and every event
 * (started / applied / failed / rolled_back) is appended to `migration_log` and storage/logs/install.log.
 *
 * Guarantees
 *  - One migrator at a time: a database advisory lock (GET_LOCK / pg_advisory_lock) serialises concurrent
 *    deploys, and the applied list is re-read after the lock is held, so nothing runs twice.
 *  - Nothing is dropped or truncated. fresh() exists for disposable test databases only and the console
 *    refuses it on live data.
 *  - PostgreSQL: each migration (statements + bookkeeping row) is one transaction. MySQL/MariaDB cannot roll
 *    back DDL; statements are idempotent (already-existing objects are skipped) so a failed migration is
 *    fixed by correcting the cause and running migrate again.
 *  - Rollback is opt-in per migration (database/rollbacks/NNN_name.sql or a PHP 'down'), guarded by queries
 *    that must return 0, so it can never discard data silently.
 *  - A recorded migration whose file was edited afterwards is reported (checksum), never re-run.
 */
final class Migrator
{
    private const LOCK_NAME = 'erp_schema_migrations';
    private const LOCK_WAIT_SECONDS = 60;

    private string $path;
    private Connection $conn;
    private bool $locked = false;

    public function __construct(?string $path = null, ?Connection $conn = null)
    {
        $this->path = $path ?? (string) Config::get('database.migrations_path');
        $this->conn = $conn ?? Connection::instance();
    }

    // ------------------------------------------------------------------ bookkeeping tables

    /** Create / upgrade the tracking tables. Safe to call repeatedly. */
    public function ensureTable(): void
    {
        $pdo = $this->conn->pdo();
        $ddl = [
            'CREATE TABLE IF NOT EXISTS `migrations` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `migration` VARCHAR(191) NOT NULL,
                `batch` INT NOT NULL,
                `executed_at` DATETIME NOT NULL,
                `checksum` CHAR(64) DEFAULT NULL,
                `execution_ms` INT DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_migration` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC',
            'CREATE TABLE IF NOT EXISTS `migration_log` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `migration` VARCHAR(191) NOT NULL,
                `event` VARCHAR(20) NOT NULL,
                `message` VARCHAR(500) DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_migration_log_name` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC',
        ];
        $t = new PostgresTranslator();
        foreach ($ddl as $sql) {
            foreach ($this->conn->isPostgres() ? $t->translate($sql) : [$sql] as $stmt) {
                $pdo->exec($stmt);
            }
        }
        // Installations created before checksums existed.
        $have = (new SchemaInspector($this->conn))->columns('migrations');
        foreach (['checksum' => 'CHAR(64) DEFAULT NULL', 'execution_ms' => 'INT DEFAULT NULL'] as $col => $def) {
            if (!in_array($col, $have, true)) {
                $pdo->exec($this->conn->isPostgres() ? "ALTER TABLE \"migrations\" ADD COLUMN \"{$col}\" " . ($col === 'checksum' ? 'CHAR(64)' : 'INTEGER') : "ALTER TABLE `migrations` ADD COLUMN `{$col}` {$def}");
            }
        }
    }

    /** @return string[] absolute paths, sorted by file name */
    private function files(): array
    {
        $files = array_merge(glob(rtrim($this->path, '/') . '/*.sql') ?: [], glob(rtrim($this->path, '/') . '/*.php') ?: []);
        usort($files, static fn ($a, $b) => strcmp(basename($a), basename($b)));
        return $files;
    }

    /** @return array<string,array{id:int,batch:int,checksum:?string,executed_at:string,ms:?int}> by name */
    private function appliedRows(): array
    {
        $out = [];
        foreach ($this->conn->select('SELECT `id`, `migration`, `batch`, `checksum`, `executed_at`, `execution_ms` FROM `migrations` ORDER BY `id`') as $r) {
            $out[(string) $r['migration']] = ['id' => (int) $r['id'], 'batch' => (int) $r['batch'], 'checksum' => $r['checksum'] !== null ? (string) $r['checksum'] : null,
                'executed_at' => (string) $r['executed_at'], 'ms' => $r['execution_ms'] !== null ? (int) $r['execution_ms'] : null];
        }
        return $out;
    }

    public static function checksum(string $file): string
    {
        return hash('sha256', str_replace("\r\n", "\n", (string) file_get_contents($file)));
    }

    // ------------------------------------------------------------------ status & preflight

    /**
     * @return array{applied:string[],pending:string[],modified:string[],missing_files:string[],batch:int}
     */
    public function status(): array
    {
        $this->ensureTable();
        $rows = $this->appliedRows();
        $pending = [];
        $modified = [];
        $known = [];
        foreach ($this->files() as $f) {
            $n = basename($f);
            $known[$n] = true;
            if (!isset($rows[$n])) {
                $pending[] = $n;
            } elseif ($rows[$n]['checksum'] !== null && $rows[$n]['checksum'] !== self::checksum($f)) {
                $modified[] = $n;
            }
        }
        return ['applied' => array_keys($rows), 'pending' => $pending, 'modified' => $modified,
            'missing_files' => array_values(array_diff(array_keys($rows), array_keys($known))),
            'batch' => $rows === [] ? 0 : max(array_column($rows, 'batch'))];
    }

    /** @return array<int,array<string,mixed>> newest first */
    public function history(int $limit = 100): array
    {
        $this->ensureTable();
        return $this->conn->select('SELECT `migration`, `batch`, `executed_at`, `checksum`, `execution_ms` FROM `migrations` ORDER BY `id` DESC LIMIT ' . max(1, $limit));
    }

    /** @return array<int,array<string,mixed>> */
    public function events(int $limit = 50): array
    {
        $this->ensureTable();
        return $this->conn->select('SELECT `migration`, `event`, `message`, `created_at` FROM `migration_log` ORDER BY `id` DESC LIMIT ' . max(1, $limit));
    }

    /**
     * Checks that must hold before any schema change.
     *
     * @return array{errors:string[],warnings:string[]}
     */
    public function preflight(): array
    {
        $errors = [];
        $warnings = [];
        try {
            $version = (string) $this->conn->scalar('SELECT VERSION()');
        } catch (\Throwable) {
            return ['errors' => ['The database is not reachable.'], 'warnings' => []];
        }
        if ($this->conn->isPostgres()) {
            if ((int) $this->conn->scalar('SHOW server_version_num') < 120000) {
                $errors[] = 'PostgreSQL 12 or newer is required (generated columns).';
            }
        } elseif (stripos($version, 'mariadb') !== false) {
            if (version_compare((string) preg_replace('/^(\d+\.\d+).*/', '$1', (string) preg_replace('/^5\.5\.5-/', '', $version)), '10.2', '<')) {
                $errors[] = 'MariaDB 10.2 or newer is required.';
            }
        } elseif (version_compare((string) preg_replace('/^(\d+\.\d+).*/', '$1', $version), '5.7', '<')) {
            $errors[] = 'MySQL 5.7 or newer is required.';
        }

        // Numbering: two files with the same NNN prefix would make the order ambiguous.
        $seen = [];
        foreach ($this->files() as $f) {
            if (preg_match('/^(\d+)_/', basename($f), $m) !== 1) {
                $errors[] = 'Migration file name must start with a number: ' . basename($f);
                continue;
            }
            if (isset($seen[$m[1]])) {
                $errors[] = "Migrations {$seen[$m[1]]} and " . basename($f) . " share the number {$m[1]}.";
            }
            $seen[$m[1]] = basename($f);
        }

        // Can this database user create (and remove) its own tables? Uses a throw-away probe table only.
        $probe = 'erp_probe_' . bin2hex(random_bytes(4));
        try {
            $this->conn->pdo()->exec("CREATE TABLE {$this->q($probe)} (id INT)");
            $this->conn->pdo()->exec("DROP TABLE {$this->q($probe)}");
        } catch (\Throwable) {
            $errors[] = 'The database user cannot create tables (needs CREATE, ALTER, DROP on its own database).';
        }

        $s = $this->status();
        foreach ($s['modified'] as $n) {
            $warnings[] = "Migration {$n} was changed after it was applied (checksum differs). It will NOT run again; add a new migration instead.";
        }
        foreach ($s['missing_files'] as $n) {
            $warnings[] = "Migration {$n} is recorded as applied but its file is missing from this release.";
        }
        $tables = (new SchemaInspector($this->conn))->tables();
        if ($s['applied'] === [] && count(array_diff($tables, ['migrations', 'migration_log'])) > 0) {
            $warnings[] = 'The database has tables but no migration history. Migrations will be reconciled additively (existing objects are skipped, nothing is dropped).';
        }
        return ['errors' => $errors, 'warnings' => $warnings];
    }

    // ------------------------------------------------------------------ migrate

    /**
     * Apply pending migrations. Returns the names applied.
     *
     * @return string[]
     */
    public function migrate(): array
    {
        $this->ensureTable();
        $this->acquireLock();
        $ran = [];
        try {
            $rows = $this->appliedRows();   // re-read inside the lock: another process may have just finished
            $this->recordMissingChecksums($rows);
            $batch = $rows === [] ? 1 : max(array_column($rows, 'batch')) + 1;
            foreach ($this->files() as $file) {
                $name = basename($file);
                if (isset($rows[$name])) {
                    continue;
                }
                $this->apply($file, $name, $batch);
                $ran[] = $name;
            }
        } finally {
            $this->releaseLock();
        }

        // PRN / roll-number migration: fill identifiers for pre-existing students
        // (idempotent, never overwrites, reports anything it cannot resolve).
        if (in_array('031_prn_and_roll_numbers.sql', $ran, true) && $this->conn === Connection::instance()) {
            try {
                $report = (new \App\Services\StudentIdentifierService())->backfill();
                $this->log('PRN backfill: ' . json_encode($report));
            } catch (\Throwable $e) {
                $this->log('PRN backfill FAILED (re-run: php bin/console students:backfill-identifiers): ' . $e->getMessage());
            }
        }
        return $ran;
    }

    /**
     * What migrate() would execute, per migration, for the connected engine. Changes nothing.
     *
     * @return array<string,string[]>
     */
    public function pretend(): array
    {
        $this->ensureTable();
        $rows = $this->appliedRows();
        $out = [];
        foreach ($this->files() as $f) {
            if (!isset($rows[basename($f)])) {
                $out[basename($f)] = $this->engineStatements($f, basename($f), 'up');
            }
        }
        return $out;
    }

    /**
     * Re-run every migration's (idempotent) statements without touching the history. Repairs a
     * partially initialised or damaged schema: missing tables/columns/indexes/keys are created,
     * existing objects are skipped, no data is removed. Take a backup first.
     *
     * @return string[] migrations processed
     */
    public function reapplyAll(): array
    {
        $this->ensureTable();
        $this->acquireLock();
        $done = [];
        try {
            foreach ($this->files() as $file) {
                $name = basename($file);
                $this->execute($name, $this->engineStatements($file, $name, 'up'), null);
                $this->event($name, 'reapplied', 'idempotent re-run (repair)');
                $done[] = $name;
            }
        } finally {
            $this->releaseLock();
        }
        return $done;
    }

    private function apply(string $file, string $name, int $batch): void
    {
        $t0 = microtime(true);
        $this->event($name, 'started', null);
        $this->log("MIGRATION {$name}: start");
        try {
            $statements = $this->engineStatements($file, $name, 'up');
            $sum = self::checksum($file);
            $record = function () use ($name, $batch, $sum, $t0): void {
                $this->conn->statement(
                    'INSERT INTO `migrations` (`migration`, `batch`, `executed_at`, `checksum`, `execution_ms`) VALUES (?, ?, ?, ?, ?)',
                    [$name, $batch, date('Y-m-d H:i:s'), $sum, (int) round((microtime(true) - $t0) * 1000)]
                );
            };
            $this->execute($name, $statements, $record);
        } catch (\Throwable $e) {
            $this->event($name, 'failed', substr($e->getMessage(), 0, 500));
            $this->log("MIGRATION {$name}: FAILED " . $e->getMessage());
            throw $e;
        }
        $ms = (int) round((microtime(true) - $t0) * 1000);
        $this->event($name, 'applied', "batch {$batch}, {$ms} ms");
        $this->log("MIGRATION {$name}: done ({$ms} ms)");
    }

    /**
     * Run statements for the connected engine. PostgreSQL: one transaction including the bookkeeping row.
     * MySQL/MariaDB: statement by statement with idempotent skips, bookkeeping row last.
     *
     * @param string[] $statements already translated for this engine
     */
    private function execute(string $name, array $statements, ?callable $record): void
    {
        $pdo = $this->conn->pdo();
        if ($this->conn->isPostgres()) {
            $pdo->beginTransaction();
            try {
                foreach ($statements as $stmt) {
                    try {
                        $pdo->exec($stmt);
                    } catch (\PDOException $e) {
                        throw new \RuntimeException("Migration {$name} failed and was rolled back: " . $e->getMessage() . ' [statement: ' . $this->summarize($stmt) . ']', 0, $e);
                    }
                }
                if ($record !== null) {
                    $record();
                }
                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
            return;
        }

        foreach ($statements as $stmt) {
            try {
                $pdo->exec($stmt);
            } catch (\PDOException $e) {
                $code = (int) ($e->errorInfo[1] ?? 0);
                // Defence-in-depth triggers need TRIGGER/SUPER under binary logging (MySQL error 1419).
                if ($code === 1419 && preg_match('/^\s*CREATE\s+TRIGGER/i', $stmt) === 1) {
                    $this->log('  WARN (MySQL 1419): trigger not created, privilege missing: ' . $this->summarize($stmt));
                    continue;
                }
                if ($this->isIgnorable($code, $e->getMessage())) {
                    $this->log("  SKIP (already applied, MySQL {$code}): " . $this->summarize($stmt));
                    continue;
                }
                $this->log("  FAIL (MySQL {$code}): " . $this->summarize($stmt) . ' -- ' . $e->getMessage());
                throw new \RuntimeException("Migration {$name} failed: " . $e->getMessage() . ' [statement: ' . $this->summarize($stmt) . ']', 0, $e);
            }
        }
        if ($record !== null) {
            $record();
        }
    }

    /**
     * The exact statements for one migration file on the connected engine.
     *
     * @return string[]
     */
    private function engineStatements(string $file, string $name, string $direction): array
    {
        if (str_ends_with($file, '.php')) {
            $def = require $file;
            if (!is_array($def) || !isset($def[$direction]) || !is_callable($def[$direction])) {
                throw new \RuntimeException("Migration {$name} has no '{$direction}' definition.");
            }
            $schema = new Schema();
            $def[$direction]($schema);
            $mysql = $schema->statements();
            if (!$this->conn->isPostgres()) {
                return $mysql;
            }
            $t = new PostgresTranslator();
            $out = [];
            foreach ($mysql as $s) {
                array_push($out, ...$t->translate($s));
            }
            return $out;
        }
        $sql = (string) file_get_contents($file);
        return $this->conn->isPostgres() ? $this->postgresStatements($name, $sql) : $this->splitStatements($sql);
    }

    /**
     * The PostgreSQL statements for one SQL migration (translation + native overrides).
     * Public so tooling and tests can inspect/validate exactly what would run.
     *
     * Optional native files in database/migrations_pgsql/:
     *   NNN_name.sql       replaces the automatic translation of that migration
     *   NNN_name.post.sql  extra statements run after it (triggers, functions)
     *
     * @return string[]
     */
    public function postgresStatements(string $name, string $sql): array
    {
        $dir = rtrim((string) Config::get('database.migrations_pgsql_path'), '/');
        $override = $dir . '/' . $name;
        $post = $dir . '/' . preg_replace('/\.sql$/', '.post.sql', $name);
        $statements = [];
        if (is_file($override)) {
            $statements = $this->splitStatements((string) file_get_contents($override));
        } else {
            $translator = new PostgresTranslator();
            foreach ($this->splitStatements($sql) as $stmt) {
                if (preg_match('/^\s*(CREATE|DROP)\s+TRIGGER/i', $stmt) === 1) {
                    continue; // MySQL trigger syntax; native equivalents live in the .post.sql file
                }
                array_push($statements, ...$translator->translate($stmt));
            }
        }
        if (is_file($post)) {
            array_push($statements, ...$this->splitStatements((string) file_get_contents($post)));
        }
        return $statements;
    }

    /**
     * PostgreSQL statements for ANY migration file (.sql or .php), whatever engine is connected.
     * Used by tooling that validates the translation against a real PostgreSQL server.
     *
     * @return string[]
     */
    public function postgresStatementsForFile(string $file): array
    {
        $name = basename($file);
        if (!str_ends_with($file, '.php')) {
            return $this->postgresStatements($name, (string) file_get_contents($file));
        }
        $def = require $file;
        $schema = new Schema();
        $def['up']($schema);
        $t = new PostgresTranslator();
        $out = [];
        foreach ($schema->statements() as $stmt) {
            array_push($out, ...$t->translate($stmt));
        }
        return $out;
    }

    // ------------------------------------------------------------------ rollback

    /**
     * Reverse the most recent batch(es), newest migration first. Only migrations that ship a rollback are
     * reversible, and every rollback's guards (queries that must return 0) must pass: a rollback that would
     * discard data is refused. Pass $allowDataLoss only after a verified backup and explicit human approval.
     *
     * @return array<int,array{migration:string,status:string,detail:string}>
     */
    public function rollback(int $batches = 1, bool $allowDataLoss = false): array
    {
        $this->ensureTable();
        $this->acquireLock();
        $report = [];
        try {
            $rows = $this->appliedRows();
            if ($rows === []) {
                return [];
            }
            $maxBatch = max(array_column($rows, 'batch'));
            $targets = array_filter($rows, static fn ($r) => $r['batch'] > $maxBatch - max(1, $batches));
            uasort($targets, static fn ($a, $b) => $b['id'] <=> $a['id']);
            // Plan everything first so a non-reversible migration stops the run before any change.
            $plans = [];
            foreach (array_keys($targets) as $name) {
                $plans[$name] = $this->rollbackPlan($name);
                if ($plans[$name] === null) {
                    throw new \RuntimeException("Migration {$name} has no rollback (database/rollbacks/{$name}); it is not reversible. Nothing was changed.");
                }
                foreach ($plans[$name]['guards'] as $g) {
                    $n = (int) $this->conn->scalar($g);
                    if ($n !== 0 && !$allowDataLoss) {
                        throw new \RuntimeException("Rollback of {$name} refused: it would discard data (guard `{$g}` returned {$n}). Nothing was changed.");
                    }
                }
            }
            foreach ($plans as $name => $plan) {
                $this->execute($name, $plan['statements'], function () use ($name): void {
                    $this->conn->statement('DELETE FROM `migrations` WHERE `migration` = ?', [$name]);
                });
                $this->event($name, 'rolled_back', null);
                $this->log("MIGRATION {$name}: rolled back");
                $report[] = ['migration' => $name, 'status' => 'rolled_back', 'detail' => count($plan['statements']) . ' statement(s)'];
            }
        } finally {
            $this->releaseLock();
        }
        return $report;
    }

    /** @return array{statements:string[],guards:string[]}|null */
    private function rollbackPlan(string $name): ?array
    {
        $dir = rtrim((string) (Config::get('database.rollbacks_path') ?: dirname($this->path) . '/rollbacks'), '/');
        $sqlFile = $dir . '/' . $name;
        if (str_ends_with($name, '.php')) {
            $file = rtrim($this->path, '/') . '/' . $name;
            if (!is_file($file)) {
                return null;
            }
            $def = require $file;
            if (!isset($def['down']) || !is_callable($def['down'])) {
                return null;
            }
            $schema = new Schema();
            $def['down']($schema);
            $stmts = $schema->statements();
            if ($this->conn->isPostgres()) {
                $t = new PostgresTranslator();
                $stmts = array_merge(...array_map(static fn ($s) => $t->translate($s), $stmts));
            }
            return ['statements' => $stmts, 'guards' => $schema->guards()];
        }
        if (!is_file($sqlFile)) {
            return null;
        }
        $raw = (string) file_get_contents($sqlFile);
        $guards = [];
        if (preg_match_all('/^\s*--\s*@guard:\s*(.+)$/mi', $raw, $m) > 0) {
            $guards = array_map('trim', $m[1]);
        }
        $stmts = $this->splitStatements($raw);
        if ($this->conn->isPostgres()) {
            $t = new PostgresTranslator();
            $stmts = array_merge(...array_map(static fn ($s) => $t->translate($s), $stmts));
        }
        return ['statements' => $stmts, 'guards' => $guards];
    }

    // ------------------------------------------------------------------ locking

    private function acquireLock(): void
    {
        if ($this->locked) {
            return;
        }
        if ($this->conn->isPostgres()) {
            $deadline = time() + self::LOCK_WAIT_SECONDS;
            while (!(bool) $this->conn->scalar('SELECT pg_try_advisory_lock(hashtext(?))', [self::LOCK_NAME])) {
                if (time() >= $deadline) {
                    throw new \RuntimeException('Another migration is running; waited ' . self::LOCK_WAIT_SECONDS . ' seconds. Try again shortly.');
                }
                usleep(200000);
            }
        } elseif ((int) $this->conn->scalar('SELECT GET_LOCK(?, ?)', [self::LOCK_NAME, self::LOCK_WAIT_SECONDS]) !== 1) {
            throw new \RuntimeException('Another migration is running; waited ' . self::LOCK_WAIT_SECONDS . ' seconds. Try again shortly.');
        }
        $this->locked = true;
    }

    private function releaseLock(): void
    {
        if (!$this->locked) {
            return;
        }
        try {
            if ($this->conn->isPostgres()) {
                $this->conn->scalar('SELECT pg_advisory_unlock(hashtext(?))', [self::LOCK_NAME]);
            } else {
                $this->conn->scalar('SELECT RELEASE_LOCK(?)', [self::LOCK_NAME]);
            }
        } catch (\Throwable) {
        }
        $this->locked = false;
    }

    // ------------------------------------------------------------------ helpers

    /** @param array<string,array{id:int,batch:int,checksum:?string}> $rows */
    private function recordMissingChecksums(array $rows): void
    {
        foreach ($this->files() as $f) {
            $n = basename($f);
            if (isset($rows[$n]) && $rows[$n]['checksum'] === null) {
                $this->conn->statement('UPDATE `migrations` SET `checksum` = ? WHERE `migration` = ?', [self::checksum($f), $n]);
            }
        }
    }

    private function event(string $name, string $event, ?string $message): void
    {
        try {
            $this->conn->statement('INSERT INTO `migration_log` (`migration`, `event`, `message`, `created_at`) VALUES (?, ?, ?, ?)', [$name, $event, $message, date('Y-m-d H:i:s')]);
        } catch (\Throwable) {
            // history is best effort; it must never block or hide a migration result
        }
    }

    private function q(string $ident): string
    {
        return $this->conn->dialect()->quote($ident);
    }

    /**
     * MySQL/MariaDB error codes that mean the object is already in the desired state, so the statement
     * is safe to skip when a migration is re-run over a partially applied schema.
     */
    private function isIgnorable(int $code, string $message = ''): bool
    {
        if ($code === 1005 && str_contains($message, 'errno: 121')) {
            return true; // MariaDB: duplicate foreign key constraint name
        }
        return in_array($code, [
            1050, // table already exists
            1060, // duplicate column name
            1061, // duplicate key name
            1091, // can't DROP; column/key does not exist
            1051, // unknown table (DROP)
            1826, // duplicate foreign key constraint name
            1022, // duplicate key
            1359, // trigger already exists
        ], true);
    }

    /**
     * Split a migration file into individual statements. Strips SQL line comments and splits on
     * semicolons; files with `DELIMITER //` blocks (triggers) use the mysql-client style splitter.
     *
     * @return string[]
     */
    private function splitStatements(string $sql): array
    {
        if (preg_match('/^\s*DELIMITER\s+\S+\s*$/mi', $sql) === 1) {
            return $this->splitWithDelimiters($sql);
        }
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $parts = preg_split('/;\s*(\r?\n|$)/', $sql) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') {
                $out[] = $p;
            }
        }
        return $out;
    }

    /** @return string[] */
    private function splitWithDelimiters(string $sql): array
    {
        $delimiter = ';';
        $buffer = '';
        $out = [];
        foreach (preg_split('/\r?\n/', $sql) ?: [] as $line) {
            if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $line, $m) === 1) {
                if (trim($buffer) !== '') {
                    $out[] = trim($buffer);
                }
                $buffer = '';
                $delimiter = $m[1];
                continue;
            }
            if (preg_match('/^\s*--/', $line) === 1) {
                continue;
            }
            $buffer .= $line . "\n";
            $trim = rtrim($buffer);
            if (str_ends_with($trim, $delimiter)) {
                $stmt = trim(substr($trim, 0, -strlen($delimiter)));
                if ($stmt !== '') {
                    $out[] = $stmt;
                }
                $buffer = '';
            }
        }
        if (trim($buffer) !== '') {
            $out[] = trim($buffer);
        }
        return $out;
    }

    private function summarize(string $stmt): string
    {
        $s = preg_replace('/\s+/', ' ', trim($stmt)) ?? $stmt;
        return strlen($s) > 90 ? substr($s, 0, 90) . '...' : $s;
    }

    private function log(string $line): void
    {
        try {
            $dir = (string) Config::get('paths.logs');
            if ($dir !== '' && !is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            @file_put_contents(rtrim($dir, '/') . '/install.log', '[' . date('Y-m-d H:i:s') . '] ' . $line . PHP_EOL, FILE_APPEND);
        } catch (\Throwable) {
            // logging is best-effort; never let it break a migration
        }
    }

    /**
     * Drop every table. DESTRUCTIVE: for disposable test databases only. The console refuses it
     * on a database that holds data unless --force is given; nothing else in the application calls it.
     */
    public function fresh(): void
    {
        $pdo = $this->conn->pdo();
        if ($this->conn->isPostgres()) {
            $schema = (string) ($this->conn->scalar('SELECT current_schema()') ?: 'public');
            $q = '"' . str_replace('"', '', $schema) . '"';
            $pdo->exec("DROP SCHEMA {$q} CASCADE");
            $pdo->exec("CREATE SCHEMA {$q}");
            return;
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->conn->select('SHOW TABLES') as $row) {
            $table = array_values($row)[0];
            $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
