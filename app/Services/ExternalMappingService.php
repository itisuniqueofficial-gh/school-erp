<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\QueryBuilder;

/**
 * Maps internal ERP records to their external government identifiers so that
 * synchronisation is duplicate-safe: a given external ID always resolves to the
 * same internal record (and vice versa) per provider + entity type.
 *
 * Uniqueness is enforced both in code and by database UNIQUE constraints, so two
 * internal records can never claim the same external ID, and re-syncing never
 * creates duplicate mappings.
 */
final class ExternalMappingService
{
    /**
     * Idempotently link an internal record to an external ID.
     *
     * @return 'created'|'exists'|'updated'|'conflict'
     *   - created:  new mapping stored
     *   - exists:   identical mapping already present (no-op)
     *   - updated:  the internal record's external ID changed
     *   - conflict: that external ID already belongs to a DIFFERENT internal
     *               record — refused (no silent reassignment)
     */
    public function link(string $provider, string $entityType, int $entityId, string $externalId): string
    {
        $externalId = trim($externalId);
        if ($externalId === '' || $entityId <= 0) {
            return 'conflict';
        }
        $now = date('Y-m-d H:i:s');

        $byExternal = QueryBuilder::table('external_mappings')
            ->where('provider', $provider)->where('entity_type', $entityType)->where('external_id', $externalId)->first();
        if ($byExternal !== null) {
            return (int) $byExternal['entity_id'] === $entityId ? 'exists' : 'conflict';
        }

        $byEntity = QueryBuilder::table('external_mappings')
            ->where('provider', $provider)->where('entity_type', $entityType)->where('entity_id', $entityId)->first();
        if ($byEntity !== null) {
            QueryBuilder::table('external_mappings')->where('id', (int) $byEntity['id'])
                ->update(['external_id' => $externalId, 'updated_at' => $now]);
            return 'updated';
        }

        QueryBuilder::table('external_mappings')->insert([
            'provider' => $provider, 'entity_type' => $entityType, 'entity_id' => $entityId,
            'external_id' => $externalId, 'created_at' => $now, 'updated_at' => $now,
        ]);
        return 'created';
    }

    /** Resolve the internal record id for an external ID, or null. */
    public function internalId(string $provider, string $entityType, string $externalId): ?int
    {
        $row = QueryBuilder::table('external_mappings')
            ->where('provider', $provider)->where('entity_type', $entityType)->where('external_id', trim($externalId))
            ->first();
        return $row !== null ? (int) $row['entity_id'] : null;
    }

    /** Resolve the external ID for an internal record, or null. */
    public function externalId(string $provider, string $entityType, int $entityId): ?string
    {
        $row = QueryBuilder::table('external_mappings')
            ->where('provider', $provider)->where('entity_type', $entityType)->where('entity_id', $entityId)
            ->first();
        return $row !== null ? (string) $row['external_id'] : null;
    }

    /** Count of mappings for a provider (optionally by entity type). */
    public function count(string $provider, ?string $entityType = null): int
    {
        $q = QueryBuilder::table('external_mappings')->where('provider', $provider);
        if ($entityType !== null) {
            $q->where('entity_type', $entityType);
        }
        return (int) $q->count();
    }
}
