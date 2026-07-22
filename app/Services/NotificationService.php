<?php

declare(strict_types=1);

namespace App\Services;

use Core\Database\QueryBuilder;

/**
 * In-app notifications backed by the `notifications` table. Also the single
 * entry point for fan-out helpers (notify a whole role) used by event hooks
 * such as marks publication and document verification.
 */
final class NotificationService
{
    /** Create a single notification for a user. Returns the row id (0 on failure). */
    public function notify(int $userId, string $type, string $title, ?string $body = null, ?string $link = null): int
    {
        if ($userId <= 0) {
            return 0;
        }
        try {
            return QueryBuilder::table('notifications')->insert([
                'user_id' => $userId, 'type' => $type,
                'title' => mb_substr($title, 0, 200),
                'body'  => $body !== null ? mb_substr($body, 0, 500) : null,
                'link'  => $link, 'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Notify every active user that has one of the given roles. */
    public function notifyRoles(array $roleSlugs, string $type, string $title, ?string $body = null, ?string $link = null): int
    {
        if ($roleSlugs === []) {
            return 0;
        }
        try {
            $rows = QueryBuilder::table('user_roles')
                ->selectRaw('DISTINCT `user_roles`.`user_id` AS uid')
                ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                ->whereIn('roles.slug', $roleSlugs)->get();
        } catch (\Throwable) {
            return 0;
        }
        $n = 0;
        foreach ($rows as $r) {
            $n += $this->notify((int) $r['uid'], $type, $title, $body, $link) > 0 ? 1 : 0;
        }
        return $n;
    }

    /** @return array<int,array<string,mixed>> Recent notifications for a user. */
    public function recent(int $userId, int $limit = 10): array
    {
        try {
            return QueryBuilder::table('notifications')
                ->where('user_id', $userId)->orderBy('id', 'DESC')->limit($limit)->get();
        } catch (\Throwable) {
            return [];
        }
    }

    public function unreadCount(int $userId): int
    {
        try {
            return QueryBuilder::table('notifications')->where('user_id', $userId)->whereNull('read_at')->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function markAllRead(int $userId): void
    {
        try {
            QueryBuilder::table('notifications')->where('user_id', $userId)->whereNull('read_at')
                ->update(['read_at' => date('Y-m-d H:i:s')]);
        } catch (\Throwable) {
        }
    }

    public function markRead(int $id, int $userId): void
    {
        try {
            QueryBuilder::table('notifications')->where('id', $id)->where('user_id', $userId)
                ->update(['read_at' => date('Y-m-d H:i:s')]);
        } catch (\Throwable) {
        }
    }
}
