<?php

declare(strict_types=1);

namespace App\Support;

use Core\Database\Connection;

/**
 * Startup database-integrity guard (HTTP only).
 *
 * Data-safety guarantee: the application must NEVER appear to work while
 * silently serving an empty or unavailable database (a state that invites
 * accidental "re-setup" over real data). Before dispatching a request we probe
 * a core table; if the database is unreachable or has not been migrated we stop
 * and show a clear critical error instead of a half-working empty UI.
 *
 * The error page is fully self-contained (no DB queries, no view layout) so it
 * can render even when the database — and therefore branding/settings — is down.
 */
final class StartupGuard
{
    /**
     * Classify the result of a database probe.
     * Returns null when healthy, or a reason: 'unmigrated' | 'connect'.
     *
     * Split out from check() so the classification logic is unit-testable
     * without needing a genuinely down database.
     */
    public static function evaluate(callable $probe): ?string
    {
        try {
            $probe();
            return null;
        } catch (\Throwable $e) {
            $m = strtolower($e->getMessage());
            $missingTable = str_contains($m, "doesn't exist")
                || str_contains($m, 'does not exist')
                || str_contains($m, 'no such table')
                || str_contains($m, 'unknown table')
                || str_contains($m, '1146');
            return $missingTable ? 'unmigrated' : 'connect';
        }
    }

    /**
     * Probe the live connection. Returns null when the DB is reachable and the
     * schema is present, otherwise the failure reason.
     */
    public static function check(): ?string
    {
        return self::evaluate(static function (): void {
            Connection::instance()->selectOne('SELECT 1 FROM `users` LIMIT 1');
        });
    }

    /**
     * Emit a standalone HTTP 503 critical-error page and stop. Never leaks
     * credentials or stack traces to the visitor; logs a reference id privately.
     */
    public static function respond(string $reason): void
    {
        $ref = substr(bin2hex(random_bytes(6)), 0, 12);
        @error_log(sprintf('[startup-guard] ref=%s reason=%s', $ref, $reason));

        [$title, $what, $how] = match ($reason) {
            'unmigrated' => [
                'Database not initialised',
                'The application connected to the database, but the required tables are missing.',
                'Run "php bin/console migrate" (additive, non-destructive) to create the schema, then reload.',
            ],
            default => (static function (): array {
                $p = \Core\Database\DatabasePreflight::probe();
                return [
                    'Database unavailable',
                    $p['message'],
                    'Check the database service and the DB_* configuration, then reload. Existing data is not affected.',
                ];
            })(),
        };

        if (!headers_sent()) {
            http_response_code(503);
            header('Content-Type: text/html; charset=UTF-8');
            header('Retry-After: 30');
        }

        $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $w = htmlspecialchars($what, ENT_QUOTES, 'UTF-8');
        $h = htmlspecialchars($how, ENT_QUOTES, 'UTF-8');
        echo <<<HTML
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$t}</title>
<style>
  body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
       font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f8fafc;color:#0f172a}
  .card{max-width:32rem;margin:1rem;padding:2rem;background:#fff;border:1px solid #e2e8f0;
        border-radius:16px;box-shadow:0 10px 30px rgba(2,6,23,.06)}
  .icon{width:48px;height:48px;border-radius:12px;background:#fef2f2;color:#dc2626;
        display:flex;align-items:center;justify-content:center;font-size:24px;margin-bottom:1rem}
  h1{font-size:1.25rem;margin:0 0 .5rem} p{line-height:1.6;margin:.25rem 0;color:#334155}
  .how{margin-top:1rem;padding:.75rem 1rem;background:#f1f5f9;border-radius:10px;font-size:.9rem}
  .ref{margin-top:1.25rem;font-size:.8rem;color:#64748b}
  code{background:#f1f5f9;padding:.1rem .35rem;border-radius:6px}
</style></head>
<body><div class="card" role="alert">
  <div class="icon">&#9888;</div>
  <h1>{$t}</h1>
  <p>{$w}</p>
  <div class="how"><strong>How to fix:</strong> {$h}</div>
  <p class="ref">Reference: <code>{$ref}</code></p>
</div></body></html>
HTML;
    }
}
