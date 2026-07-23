<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\Connection;
use Core\Database\QueryBuilder;
use Database\Migrator;

/**
 * Migration / portability integrity tool.
 *
 * Captures a snapshot of record counts across every important ERP table (plus
 * environment identity and migration version) so that a server/database
 * migration can be verified: take a snapshot on the source, take one on the
 * destination, and compare. Any unexpected DROP in counts flags the migration
 * as unsafe. Read-only — never modifies data.
 */
final class MigrationSnapshotService
{
    /** Tables whose record counts are tracked for zero-data-loss verification. */
    public const TABLES = [
        'users', 'students', 'guardians', 'staff', 'academic_years', 'classes', 'sections',
        'subjects', 'class_subjects', 'teacher_assignments', 'student_enrollments',
        'student_attendance', 'exams', 'exam_schedules', 'exam_marks', 'report_cards',
        'report_card_versions', 'certificates', 'certificate_requests', 'hall_tickets',
        'student_documents', 'fee_invoices', 'payment_transactions', 'audit_logs',
        'external_mappings', 'government_sync_logs',
    ];

    /** @return array<string,mixed> */
    public function snapshot(): array
    {
        $conn = (string) config('database.default');
        $counts = [];
        foreach (self::TABLES as $t) {
            try {
                $counts[$t] = (int) QueryBuilder::table($t)->count();
            } catch (\Throwable) {
                $counts[$t] = null; // table absent in this schema
            }
        }
        $applied = [];
        try {
            $applied = (new Migrator())->status()['applied'] ?? [];
        } catch (\Throwable) {
        }

        $payload = [
            'generated_at'       => date('c'),
            'app_version'        => (string) config('app.version'),
            'environment'        => (string) config('app.env'),
            'driver'             => (string) config("database.connections.{$conn}.driver"),
            'database'           => (string) config("database.connections.{$conn}.database"),
            'migrations_applied' => count($applied),
            'total_records'      => array_sum(array_map(static fn ($v) => (int) $v, $counts)),
            'counts'             => $counts,
        ];
        $payload['checksum'] = hash('sha256', json_encode($payload['counts'], JSON_UNESCAPED_UNICODE) . '|' . $payload['migrations_applied']);
        return $payload;
    }

    /** Persist a snapshot to storage/backups/snapshots and return its path. */
    public function save(): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/backups/snapshots';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/snapshot_' . date('Ymd_His') . '.json';
        file_put_contents($file, json_encode($this->snapshot(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $file;
    }

    /**
     * Compare a previous snapshot (source) against the current DB (destination).
     *
     * @param array<string,mixed> $before
     * @return array{safe:bool,dropped:array<int,array{table:string,before:int,after:int}>,added:array<int,array{table:string,before:int,after:int}>,missing:array<int,string>}
     */
    public function compareToCurrent(array $before): array
    {
        $after = $this->snapshot();
        $dropped = [];
        $added = [];
        $missing = [];
        foreach (($before['counts'] ?? []) as $table => $bc) {
            if ($bc === null) {
                continue;
            }
            $ac = $after['counts'][$table] ?? null;
            if ($ac === null) {
                $missing[] = (string) $table; // table disappeared entirely
                continue;
            }
            if ($ac < $bc) {
                $dropped[] = ['table' => (string) $table, 'before' => (int) $bc, 'after' => (int) $ac];
            } elseif ($ac > $bc) {
                $added[] = ['table' => (string) $table, 'before' => (int) $bc, 'after' => (int) $ac];
            }
        }
        return [
            'safe'    => $dropped === [] && $missing === [],
            'dropped' => $dropped,
            'added'   => $added,
            'missing' => $missing,
        ];
    }

    /** Load a saved snapshot JSON file. @return array<string,mixed>|null */
    public function load(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : null;
    }
}
