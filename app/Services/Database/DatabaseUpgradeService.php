<?php

declare(strict_types=1);

namespace App\Services\Database;

use App\Services\Setup\SetupException;
use Core\Database\Connection;
use Database\Migrator;

/**
 * The one safe path for changing the schema of an installed application
 * (used by `php bin/console upgrade`, the Database dashboard and optional
 * automatic upgrade on deploy):
 *
 *   preflight -> pending? -> backup (policy) -> migrate under lock -> verify schema -> report
 *
 * It never drops data. If the backup policy is "required" and no verified backup could be
 * made, nothing is changed.
 */
final class DatabaseUpgradeService
{
    public function __construct(private readonly ?Connection $conn = null)
    {
    }

    /**
     * @param array{no_backup?:bool,user_id?:?int,pretend?:bool} $opts
     * @return array{ok:bool,message:string,applied:string[],backup:?string,warnings:string[],schema:array<string,mixed>,steps:string[]}
     */
    public function upgrade(array $opts = []): array
    {
        $conn = $this->conn ?? Connection::instance();
        $mig = new Migrator(null, $conn);
        $steps = [];
        $r = ['ok' => false, 'message' => '', 'applied' => [], 'backup' => null, 'warnings' => [], 'schema' => [], 'steps' => &$steps];

        $pf = $mig->preflight();
        $r['warnings'] = $pf['warnings'];
        if ($pf['errors'] !== []) {
            $r['message'] = 'Upgrade not started: ' . implode(' ', $pf['errors']);
            return $r;
        }
        $st = $mig->status();
        if ($st['pending'] === []) {
            $r['ok'] = true;
            $r['message'] = 'Database is up to date (' . count($st['applied']) . ' migrations).';
            $r['schema'] = (new SchemaManifest($conn))->validate();
            return $r;
        }
        $steps[] = count($st['pending']) . ' pending: ' . implode(', ', $st['pending']);
        if (!empty($opts['pretend'])) {
            $r['ok'] = true;
            $r['message'] = 'Pretend mode: nothing was executed.';
            $r['steps'] = array_merge($steps, array_map(static fn ($n, $s) => "{$n}: " . count($s) . ' statement(s)', array_keys($mig->pretend()), $mig->pretend()));
            return $r;
        }

        $policy = BackupManager::policy();
        if (!empty($opts['no_backup'])) {
            $policy = 'off';
        }
        $hasData = $this->hasSchoolData($conn);
        if ($policy !== 'off' && $hasData) {
            try {
                $b = (new BackupManager($conn))->create('pre_migration', $opts['user_id'] ?? null);
                $r['backup'] = $b['filename'];
                $steps[] = sprintf('Verified backup %s (%d tables, %s rows)', $b['filename'], $b['tables'], number_format($b['rows']));
            } catch (\Throwable $e) {
                if ($policy === 'required') {
                    $r['message'] = 'Upgrade stopped, nothing was changed: the pre-upgrade backup failed (' . \App\Services\Setup\SetupService::redact(strtok($e->getMessage(), "\n") ?: 'unknown error')
                        . '). Fix this, or set BACKUP_BEFORE_MIGRATE=optional / use --no-backup if you hold your own verified backup.';
                    return $r;
                }
                $r['warnings'][] = 'Backup failed and was skipped (policy: optional).';
            }
        } elseif ($policy !== 'off') {
            $steps[] = 'Backup skipped: the database holds no school data yet.';
        }

        try {
            $r['applied'] = $mig->migrate();
        } catch (\Throwable $e) {
            $r['message'] = 'Migration failed: ' . \App\Services\Setup\SetupService::redact($e->getMessage())
                . ($r['backup'] ? ' The verified backup ' . $r['backup'] . ' was taken first; no data was removed.' : '')
                . ' Fix the cause and run the upgrade again; completed steps are skipped.';
            return $r;
        }
        $steps[] = 'Applied: ' . implode(', ', $r['applied']);
        if ($r['backup'] !== null && !empty($b) && (int) $b['id'] === 0) {
            // The backup was taken before the register table existed; record it now so it is listed and pruned.
            try {
                (new BackupManager($conn))->registerExisting($r['backup'], 'pre_migration', $opts['user_id'] ?? null);
                $steps[] = 'Pre-upgrade backup registered.';
            } catch (\Throwable) {
            }
        }

        $r['schema'] = (new SchemaManifest($conn))->validate();
        $steps[] = 'Schema check: ' . $r['schema']['message'];
        $r['ok'] = $r['schema']['ok'];
        $r['message'] = $r['ok'] ? count($r['applied']) . ' migration(s) applied; existing data preserved.'
            : count($r['applied']) . ' migration(s) applied, but the schema check found differences. Run: php bin/console db:schema:validate';
        return $r;
    }

    private function hasSchoolData(Connection $conn): bool
    {
        try {
            return (int) $conn->scalar('SELECT COUNT(*) FROM `users`') > 0;
        } catch (\Throwable) {
            return false;
        }
    }
}
