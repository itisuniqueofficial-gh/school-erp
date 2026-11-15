<?php

declare(strict_types=1);

namespace App\Services\Database;

use Core\Database\Connection;
use Core\Database\DatabasePreflight;
use Core\Database\SchemaInspector;
use Database\Migrator;

/**
 * Read-only picture of the database for the dashboard and `check`. Never returns credentials.
 */
final class DatabaseHealthService
{
    public function __construct(private readonly ?Connection $conn = null)
    {
    }

    /** @return array<string,mixed> */
    public function report(): array
    {
        $conn = $this->conn ?? Connection::instance();
        $cfg = (array) \Core\Config\Config::get('database.connections.' . \Core\Config\Config::get('database.default'), []);
        $r = [
            'engine' => $conn->engine(), 'database' => (string) ($cfg['database'] ?? ''), 'host' => ($cfg['socket'] ?? '') !== '' ? 'socket' : (string) ($cfg['host'] ?? ''),
            'port' => (int) ($cfg['port'] ?? 0), 'username' => (string) ($cfg['username'] ?? ''), 'ssl_mode' => (string) ($cfg['ssl_mode'] ?? 'disable'),
            'reachable' => false, 'latency_ms' => null, 'version' => null, 'size_bytes' => null, 'connections' => null,
            'tables' => 0, 'schema_version' => null, 'migrations_applied' => 0, 'pending' => [], 'modified' => [], 'history' => [], 'events' => [],
            'schema' => ['ok' => true, 'checked' => false, 'message' => '', 'issues' => []], 'backups' => [], 'backup_summary' => ['count' => 0, 'last' => null, 'last_age_hours' => null, 'warning' => null],
            'recent_errors' => [], 'status' => 'fail', 'problems' => [], 'storage' => $this->storage(),
        ];
        try {
            $t = microtime(true);
            $r['version'] = (string) $conn->scalar('SELECT VERSION()');
            $r['latency_ms'] = round((microtime(true) - $t) * 1000, 1);
            $r['reachable'] = true;
        } catch (\Throwable $e) {
            [, $r['problems'][]] = DatabasePreflight::classify($e);
            $r['recent_errors'] = $this->logErrors();
            return $r;
        }

        $ins = new SchemaInspector($conn);
        $r['tables'] = count($ins->tables());
        $r['size_bytes'] = $this->size($conn);
        $r['connections'] = $this->connections($conn);
        $mig = new Migrator(null, $conn);
        $st = $mig->status();
        $r['migrations_applied'] = count($st['applied']);
        $r['pending'] = $st['pending'];
        $r['modified'] = $st['modified'];
        $r['schema_version'] = $st['applied'] === [] ? null : end($st['applied']);
        $r['history'] = $mig->history(25);
        $r['events'] = $mig->events(15);
        if ($st['pending'] === []) {
            $r['schema'] = (new SchemaManifest($conn))->validate();
        }
        try {
            $bm = new BackupManager($conn);
            $r['backups'] = $bm->list(15);
            $r['backup_summary'] = $bm->summary();
        } catch (\Throwable) {
        }
        $r['recent_errors'] = $this->logErrors(array_filter($r['events'], static fn ($e) => $e['event'] === 'failed'));

        if ($r['pending'] !== []) {
            $r['problems'][] = count($r['pending']) . ' pending migration(s).';
        }
        if (!$r['schema']['ok']) {
            $r['problems'][] = $r['schema']['message'];
        }
        if ($r['modified'] !== []) {
            $r['problems'][] = 'Applied migration file(s) changed after being applied: ' . implode(', ', $r['modified']) . '.';
        }
        if ($r['backup_summary']['warning'] !== null) {
            $r['problems'][] = $r['backup_summary']['warning'];
        }
        $r['status'] = match (true) {
            $r['pending'] !== [] || !$r['schema']['ok'] => 'warn',
            $r['problems'] !== [] => 'warn',
            default => 'ok',
        };
        return $r;
    }

