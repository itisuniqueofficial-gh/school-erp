<?php

declare(strict_types=1);

namespace App\Services\Database;

use App\Services\Portability\PortableDumpService;
use App\Services\Setup\SetupException;
use App\Services\Setup\SetupService;
use Core\Database\Connection;
use Core\Database\DatabaseConfig;
use Database\Migrator;

/**
 * Backups the application creates, registers (database_backups), verifies and prunes, plus restore
 * verification in an isolated scratch database.
 *
 * Policy (env):
 *   BACKUP_BEFORE_MIGRATE = required (default) | optional | off   what upgrades do about a backup
 *   BACKUP_RETENTION_COUNT = 14   keep the newest N scheduled/manual backups (0 = never delete)
 *   BACKUP_PASSPHRASE      = optional, encrypts every backup the application creates (AES-256-GCM)
 *
 * Restoring never overwrites a database. The supported restore is "new empty database + db:import", then
 * switch the application over; that is the only path that can be verified before cut-over.
 */
final class BackupManager
{
    public const KINDS = ['manual', 'scheduled', 'pre_migration', 'pre_restore'];

    private Connection $conn;

    public function __construct(?Connection $conn = null)
    {
        $this->conn = $conn ?? Connection::instance();
    }

    public static function policy(): string
    {
        $v = strtolower(trim((string) (\Core\Support\Env::get('BACKUP_BEFORE_MIGRATE', 'required') ?? 'required')));
        return in_array($v, ['required', 'optional', 'off'], true) ? $v : 'required';
    }

    public function directory(): string
    {
        return rtrim((string) config('paths.backups'), '/');
    }

