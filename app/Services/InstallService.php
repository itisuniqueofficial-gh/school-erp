<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\Connection;
use Database\Migrator;

/**
 * Detects whether the ERP is a FRESH install or an EXISTING (live) install.
 *
 * This is the guard that makes redeploys safe: an existing installation must
 * never be reseeded, its admin must never be recreated, and its data must never
 * be reset. "Installed" means the core `users` table exists and already holds at
 * least one account.
 */
final class InstallService
{
    /** True when a real installation already exists (has users). */
    public function isInstalled(): bool
    {
        try {
            if (!$this->tableExists('users')) {
                return false;
            }
            return $this->userCount() > 0;
        } catch (\Throwable) {
            // If we cannot determine state, treat as installed to avoid any
            // destructive fresh-install path running against a live database.
            return true;
        }
    }

    /** Whether it is safe to run the seeder (only on a fresh install). */
    public function shouldSeed(): bool
    {
        return !$this->isInstalled();
    }

    /**
     * @return array{installed:bool,users:int,tables:int,migrations_applied:int,migrations_pending:int,db_reachable:bool}
     */
    public function state(): array
    {
        $reachable = true;
        $users = 0;
        $tables = 0;
        $applied = 0;
        $pending = 0;
        try {
            Connection::instance();
            $tables = $this->tableCount();
            $users = $this->tableExists('users') ? $this->userCount() : 0;
            $status = (new Migrator())->status();
            $applied = count($status['applied'] ?? []);
            $pending = count($status['pending'] ?? []);
        } catch (\Throwable) {
            $reachable = false;
        }

        return [
            'installed'          => $reachable && $users > 0,
            'users'              => $users,
            'tables'             => $tables,
            'migrations_applied' => $applied,
            'migrations_pending' => $pending,
            'db_reachable'       => $reachable,
        ];
    }

    private function tableExists(string $table): bool
    {
        $row = Connection::instance()->selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$table]
        );
        return (int) ($row['c'] ?? 0) > 0;
    }

    private function tableCount(): int
    {
        $row = Connection::instance()->selectOne(
            'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE()'
        );
        return (int) ($row['c'] ?? 0);
    }

    private function userCount(): int
    {
        $row = Connection::instance()->selectOne('SELECT COUNT(*) AS c FROM `users`');
        return (int) ($row['c'] ?? 0);
    }
}
