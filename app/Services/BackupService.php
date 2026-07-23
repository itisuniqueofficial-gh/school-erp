<?php

declare(strict_types=1);

namespace App\Services;

use Core\Config\Config;

/**
 * Database backup management: create (mysqldump), list, restore, download,
 * plus log/session cleanup used by scheduled maintenance commands.
 */
final class BackupService
{
    private function dir(): string
    {
        $dir = (string) Config::get('paths.backups');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    /** @return array{host:string,port:int,username:string,password:string,database:string} */
    private function db(): array
    {
        $c = (array) Config::get('database.connections.mysql');
        return [
            'host'     => (string) ($c['host'] ?? '127.0.0.1'),
            'port'     => (int) ($c['port'] ?? 3306),
            'username' => (string) ($c['username'] ?? 'root'),
            'password' => (string) ($c['password'] ?? ''),
            'database' => (string) ($c['database'] ?? ''),
        ];
    }

    /**
     * Create a SQL backup. Returns the file path on success or null on failure.
     */
    public function create(): ?string
    {
        $c = $this->db();
        $file = $this->dir() . '/backup-' . date('Y-m-d_His') . '.sql';
        $cmd = sprintf(
            'mysqldump --host=%s --port=%d --user=%s %s %s > %s 2>/dev/null',
            escapeshellarg($c['host']),
            $c['port'],
            escapeshellarg($c['username']),
            $c['password'] !== '' ? '--password=' . escapeshellarg($c['password']) : '',
            escapeshellarg($c['database']),
            escapeshellarg($file)
        );
        @exec($cmd, $out, $code);

        return ($code === 0 && is_file($file) && filesize($file) > 0) ? $file : null;
    }

    /**
     * Restore the database from a backup file (destructive — requires explicit action).
     */
    public function restore(string $filename): bool
    {
        $file = $this->dir() . '/' . basename($filename); // prevent traversal
        if (!is_file($file)) {
            return false;
        }
        $c = $this->db();
        $cmd = sprintf(
            'mysql --host=%s --port=%d --user=%s %s %s < %s 2>/dev/null',
            escapeshellarg($c['host']),
            $c['port'],
            escapeshellarg($c['username']),
            $c['password'] !== '' ? '--password=' . escapeshellarg($c['password']) : '',
            escapeshellarg($c['database']),
            escapeshellarg($file)
        );
        @exec($cmd, $out, $code);
        return $code === 0;
    }

    /**
     * Verify a backup file is a valid, non-empty, restorable SQL dump that
     * covers the critical tables. This is the "trust but verify" step: a backup
     * you cannot restore is not data safety.
     *
     * @return array{ok:bool,size:int,tables:int,inserts:int,critical:array<string,bool>,missing:array<int,string>,message:string}
     */
    public function verify(string $filename): array
    {
        $criticalTables = ['migrations', 'users', 'students', 'certificates', 'settings'];
        $r = ['ok' => false, 'size' => 0, 'tables' => 0, 'inserts' => 0, 'critical' => [], 'missing' => [], 'message' => ''];

        $file = $this->path($filename);
        if ($file === null) {
            $r['message'] = 'Backup file not found.';
            return $r;
        }
        $r['size'] = (int) filesize($file);
        if ($r['size'] <= 0) {
            $r['message'] = 'Backup file is empty (0 bytes).';
            return $r;
        }
        $content = @file_get_contents($file);
        if ($content === false || $content === '') {
            $r['message'] = 'Backup file could not be read.';
            return $r;
        }

        $r['tables'] = (int) preg_match_all('/CREATE TABLE/i', $content);
        $r['inserts'] = (int) preg_match_all('/INSERT INTO/i', $content);
        if ($r['tables'] === 0) {
            $r['message'] = 'No CREATE TABLE statements found — this is not a valid schema dump.';
            return $r;
        }

        foreach ($criticalTables as $t) {
            $present = (bool) preg_match('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?' . preg_quote($t, '/') . '`?/i', $content);
            $r['critical'][$t] = $present;
            if (!$present) {
                $r['missing'][] = $t;
            }
        }

        $r['ok'] = $r['missing'] === [];
        $r['message'] = $r['ok']
            ? sprintf('Valid dump: %d tables, %d insert statements, %s bytes.', $r['tables'], $r['inserts'], number_format($r['size']))
            : 'Missing critical tables in dump: ' . implode(', ', $r['missing']);
        return $r;
    }

    /** @return array<int,array{name:string,size:int,created:int}> */
    public function list(): array
    {
        $files = glob($this->dir() . '/*.sql') ?: [];
        rsort($files);
        return array_map(static fn ($f) => [
            'name'    => basename($f),
            'size'    => (int) filesize($f),
            'created' => (int) filemtime($f),
        ], $files);
    }

    public function path(string $filename): ?string
    {
        $file = $this->dir() . '/' . basename($filename);
        return is_file($file) ? $file : null;
    }

    public function delete(string $filename): bool
    {
        $file = $this->path($filename);
        return $file !== null && @unlink($file);
    }

    /**
     * Delete log files older than N days. Returns count removed.
     */
    public function cleanupLogs(int $days = 30): int
    {
        $removed = 0;
        $dir = (string) Config::get('paths.logs');
        $cutoff = time() - $days * 86400;
        foreach (glob(rtrim($dir, '/') . '/*.log') ?: [] as $f) {
            if (filemtime($f) < $cutoff && @unlink($f)) {
                $removed++;
            }
        }
        return $removed;
    }

    /**
     * Delete expired file-based sessions. Returns count removed.
     */
    public function cleanupSessions(int $minutes = 0): int
    {
        $removed = 0;
        $minutes = $minutes ?: (int) Config::get('session.lifetime', 120);
        $dir = (string) Config::get('session.path');
        $cutoff = time() - $minutes * 60;
        foreach (glob(rtrim($dir, '/') . '/sess_*') ?: [] as $f) {
            if (filemtime($f) < $cutoff && @unlink($f)) {
                $removed++;
            }
        }
        return $removed;
    }
}
