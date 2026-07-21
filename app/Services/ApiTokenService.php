<?php

declare(strict_types=1);

namespace App\Services;

use Core\Config\Config;
use Core\Database\QueryBuilder;

/**
 * Issues and resolves API bearer tokens (stored hashed) for the REST API.
 */
final class ApiTokenService
{
    /**
     * Create a new API token for a user. Returns the plaintext token
     * (shown once); only its hash is persisted.
     */
    public function create(int $userId, string $name = 'api'): string
    {
        $plain = bin2hex(random_bytes(32));
        $ttl = (int) Config::get('auth.api.ttl', 3600);

        QueryBuilder::table('api_tokens')->insert([
            'user_id'    => $userId,
            'name'       => $name,
            'token'      => hash('sha256', $plain),
            'expires_at' => date('Y-m-d H:i:s', time() + $ttl),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $plain;
    }

    /**
     * Resolve a plaintext bearer token to a user id, or null if invalid/expired.
     */
    public function resolve(string $plainToken): ?int
    {
        $hash = hash('sha256', $plainToken);
        $record = QueryBuilder::table('api_tokens')->where('token', $hash)->first();

        if ($record === null) {
            return null;
        }

        if ($record['expires_at'] !== null && strtotime((string) $record['expires_at']) < time()) {
            return null;
        }

        // Best-effort last-used timestamp.
        try {
            QueryBuilder::table('api_tokens')->where('id', $record['id'])->update([
                'last_used_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // ignore
        }

        return (int) $record['user_id'];
    }

    /**
     * Revoke a specific token (by plaintext) — used on logout.
     */
    public function revoke(string $plainToken): void
    {
        QueryBuilder::table('api_tokens')->where('token', hash('sha256', $plainToken))->delete();
    }

    /**
     * Revoke all tokens for a user.
     */
    public function revokeAll(int $userId): void
    {
        QueryBuilder::table('api_tokens')->where('user_id', $userId)->delete();
    }
}
