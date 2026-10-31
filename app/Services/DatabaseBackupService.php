<?php

declare(strict_types=1);

namespace App\Services;

use Core\Config\Config;
use Core\Database\Connection;
use Core\Database\DatabaseManager;

class DatabaseBackupService
{
    private string $backupDir;

    public function __construct()
    {
        $this->backupDir = (string) Config::get('paths.storage') . '/backups';
        if (!is_dir($this->backupDir)) {
            @mkdir($this->backupDir, 0775, true);
        }
    }

    public function create(string $label = null): ?string
    {
        $driver = DatabaseManager::instance()->getDriver();
        $timestamp = date('Y-m-d_His');
        $name = 'backup-' . $timestamp . ($label ? '-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $label) : '') . '.sql';
        $path = $this->backupDir . '/' . $name;

        try {
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $this->backupMysql($path);
                return $path;
            }
            // Portable SQL dump for other drivers
            $this->backupPortable($path);
            return $path;
        } catch (\Throwable $e) {
            @unlink($path);
            // Fallback to portable
            try {
                $this->backupPortable($path);
                return $path;
            } catch (\Throwable) {
                return null;
            }
        }
    }

    private function backupMysql(string $path): void
    {
        $config = (array) Config::get('database.connections.' . Config::get('database.default'));
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 3306;
        $user = $config['username'] ?? 'root';
        $pass = $config['password'] ?? '';
        $db = $config['database'] ?? 'school_erp';

        // Try common mysqldump locations
        $mysqldump = null;
        $candidates = ['mysqldump', '/usr/bin/mysqldump', '/usr/local/bin/mysqldump'];
        $lampp = getenv('XAMPP_HOME') ?: '/opt/lampp';
        $candidates[] = $lampp . '/bin/mysqldump';
        foreach ($candidates as $c) {
            if ($c === 'mysqldump') {
                $which = @trim((string) shell_exec('command -v mysqldump 2>/dev/null'));
                if ($which) { $mysqldump = $which; break; }
            } elseif (is_file($c) && is_executable($c)) {
                $mysqldump = $c; break;
            }
        }
        if (!$mysqldump) {
            throw new \RuntimeException('mysqldump not found');
        }

        $cmd = sprintf('%s --host=%s --port=%d --user=%s --single-transaction --routines --triggers --skip-lock-tables %s > %s 2>&1',
            escapeshellarg($mysqldump), escapeshellarg($host), (int)$port, escapeshellarg($user), escapeshellarg($db), escapeshellarg($path));
        if ($pass !== '') {
            // Use MYSQL_PWD carefully or --password; for portability just pass
            $cmd = sprintf('MYSQL_PWD=%s %s --host=%s --port=%d --user=%s --single-transaction --routines --triggers --skip-lock-tables %s > %s 2>&1',
                escapeshellarg($pass), escapeshellarg($mysqldump), escapeshellarg($host), (int)$port, escapeshellarg($user), escapeshellarg($db), escapeshellarg($path));
        }
        @shell_exec($cmd);
        if (!is_file($path) || filesize($path) < 100) {
            throw new \RuntimeException('Backup failed');
        }
    }

    private function backupPortable(string $path): void
    {
        $pdo = Connection::instance()->pdo();
        $driver = DatabaseManager::instance()->getDriver();
        $out = fopen($path, 'w');
        fwrite($out, "-- School ERP Portable SQL Backup\n");
        fwrite($out, "-- Driver: {$driver}\n");
        fwrite($out, "-- Date: " . date('Y-m-d H:i:s') . "\n\n");
        fwrite($out, "SET FOREIGN_KEY_CHECKS=0;\n\n");

        $tables = [];
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $rows = $pdo->query('SHOW TABLES')->fetchAll();
            foreach ($rows as $r) { $tables[] = array_values($r)[0]; }
        } elseif ($driver === 'pgsql') {
            $rows = $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename")->fetchAll();
            foreach ($rows as $r) { $tables[] = $r['tablename']; }
        } elseif ($driver === 'sqlite') {
            $rows = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll();
            foreach ($rows as $r) { $tables[] = $r['name']; }
        }

        foreach ($tables as $table) {
            try {
                $rows = $pdo->query('SELECT * FROM ' . DatabaseManager::instance()->quoteIdentifier($table))->fetchAll();
                if (empty($rows)) continue;
                foreach ($rows as $row) {
                    $cols = array_keys($row);
                    $colStr = implode(', ', array_map([DatabaseManager::instance(), 'quoteIdentifier'], $cols));
                    $vals = [];
                    foreach ($cols as $c) {
                        $v = $row[$c];
                        if ($v === null) { $vals[] = 'NULL'; continue; }
                        if (is_int($v) || is_float($v)) { $vals[] = (string)$v; continue; }
                        if (is_bool($v)) { $vals[] = $v ? '1' : '0'; continue; }
                        $vals[] = $pdo->quote((string)$v);
                    }
                    fwrite($out, 'INSERT INTO ' . DatabaseManager::instance()->quoteIdentifier($table) . " ({$colStr}) VALUES (" . implode(', ', $vals) . ");\n");
                }
                fwrite($out, "\n");
            } catch (\Throwable $e) {
                fwrite($out, "-- Error backing up {$table}: " . $e->getMessage() . "\n\n");
            }
        }
        fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($out);
    }

    public function list(): array
    {
        $files = glob($this->backupDir . '/*.sql') ?: [];
        $list = [];
        foreach ($files as $f) {
            $list[] = [
                'name' => basename($f),
                'path' => $f,
                'size' => filesize($f),
                'date' => date('Y-m-d H:i:s', filemtime($f)),
            ];
        }
        usort($list, function($a,$b){ return strcmp($b['date'],$a['date']); });
        return $list;
    }

    public function restore(string $path, bool $confirm = false): array
    {
        if (!$confirm) {
            return ['ok' => false, 'error' => 'Confirmation required to restore a backup.'];
        }
        if (!is_file($path)) {
            return ['ok' => false, 'error' => 'Backup file not found.'];
        }
        $sql = file_get_contents($path);
        if ($sql === false) {
            return ['ok' => false, 'error' => 'Could not read backup file.'];
        }
        try {
            $pdo = Connection::instance()->pdo();
            $driver = DatabaseManager::instance()->getDriver();
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            }
            // Split and execute
            $statements = preg_split('/;\s*\n/', $sql);
            $count = 0;
            foreach ($statements as $stmt) {
                $stmt = trim($stmt);
                if ($stmt === '' || str_starts_with($stmt, '--')) continue;
                try {
                    $pdo->exec($stmt);
                    $count++;
                } catch (\Throwable $e) {
                    // Continue on errors for compatibility
                }
            }
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            }
            return ['ok' => true, 'executed' => $count];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Restore failed.'];
        }
    }
}
