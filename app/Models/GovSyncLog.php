<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database\Model;
use Core\Database\QueryBuilder;

/**
 * Ledger of government-integration sync attempts (future adapter calls). Rows are
 * created pending and advanced to success/failed/retry by the integration layer.
 */
final class GovSyncLog extends Model
{
    protected string $table = 'government_sync_logs';

    protected array $fillable = [
        'provider', 'student_id', 'action', 'status', 'request_ref', 'message',
    ];

    /** @return array<int,array<string,mixed>> Recent sync attempts with student name. */
    public static function recent(int $limit = 50): array
    {
        return QueryBuilder::table('government_sync_logs')
            ->select(['government_sync_logs.*', 'students.first_name', 'students.last_name'])
            ->leftJoin('students', 'government_sync_logs.student_id', '=', 'students.id')
            ->orderBy('government_sync_logs.id', 'DESC')
            ->limit($limit)
            ->get();
    }
}
