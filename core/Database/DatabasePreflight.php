<?php

declare(strict_types=1);

namespace Core\Database;

/**
 * DatabasePreflight — the single source of truth for classifying database
 * connectivity into safe, user-facing states.
 *
 * It maps low-level driver errors (which may contain the host, username, or
 * "using password: YES") to a stable state + a generic, credential-free
 * message. Raw details go to the server log only, never to the user.
 *
 * States:
 *   ok                connection succeeded
 *   unreachable       server down / host or port wrong / network
 *   auth_failed       wrong username or password
 *   database_missing  server reachable + authenticated, but the DB is absent
 *   access_denied     authenticated, but no permission on the database
 *   unknown           any other failure
 */
final class DatabasePreflight
{
    public const OK               = 'ok';
    public const UNREACHABLE      = 'unreachable';
    public const AUTH_FAILED      = 'auth_failed';
    public const DATABASE_MISSING = 'database_missing';
    public const ACCESS_DENIED    = 'access_denied';
    public const UNKNOWN          = 'unknown';

    /**
     * Classify a connection Throwable into a safe [state, message] pair.
     * The message NEVER contains credentials, host, SQLSTATE, or driver text.
     *
     * @return array{0:string,1:string}
     */
    public static function classify(\Throwable $e): array
    {
        $code = self::mysqlCode($e);

        return match (true) {
            in_array($code, [2002, 2003, 2005, 2006, 2013], true) =>
                [self::UNREACHABLE, 'The database server could not be reached. Verify it is running and the configured host and port are correct.'],
            $code === 1045 =>
                [self::AUTH_FAILED, 'The database username or password is incorrect.'],
            $code === 1049 =>
                [self::DATABASE_MISSING, 'The configured database does not exist. Create it, or correct the database name.'],
            in_array($code, [1044, 1142, 1143], true) =>
                [self::ACCESS_DENIED, 'The database user does not have sufficient permissions on the configured database.'],
            default =>
                [self::UNKNOWN, 'The database is not available. Check the database service and the DB_* configuration.'],
        };
    }

    /**
     * Perform a live connectivity probe. Never throws; never leaks secrets.
     *
     * @return array{ok:bool,state:string,message:string}
     */
    public static function probe(): array
    {
        try {
            Connection::instance()->pdo()->query('SELECT 1');
            return ['ok' => true, 'state' => self::OK, 'message' => 'Database connection successful.'];
        } catch (\Throwable $e) {
            [$state, $message] = self::classify($e);
            $ref = substr(bin2hex(random_bytes(4)), 0, 8);
            // Detail (may contain host/user) goes to the log ONLY.
            @error_log(sprintf('[db-preflight] ref=%s state=%s detail=%s', $ref, $state, (($e->getPrevious() ?? $e)->getMessage())));
            return ['ok' => false, 'state' => $state, 'message' => $message];
        }
    }

    /** Human label for a state (for CLI / health output). */
    public static function label(string $state): string
    {
        return match ($state) {
            self::OK               => 'OK',
            self::UNREACHABLE      => 'SERVER UNREACHABLE',
            self::AUTH_FAILED      => 'AUTHENTICATION FAILED',
            self::DATABASE_MISSING => 'DATABASE MISSING',
            self::ACCESS_DENIED    => 'PERMISSION DENIED',
            default                => 'UNAVAILABLE',
        };
    }

    /**
     * Extract the MySQL numeric error code. mysqlnd embeds it as "[1045]" in the
     * message; fall back to the exception code when it is a plain integer.
     */
    private static function mysqlCode(\Throwable $e): int
    {
        foreach ([$e, $e->getPrevious()] as $x) {
            if ($x === null) {
                continue;
            }
            if (preg_match('/\[(\d{3,4})\]/', $x->getMessage(), $m)) {
                return (int) $m[1];
            }
            $c = $x->getCode();
            if (is_int($c) && $c > 0) {
                return $c;
            }
            if (is_string($c) && ctype_digit($c) && $c !== '') {
                return (int) $c;
            }
        }
        return 0;
    }
}
