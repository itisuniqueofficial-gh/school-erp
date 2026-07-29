<?php

declare(strict_types=1);

namespace Database;

use Core\Config\Config;
use Core\Database\Connection;

/**
 * Runs and tracks SQL migrations. Each *.sql file in the migrations directory
 * is executed once (in filename order) and recorded in the `migrations` table.
 */
final class Migrator
{
    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? (string) Config::get('database.migrations_path');
    }

    /**
     * Ensure the migration tracking table exists.
     */
    private function ensureTable(): void
    {
        Connection::instance()->pdo()->exec(
            'CREATE TABLE IF NOT EXISTS `migrations` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `migration` VARCHAR(191) NOT NULL,
                `batch` INT NOT NULL,
                `executed_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_migration` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC'
        );
    }

    /** @return string[] */
    private function files(): array
    {
        $files = glob(rtrim($this->path, '/') . '/*.sql') ?: [];
        sort($files, SORT_STRING);
        return $files;
    }

    /** @return string[] Names of already-applied migrations. */
    private function applied(): array
    {
        $rows = Connection::instance()->select('SELECT `migration` FROM `migrations`');
        return array_column($rows, 'migration');
    }

    /**
     * Run all pending migrations. Returns the list of applied migration names.
     *
     * @return string[]
     */
    public function migrate(): array
    {
        $this->ensureTable();
        $applied = $this->applied();
        $batch = $this->nextBatch();
        $ran = [];

        foreach ($this->files() as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) {
                continue;
            }

            $sql = (string) file_get_contents($file);
            $this->runMigrationFile($name, $sql);

            Connection::instance()->statement(
                'INSERT INTO `migrations` (`migration`, `batch`, `executed_at`) VALUES (?, ?, ?)',
                [$name, $batch, date('Y-m-d H:i:s')]
            );
            $ran[] = $name;
        }

        return $ran;
    }

    /**
     * Execute one migration file statement-by-statement. Idempotent &
     * recovery-safe: statements that fail because an object already exists
     * (duplicate column/key/table) or does not exist to drop are logged and
     * skipped, so a re-run over a partially-applied database completes cleanly
     * instead of aborting. Any other error is a real failure and is re-raised
     * with a clear, migration-scoped message.
     */
    private function runMigrationFile(string $name, string $sql): void
    {
        $pdo = Connection::instance()->pdo();
        $t0 = microtime(true);
        $this->log("MIGRATION {$name}: start");

        foreach ($this->splitStatements($sql) as $stmt) {
            try {
                $pdo->exec($stmt);
            } catch (\PDOException $e) {
                $code = (int) ($e->errorInfo[1] ?? 0);
                if ($this->isIgnorable($code)) {
                    $this->log("  SKIP (already applied, MySQL {$code}): " . $this->summarize($stmt));
                    continue;
                }
                $this->log("  FAIL (MySQL {$code}): " . $this->summarize($stmt) . ' -- ' . $e->getMessage());
                throw new \RuntimeException(
                    "Migration {$name} failed: " . $e->getMessage() . ' [statement: ' . $this->summarize($stmt) . ']',
                    0,
                    $e
                );
            }
        }

        $ms = (int) round((microtime(true) - $t0) * 1000);
        $this->log("MIGRATION {$name}: done ({$ms} ms)");
    }

    /**
     * MySQL error codes that mean the object is already in the desired state —
     * safe to skip for idempotent re-runs / partial-install recovery.
     */
    private function isIgnorable(int $code): bool
    {
        return in_array($code, [
            1050, // table already exists
            1060, // duplicate column name
            1061, // duplicate key name
            1091, // can't DROP; column/key does not exist
            1051, // unknown table (DROP)
            1826, // duplicate foreign key constraint name
            1022, // duplicate key
        ], true);
    }

    /**
     * Split a migration file into individual statements. Strips SQL line
     * comments and splits on semicolons (these migrations contain plain DDL/DML,
     * no stored routines), so each statement is executed and checked
     * independently.
     *
     * @return string[]
     */
    private function splitStatements(string $sql): array
    {
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
            @file_put_contents(
                rtrim($dir, '/') . '/install.log',
                '[' . date('Y-m-d H:i:s') . '] ' . $line . PHP_EOL,
                FILE_APPEND
            );
        } catch (\Throwable) {
            // logging is best-effort; never let it break a migration
        }
    }

    private function nextBatch(): int
    {
        $max = Connection::instance()->scalar('SELECT MAX(`batch`) FROM `migrations`');
        return (int) $max + 1;
    }

    /**
     * Drop all tables (fresh) — destructive; used by `migrate:fresh`.
     */
    public function fresh(): void
    {
        $pdo = Connection::instance()->pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $tables = Connection::instance()->select('SHOW TABLES');
        foreach ($tables as $row) {
            $table = array_values($row)[0];
            $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /** @return array{applied:string[],pending:string[]} */
    public function status(): array
    {
        $this->ensureTable();
        $applied = $this->applied();
        $pending = [];
        foreach ($this->files() as $file) {
            $name = basename($file);
            if (!in_array($name, $applied, true)) {
                $pending[] = $name;
            }
        }
        return ['applied' => $applied, 'pending' => $pending];
    }
}