    private function size(Connection $conn): ?int
    {
        try {
            return (int) ($conn->isPostgres()
                ? $conn->scalar('SELECT pg_database_size(current_database())')
                : $conn->scalar('SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.tables WHERE table_schema = DATABASE()'));
        } catch (\Throwable) {
            return null;
        }
    }

    private function connections(Connection $conn): ?int
    {
        try {
            return (int) ($conn->isPostgres()
                ? $conn->scalar('SELECT COUNT(*) FROM pg_stat_activity WHERE datname = current_database()')
                : $conn->selectOne("SHOW STATUS LIKE 'Threads_connected'")['Value'] ?? null);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{path:string,used_bytes:?int,free_bytes:?int} */
    private function storage(): array
    {
        $p = (string) config('paths.storage');
        $used = null;
        if (is_dir($p)) {
            $used = 0;
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($p, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($f->isFile()) {
                    $used += $f->getSize();
                }
            }
        }
        $free = is_dir($p) ? @disk_free_space($p) : false;
        return ['path' => basename($p), 'used_bytes' => $used, 'free_bytes' => $free === false ? null : (int) $free];
    }

    /**
     * Real database errors for the dashboard: failed migrations, plus ERROR-level (or worse) lines from the
     * application logs that come from the database layer. WARNING lines are not errors: they record
     * conflicts the application already handled (for example two people taking the same roll number at
     * the same moment, where the unique key correctly rejected the second). Identical messages are merged
     * with a count. Anything resembling a credential is removed.
     *
     * @param iterable<array<string,mixed>> $failed
     * @return string[]
     */
    private function logErrors(iterable $failed = []): array
    {
        $out = [];
        foreach ($failed as $f) {
            $out[] = $f['created_at'] . '  migration ' . $f['migration'] . ' failed: ' . $f['message'];
        }
        $seen = [];
        $db = strtolower((string) \Core\Config\Config::get('database.connections.' . \Core\Config\Config::get('database.default') . '.database'));
        foreach (glob(rtrim((string) config('paths.logs'), '/') . '/erp-*.log') ?: [] as $file) {
            if (!is_file($file) || filesize($file) > 5_000_000) {
                continue;
            }
            foreach (array_slice(file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -400) as $l) {
                if (preg_match('/^\[([^\]]+)\] (ERROR|CRITICAL|ALERT|EMERGENCY): (.*)$/', $l, $m) !== 1
                    || preg_match('/SQLSTATE|PDOException|Database (unavailable|connection)|Migration .* failed/i', $m[3]) !== 1) {
                    continue;
                }
                // Errors that name another database (test or scratch databases) are not about this one.
                // MySQL/MariaDB name the database as "Table 'db.table'" or "(`db`.`table`" (key names like 'students.uq_x' are not databases).
                if (preg_match_all("/(?:Table '([A-Za-z0-9_$]+)\\.|\\(`([A-Za-z0-9_$]+)`\\.`)/", $m[3], $mm) > 0 && ($dbs = [1 => array_filter(array_merge($mm[1], $mm[2]))]) && $db !== ''
                    && array_filter($dbs[1], static fn ($d) => strtolower($d) !== $db) !== []) {
                    continue;
                }
                $msg = \App\Services\Setup\SetupService::redact(mb_substr((string) preg_replace('/\s*\{"exception".*$/', '', $m[3]), 0, 240));
                if (isset($seen[$msg])) {
                    $seen[$msg]['n']++;
                    $seen[$msg]['last'] = $m[1];
                } else {
                    $seen[$msg] = ['n' => 1, 'last' => $m[1]];
                }
            }
        }
        uasort($seen, static fn ($a, $b) => strcmp($b['last'], $a['last']));
        foreach ($seen as $msg => $i) {
            $out[] = $i['last'] . '  ' . $msg . ($i['n'] > 1 ? '  (×' . $i['n'] . ')' : '');
        }
        return array_slice($out, 0, 10);
    }
}