    /**
     * @return array{id:int,filename:string,path:string,bytes:int,tables:int,rows:int,sha256:string,encrypted:bool}
     */
    public function create(string $kind = 'manual', ?int $userId = null, ?string $passphrase = null): array
    {
        if (!in_array($kind, self::KINDS, true)) {
            throw new \InvalidArgumentException('Unknown backup kind.');
        }
        $passphrase ??= (string) (\Core\Support\Env::get('BACKUP_PASSPHRASE', '') ?? '');
        $dir = $this->directory();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new SetupException('The backup folder could not be created.');
        }
        $file = sprintf('erp-%s-%s-%s.erpdump%s', $kind, $this->conn->engine(), date('Ymd-His'), $passphrase !== '' ? '.enc' : '');
        $r = (new PortableDumpService($this->conn))->export($dir . '/' . $file, $passphrase !== '' ? $passphrase : null);
        $now = date('Y-m-d H:i:s');
        $id = 0;
        try {
            $this->conn->statement(
                'INSERT INTO `database_backups` (`filename`,`kind`,`engine`,`size_bytes`,`tables_count`,`rows_count`,`sha256`,`encrypted`,`status`,`verified_at`,`created_by`,`created_at`,`updated_at`)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$file, $kind, $r['engine'], $r['bytes'], $r['tables'], $r['rows'], $r['sha256'], $r['encrypted'] ? 1 : 0, 'verified', $now, $userId, $now, $now]
            );
            $id = (int) $this->conn->lastInsertId('database_backups');
        } catch (\Throwable) {
            // Upgrading from a release that predates the backup register (migration 034): the file is
            // still verified and on disk; it is simply not listed until the register exists.
        }
        if ($id > 0) {
            $this->prune();
        }
        return ['id' => $id, 'filename' => $file, 'path' => $r['path'], 'bytes' => $r['bytes'], 'tables' => $r['tables'], 'rows' => $r['rows'], 'sha256' => $r['sha256'], 'encrypted' => $r['encrypted']];
    }

    /**
     * Register a backup file made before the register table existed (an upgrade's pre-migration backup is
     * taken while migration 034 is still pending). Re-verifies the file first. No-op if already registered.
     */
    public function registerExisting(string $filename, string $kind, ?int $userId = null): bool
    {
        $path = $this->resolve($filename);
        if ((int) $this->conn->scalar('SELECT COUNT(*) FROM `database_backups` WHERE `filename` = ?', [basename($path)]) > 0) {
            return true;
        }
        $pass = (string) (\Core\Support\Env::get('BACKUP_PASSPHRASE', '') ?? '');
        $v = (new PortableDumpService($this->conn))->verify($path, $pass !== '' ? $pass : null);
        $now = date('Y-m-d H:i:s');
        $this->conn->statement(
            'INSERT INTO `database_backups` (`filename`,`kind`,`engine`,`size_bytes`,`tables_count`,`rows_count`,`sha256`,`encrypted`,`status`,`verified_at`,`created_by`,`created_at`,`updated_at`)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [basename($path), $kind, (string) ($v['engine'] ?? $this->conn->engine()), (int) filesize($path), $v['tables'], $v['rows'], hash_file('sha256', $path),
                str_ends_with($path, '.enc') ? 1 : 0, $v['ok'] ? 'verified' : 'failed', $now, $userId, date('Y-m-d H:i:s', (int) filemtime($path)), $now]
        );
        return $v['ok'];
    }

    /** @return array<int,array<string,mixed>> newest first; each row gains `exists` and `age_hours` */
    public function list(int $limit = 50): array
    {
        $rows = $this->conn->select('SELECT * FROM `database_backups` ORDER BY `id` DESC LIMIT ' . max(1, $limit));
        foreach ($rows as &$r) {
            $r['exists'] = is_file($this->directory() . '/' . $r['filename']);
            $r['age_hours'] = $r['created_at'] ? (int) floor((time() - strtotime((string) $r['created_at'])) / 3600) : null;
        }
        return $rows;
    }

    /**
     * Re-read a registered backup and compare checksums. Updates the register.
     *
     * @return array{ok:bool,message:string}
     */
    public function verify(string $filename, ?string $passphrase = null): array
    {
        $path = $this->resolve($filename);
        $passphrase ??= (string) (\Core\Support\Env::get('BACKUP_PASSPHRASE', '') ?? '');
        $v = (new PortableDumpService($this->conn))->verify($path, $passphrase !== '' ? $passphrase : null);
        // A missing or wrong passphrase says nothing about the file's integrity: do not flag the backup as failed.
        $keyProblem = !$v['ok'] && (str_contains($v['message'], 'passphrase') || str_contains($v['message'], 'decrypt'));
        if (!$keyProblem) {
            try {
                $this->conn->statement('UPDATE `database_backups` SET `status` = ?, `verified_at` = ?, `updated_at` = ? WHERE `filename` = ?',
                    [$v['ok'] ? 'verified' : 'failed', date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), basename($filename)]);
            } catch (\Throwable) {
            }
        }
        return ['ok' => $v['ok'], 'message' => $v['message']];
    }

    /**
     * Prove a backup can be restored: load it into a SCRATCH database (never the live one), migrate it,
     * import, and compare counts / foreign keys. The scratch database must be empty or non-existent; it is
     * created only when missing and when the user may create it, and it is left in place for inspection.
     *
     * @return array{ok:bool,message:string,steps:string[]}
     */
    public function restoreTest(string $filename, DatabaseConfig $scratch, ?string $passphrase = null): array
    {
        $steps = [];
        $path = $this->resolve($filename);
        $active = Connection::instance();
        $activeDb = (string) \Core\Config\Config::get('database.connections.' . \Core\Config\Config::get('database.default') . '.database');
        $activeHost = (string) \Core\Config\Config::get('database.connections.' . \Core\Config\Config::get('database.default') . '.host');
        $activePort = (int) \Core\Config\Config::get('database.connections.' . \Core\Config\Config::get('database.default') . '.port');
        if (strcasecmp($scratch->database, $activeDb) === 0 && $scratch->host === $activeHost && $scratch->port === $activePort) {
            throw new SetupException('The scratch database must be a different database from the live one.');
        }
        if ($scratch->database === '') {
            throw new SetupException('Give the scratch database a name.');
        }
        $setup = new SetupService(dirname(__DIR__, 3));
        $i = $setup->inspectConnection($scratch);
        if (!$i['ok']) {
            throw new SetupException('Cannot reach the scratch database server. ' . $i['message']);
        }
        if (!$i['database_exists']) {
            $setup->createDatabase($scratch);
            $steps[] = 'Created scratch database ' . $scratch->database;
        } elseif ($i['tables'] > 0) {
            throw new SetupException('The scratch database "' . $scratch->database . '" already contains ' . $i['tables'] . ' table(s). Use an empty or new database; nothing was changed.');
        }

        $conn = Connection::forConfig($scratch);
        $mig = new Migrator(null, $conn);
        $mig->migrate();
        $steps[] = 'Schema created by migrations on ' . $conn->engine();
        $r = (new PortableDumpService($conn))->import($path, $passphrase ?? ((string) (\Core\Support\Env::get('BACKUP_PASSPHRASE', '') ?? '') ?: null));
        $steps[] = $r['message'];
        $val = (new SchemaManifest($conn))->validate();
        $steps[] = 'Schema check: ' . $val['message'];
        $ok = $r['ok'] && $val['ok'];
        if ($ok) {
            $this->conn->statement('UPDATE `database_backups` SET `status` = ?, `updated_at` = ? WHERE `filename` = ?', ['restore_tested', date('Y-m-d H:i:s'), basename($filename)]);
        }
        return ['ok' => $ok, 'message' => $ok ? 'Restore test passed: the backup restores completely into an isolated database.' : 'Restore test FAILED: ' . $r['message'], 'steps' => $steps];
    }

    /**
     * Delete old scheduled/manual backups beyond BACKUP_RETENTION_COUNT. pre_migration / pre_restore
     * backups are kept (the newest 5 of each); the newest verified backup is never deleted.
     *
     * @return string[] deleted file names
     */
    public function prune(?int $keep = null): array
    {
        $keep ??= (int) (\Core\Support\Env::get('BACKUP_RETENTION_COUNT', 14) ?? 14);
        if ($keep <= 0) {
            return [];
        }
        $deleted = [];
        foreach ([['manual', 'scheduled'], ['pre_migration'], ['pre_restore']] as $i => $kinds) {
            $limit = $i === 0 ? $keep : 5;
            $ph = implode(',', array_fill(0, count($kinds), '?'));
            $rows = $this->conn->select("SELECT `id`,`filename` FROM `database_backups` WHERE `kind` IN ({$ph}) AND `status` <> 'failed' ORDER BY `id` DESC", $kinds);
            foreach (array_slice($rows, $limit) as $r) {
                $p = $this->directory() . '/' . $r['filename'];
                if (is_file($p)) {
                    @unlink($p);
                }
                $this->conn->statement('DELETE FROM `database_backups` WHERE `id` = ?', [(int) $r['id']]);
                $deleted[] = (string) $r['filename'];
            }
        }
        return $deleted;
    }

    /** @return array{count:int,last:?array<string,mixed>,last_age_hours:?int,warning:?string} */
    public function summary(): array
    {
        $rows = $this->list(1);
        $last = $rows[0] ?? null;
        $verified = $this->conn->selectOne("SELECT `filename`, `created_at` FROM `database_backups` WHERE `status` IN ('verified','restore_tested') ORDER BY `id` DESC LIMIT 1");
        $age = $verified ? (int) floor((time() - strtotime((string) $verified['created_at'])) / 3600) : null;
        $warn = null;
        if ($verified === null) {
            $warn = 'No verified backup exists yet.';
        } elseif ($age !== null && $age > 24 * 7) {
            $warn = 'The newest verified backup is ' . intdiv($age, 24) . ' days old.';
        } elseif (!is_file($this->directory() . '/' . $verified['filename'])) {
            $warn = 'The newest verified backup file is missing from the backup folder.';
        }
        return ['count' => (int) $this->conn->scalar('SELECT COUNT(*) FROM `database_backups`'), 'last' => $last, 'last_age_hours' => $age, 'warning' => $warn];
    }

    /** Only plain file names inside the backup folder; no traversal. */
    private function resolve(string $filename): string
    {
        $name = basename($filename);
        $path = $this->directory() . '/' . $name;
        if ($name === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $name) || !is_file($path)) {
            // Allow an absolute path only from the CLI (explicit operator choice).
            if (PHP_SAPI === 'cli' && is_file($filename)) {
                return $filename;
            }
            throw new SetupException('Backup file not found.');
        }
        return $path;
    }
}
