<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\QueryBuilder;

/**
 * Records security & activity events into the audit_logs table.
 */
final class AuditLogger
{
    /**
     * Record an audit entry for the current (or given) user.
     */
    public static function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $description = null,
        ?int $userId = null
    ): void {
        if ($userId === null) {
            try {
                $userId = auth()->id();
            } catch (\Throwable) {
                $userId = null;
            }
        }

        try {
            $ip = null;
            $agent = null;
            try {
                $ip = request()->ip();
                $agent = substr(request()->userAgent(), 0, 255);
            } catch (\Throwable) {
                // CLI / installer context without an HTTP request.
            }

            QueryBuilder::table('audit_logs')->insert([
                'user_id'     => $userId,
                'action'      => $action,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'description' => $description,
                'ip_address'  => $ip,
                'user_agent'  => $agent,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // Auditing must never break the request; log to file instead.
            try {
                logger()->warning('Audit log failed: ' . $e->getMessage());
            } catch (\Throwable) {
                @error_log('[audit] failed: ' . $e->getMessage());
            }
        }
    }
}
