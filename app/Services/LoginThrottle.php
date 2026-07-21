<?php

declare(strict_types=1);

namespace App\Services;

use Core\Config\Config;
use Core\Database\QueryBuilder;

/**
 * Brute-force protection: records login attempts and enforces lockout after
 * too many failures from the same identifier/IP within a time window.
 */
final class LoginThrottle
{
    private function maxAttempts(): int
    {
        return (int) Config::get('security.login_max_attempts', 5);
    }

    private function lockoutSeconds(): int
    {
        return (int) Config::get('security.login_lockout_time', 900);
    }

    /**
     * Number of failed attempts for identifier+ip within the lockout window.
     */
    public function recentFailures(string $identifier, string $ip): int
    {
        $since = date('Y-m-d H:i:s', time() - $this->lockoutSeconds());
        try {
            return (int) (QueryBuilder::table('login_attempts')
                ->where('successful', 0)
                ->where('attempted_at', '>=', $since)
                ->whereRaw('(`identifier` = ? OR `ip_address` = ?)', [$identifier, $ip])
                ->count());
        } catch (\Throwable) {
            return 0; // fail open if table missing (pre-migration)
        }
    }

    public function tooManyAttempts(string $identifier, string $ip): bool
    {
        return $this->recentFailures($identifier, $ip) >= $this->maxAttempts();
    }

    /**
     * Seconds until the lockout expires (approximate, based on last attempt).
     */
    public function availableIn(string $identifier, string $ip): int
    {
        try {
            $last = QueryBuilder::table('login_attempts')
                ->where('successful', 0)
                ->whereRaw('(`identifier` = ? OR `ip_address` = ?)', [$identifier, $ip])
                ->orderBy('attempted_at', 'DESC')
                ->value('attempted_at');
            if ($last === null) {
                return 0;
            }
            $elapsed = time() - strtotime((string) $last);
            return max(0, $this->lockoutSeconds() - $elapsed);
        } catch (\Throwable) {
            return 0;
        }
    }

    public function record(string $identifier, string $ip, bool $successful, string $userAgent = ''): void
    {
        try {
            QueryBuilder::table('login_attempts')->insert([
                'identifier'   => mb_substr($identifier, 0, 190),
                'ip_address'   => $ip,
                'successful'   => $successful ? 1 : 0,
                'user_agent'   => mb_substr($userAgent, 0, 255),
                'attempted_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // never block login flow on logging failure
        }
    }

    /**
     * Clear failed attempts after a successful login.
     */
    public function clear(string $identifier, string $ip): void
    {
        try {
            QueryBuilder::table('login_attempts')
                ->where('successful', 0)
                ->whereRaw('(`identifier` = ? OR `ip_address` = ?)', [$identifier, $ip])
                ->delete();
        } catch (\Throwable) {
        }
    }
}
