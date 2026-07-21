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
                `migration` VARCHAR(255) NOT NULL,
                `batch` INT NOT NULL,
                `executed_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_migration` (`migration`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
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
            $pdo = Connection::instance()->pdo();

            // Execute the whole file (may contain multiple statements).
            $pdo->exec($sql);

            Connection::instance()->statement(
                'INSERT INTO `migrations` (`migration`, `batch`, `executed_at`) VALUES (?, ?, ?)',
                [$name, $batch, date('Y-m-d H:i:s')]
            );
            $ran[] = $name;
        }

        return $ran;
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
