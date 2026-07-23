<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\QueryBuilder;

/**
 * Tracks government synchronisation jobs and provides the duplicate-safe import
 * engine used by every provider adapter. Each batch run is recorded with
 * created / updated / skipped / failed counts, so the sync dashboard always
 * reflects exactly what happened, and re-running a batch never duplicates data.
 */
final class SyncJobService
{
    public function __construct(private ExternalMappingService $mappings = new ExternalMappingService())
    {
    }

    /** Begin a job; returns its id. */
    public function start(string $provider, string $operation, string $direction = 'pull'): int
    {
        $now = date('Y-m-d H:i:s');
        return (int) QueryBuilder::table('sync_jobs')->insert([
            'provider' => $provider, 'operation' => $operation,
            'direction' => in_array($direction, ['pull', 'push'], true) ? $direction : 'pull',
            'status' => 'running', 'run_by' => auth()->id(),
            'started_at' => $now, 'created_at' => $now,
        ]);
    }

    /** @param array{total?:int,created?:int,updated?:int,skipped?:int,failed?:int} $counts */
    public function finish(int $jobId, array $counts, string $status, ?string $message = null): void
    {
        QueryBuilder::table('sync_jobs')->where('id', $jobId)->update([
            'status' => $status,
            'total' => (int) ($counts['total'] ?? 0),
            'created_count' => (int) ($counts['created'] ?? 0),
            'updated_count' => (int) ($counts['updated'] ?? 0),
            'skipped_count' => (int) ($counts['skipped'] ?? 0),
            'failed_count' => (int) ($counts['failed'] ?? 0),
            'message' => $message !== null ? substr($message, 0, 500) : null,
            'finished_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Duplicate-safe batch import.
     *
     * For each record: resolve its external ID; if that external ID is already
     * mapped, UPDATE the existing internal record; otherwise CREATE a new record
     * and link the mapping. Re-running the same batch therefore updates in place
     * and never creates duplicates. All counts are recorded on a sync job.
     *
     * @param array<int,array<string,mixed>> $records
     * @param callable(array<string,mixed>):string           $resolveExternalId  record -> external id
     * @param callable(array<string,mixed>,?int):?int         $upsert  (record, internalIdOrNull) -> internal id
     * @return array{job_id:int,total:int,created:int,updated:int,skipped:int,failed:int,status:string}
     */
    public function importBatch(string $provider, string $entityType, string $operation, array $records, callable $resolveExternalId, callable $upsert): array
    {
        $jobId = $this->start($provider, $operation, 'pull');
        $created = 0; $updated = 0; $skipped = 0; $failed = 0;

        foreach ($records as $record) {
            try {
                $ext = trim((string) $resolveExternalId($record));
                if ($ext === '') {
                    $skipped++;
                    continue;
                }
                $internal = $this->mappings->internalId($provider, $entityType, $ext);
                if ($internal !== null) {
                    $upsert($record, $internal);
                    $updated++;
                } else {
                    $newId = $upsert($record, null);
                    if ($newId !== null && $newId > 0) {
                        $this->mappings->link($provider, $entityType, (int) $newId, $ext);
                        $created++;
                    } else {
                        $skipped++;
                    }
                }
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        $total = count($records);
        $status = $failed > 0 ? (($created + $updated) > 0 ? 'partial' : 'failed') : 'completed';
        $counts = ['total' => $total, 'created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'failed' => $failed];
        $this->finish($jobId, $counts, $status);

        AuditLogger::log('sync.batch', 'sync_job', $jobId, "{$provider}/{$operation}: +{$created} ~{$updated} skip{$skipped} fail{$failed}");
        return ['job_id' => $jobId, 'status' => $status] + $counts;
    }

    /** @return array<int,array<string,mixed>> */
    public function recent(int $limit = 50, ?string $provider = null): array
    {
        $q = QueryBuilder::table('sync_jobs')->orderBy('id', 'DESC')->limit($limit);
        if ($provider !== null) {
            $q->where('provider', $provider);
        }
        return $q->get();
    }

    /** Dashboard totals across all sync jobs. */
    public function dashboard(): array
    {
        $rows = QueryBuilder::table('sync_jobs')->selectRaw('`status`, COUNT(*) AS c')->groupBy('status')->get();
        $byStatus = [];
        foreach ($rows as $r) {
            $byStatus[(string) $r['status']] = (int) $r['c'];
        }
        return [
            'total_jobs' => array_sum($byStatus),
            'completed'  => $byStatus['completed'] ?? 0,
            'partial'    => $byStatus['partial'] ?? 0,
            'failed'     => $byStatus['failed'] ?? 0,
            'running'    => $byStatus['running'] ?? 0,
            'records_created' => (int) QueryBuilder::table('sync_jobs')->selectRaw('COALESCE(SUM(`created_count`),0) AS s')->value('s'),
            'records_updated' => (int) QueryBuilder::table('sync_jobs')->selectRaw('COALESCE(SUM(`updated_count`),0) AS s')->value('s'),
        ];
    }
}
