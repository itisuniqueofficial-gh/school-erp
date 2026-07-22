<?php

declare(strict_types=1);

namespace App\Models;

use Core\Database\Model;
use Core\Database\QueryBuilder;

/**
 * Read model for the immutable audit trail (rows are written by AuditLogger and
 * never edited/deleted from the UI). Provides filtering for the admin viewer.
 */
final class AuditLog extends Model
{
    protected string $table = 'audit_logs';

    /**
     * @param array{action?:string,entity_type?:string,q?:string,date_from?:string,date_to?:string} $filters
     */
    public static function filtered(array $filters = []): QueryBuilder
    {
        $q = QueryBuilder::table('audit_logs')
            ->select([
                'audit_logs.id', 'audit_logs.action', 'audit_logs.entity_type', 'audit_logs.entity_id',
                'audit_logs.description', 'audit_logs.ip_address', 'audit_logs.created_at',
                'users.name AS user_name', 'users.uid AS user_uid',
            ])
            ->leftJoin('users', 'audit_logs.user_id', '=', 'users.id');

        if (!empty($filters['action'])) {
            $q->where('audit_logs.action', $filters['action']);
        }
        if (!empty($filters['entity_type'])) {
            $q->where('audit_logs.entity_type', $filters['entity_type']);
        }
        if (!empty($filters['q'])) {
            $t = '%' . $filters['q'] . '%';
            $q->whereRaw('(`users`.`name` LIKE ? OR `users`.`uid` LIKE ? OR `audit_logs`.`description` LIKE ?)', [$t, $t, $t]);
        }
        if (!empty($filters['date_from'])) {
            $q->whereRaw('`audit_logs`.`created_at` >= ?', [$filters['date_from'] . ' 00:00:00']);
        }
        if (!empty($filters['date_to'])) {
            $q->whereRaw('`audit_logs`.`created_at` <= ?', [$filters['date_to'] . ' 23:59:59']);
        }

        return $q->orderBy('audit_logs.id', 'DESC');
    }

    /** @return array<int,string> distinct action names for the filter dropdown */
    public static function distinctActions(): array
    {
        return array_column(
            QueryBuilder::table('audit_logs')->select(['action'])->groupBy('action')->orderBy('action')->get(),
            'action'
        );
    }

    /** @return array<int,string> distinct entity types for the filter dropdown */
    public static function distinctEntities(): array
    {
        $rows = QueryBuilder::table('audit_logs')->select(['entity_type'])->groupBy('entity_type')->orderBy('entity_type')->get();
        return array_values(array_filter(array_column($rows, 'entity_type')));
    }
}
