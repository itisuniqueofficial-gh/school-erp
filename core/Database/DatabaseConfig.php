<?php

declare(strict_types=1);

namespace Core\Database;

use InvalidArgumentException;
use PDO;

/**
 * Immutable, validated database connection settings. One class for every way
 * of describing a connection: manual values, environment variables, a
 * connection URL (mysql://, mariadb://, postgres://) or an array read from a
 * config file. Builds the PDO DSN/options and a credential-free description
 * for logs and screens. Never returns the password except through
 * password() (used only to open a PDO connection or to write the .env file).
 *
 * Engines: mysql, mariadb (both use pdo_mysql), pgsql (pdo_pgsql).
 */
final class DatabaseConfig
{
    public const ENGINES = ['mysql', 'mariadb', 'pgsql'];

    private function __construct(
        public readonly string $engine,
        public readonly string $host,
        public readonly int $port,
        public readonly string $database,
        public readonly string $username,
        private readonly string $password,
        public readonly ?string $socket,
        public readonly string $charset,
        public readonly ?string $collation,
        public readonly ?string $schema,
        public readonly int $timeout,
        public readonly string $sslMode,      // disable | prefer | require | verify-ca | verify-full
        public readonly ?string $sslCa,
        public readonly ?string $sslCert,
        public readonly ?string $sslKey,
    ) {
    }

    // ------------------------------------------------------------------ factories

    /**
     * @param array<string,mixed> $v keys: engine/driver, host, port, database, username, password,
     *        socket, charset, collation, schema, timeout, ssl_mode, ssl_ca, ssl_cert, ssl_key
     */
    public static function fromArray(array $v): self
    {
        $engine = self::normalizeEngine((string) ($v['engine'] ?? $v['driver'] ?? 'mysql'));
        $pg = $engine === 'pgsql';
        $host = trim((string) ($v['host'] ?? '127.0.0.1'));
        $port = (int) ($v['port'] ?? 0) ?: ($pg ? 5432 : 3306);
        $database = trim((string) ($v['database'] ?? ''));
        $socket = isset($v['socket']) && trim((string) $v['socket']) !== '' ? trim((string) $v['socket']) : null;
        $timeout = (int) ($v['timeout'] ?? 10);
        $ssl = strtolower(trim((string) ($v['ssl_mode'] ?? $v['sslmode'] ?? 'disable')));
        if (in_array($ssl, ['', '0', 'false', 'off', 'no'], true)) {
            $ssl = 'disable';
        } elseif (in_array($ssl, ['1', 'true', 'on', 'yes', 'required'], true)) {
            $ssl = 'require';
        }
        $schema = isset($v['schema']) && trim((string) $v['schema']) !== '' ? trim((string) $v['schema']) : ($pg ? 'public' : null);

        $cfg = new self(
            $engine, $host, $port, $database,
            (string) ($v['username'] ?? ($pg ? 'postgres' : 'root')),
            (string) ($v['password'] ?? ''),
            $socket,
            (string) ($v['charset'] ?? ($pg ? 'UTF8' : 'utf8mb4')),
            $pg ? null : (string) ($v['collation'] ?? 'utf8mb4_unicode_ci'),
            $schema,
            max(1, min(120, $timeout)),
            $ssl,
            self::optPath($v['ssl_ca'] ?? null), self::optPath($v['ssl_cert'] ?? null), self::optPath($v['ssl_key'] ?? null)
        );
        $cfg->validate();
        return $cfg;
    }

    /** Build from a connection URL, e.g. postgres://user:p%40ss@db.example.com:5432/school_erp?sslmode=require */
    public static function fromUrl(string $url): self
    {
        $url = trim($url);
        if ($url === '' || !preg_match('#^([a-z][a-z0-9+.-]*)://#i', $url, $m)) {
            throw new InvalidArgumentException('Connection URL must look like engine://user:password@host:port/database');
        }
        $parts = parse_url($url);
        if ($parts === false) {
            throw new InvalidArgumentException('Connection URL could not be parsed. Percent-encode special characters in the password (for example @ as %40).');
        }
        $engine = self::normalizeEngine((string) ($parts['scheme'] ?? ''));
        parse_str((string) ($parts['query'] ?? ''), $q);
        $arr = [
            'engine'   => $engine,
            'host'     => isset($parts['host']) ? rawurldecode($parts['host']) : '127.0.0.1',
            'port'     => $parts['port'] ?? 0,
            'database' => isset($parts['path']) ? rawurldecode(ltrim($parts['path'], '/')) : '',
            'username' => isset($parts['user']) ? rawurldecode($parts['user']) : '',
            'password' => isset($parts['pass']) ? rawurldecode($parts['pass']) : '',
        ];
        foreach (['socket', 'charset', 'collation', 'schema', 'timeout', 'sslmode', 'ssl_mode', 'ssl_ca', 'ssl_cert', 'ssl_key'] as $k) {
            if (isset($q[$k]) && is_string($q[$k])) {
                $arr[$k] = $q[$k];
            }
        }
        return self::fromArray($arr);
    }

    /**
     * Build from environment-style values (DB_CONNECTION, DB_HOST, ...). A
     * DATABASE_URL, when present, takes precedence.
     *
     * @param callable(string,mixed=):mixed $get
     */
    public static function fromEnv(callable $get): self
    {
        $url = (string) ($get('DATABASE_URL', '') ?? '');
        if ($url !== '') {
            return self::fromUrl($url);
        }
        $engine = (string) ($get('DB_CONNECTION', $get('DB_DRIVER', 'mysql')));
        return self::fromArray([
            'engine'   => $engine,
            'host'     => $get('DB_HOST', '127.0.0.1'),
            'port'     => $get('DB_PORT', 0),
            'database' => $get('DB_DATABASE', ''),
            'username' => $get('DB_USERNAME', ''),
            'password' => $get('DB_PASSWORD', ''),
            'socket'   => $get('DB_SOCKET', ''),
            'charset'  => $get('DB_CHARSET', '') ?: null,
            'collation' => $get('DB_COLLATION', '') ?: null,
            'schema'   => $get('DB_SCHEMA', ''),
            'timeout'  => $get('DB_TIMEOUT', 10),
            'ssl_mode' => self::sslFromEnv($get),
            'ssl_ca'   => $get('DB_SSL_CA', ''),
            'ssl_cert' => $get('DB_SSL_CERT', ''),
            'ssl_key'  => $get('DB_SSL_KEY', ''),
        ]);
    }

    /**
     * DB_SSL_MODE wins. The older DB_SSL=true flag means "encrypt and verify the
     * server certificate" unless DB_SSL_VERIFY=false (then encrypt only).
     *
     * @param callable(string,mixed=):mixed $get
     */
    private static function sslFromEnv(callable $get): string
    {
        $mode = trim((string) ($get('DB_SSL_MODE', '') ?? ''));
        if ($mode !== '') {
            return $mode;
        }
        $legacy = strtolower(trim((string) ($get('DB_SSL', '') ?? '')));
        if (!in_array($legacy, ['1', 'true', 'on', 'yes', 'require', 'required'], true)) {
            return 'disable';
        }
        $verify = strtolower(trim((string) ($get('DB_SSL_VERIFY', 'true') ?? 'true')));
        return in_array($verify, ['0', 'false', 'off', 'no'], true) ? 'require' : 'verify-ca';
    }

    // ------------------------------------------------------------------ accessors

    public static function normalizeEngine(string $e): string
    {
        return match (strtolower(trim($e))) {
            'mysql' => 'mysql',
            'mariadb', 'maria' => 'mariadb',
            'pgsql', 'postgres', 'postgresql', 'pg' => 'pgsql',
            default => throw new InvalidArgumentException("Unsupported database engine '{$e}'. Supported: mysql, mariadb, pgsql (PostgreSQL)."),
        };
    }

    /** PDO driver name: mysql for MySQL and MariaDB, pgsql for PostgreSQL. */
    public function pdoDriver(): string
    {
        return $this->engine === 'pgsql' ? 'pgsql' : 'mysql';
    }

    public function isPostgres(): bool
    {
        return $this->engine === 'pgsql';
    }

    public function password(): string
    {
        return $this->password;
    }

    /** Whether the PHP extension for this engine is loaded. */
    public function driverAvailable(): bool
    {
        return extension_loaded($this->pdoDriver() === 'pgsql' ? 'pdo_pgsql' : 'pdo_mysql');
    }

    public function dsn(bool $withDatabase = true): string
    {
        if ($this->pdoDriver() === 'mysql') {
            $dsn = 'mysql:';
            $dsn .= $this->socket !== null ? 'unix_socket=' . $this->socket : 'host=' . $this->host . ';port=' . $this->port;
            if ($withDatabase && $this->database !== '') {
                $dsn .= ';dbname=' . $this->database;
            }
            return $dsn . ';charset=' . $this->charset;
        }
        $dsn = 'pgsql:host=' . ($this->socket ?? $this->host) . ';port=' . $this->port;
        $dsn .= ';dbname=' . ($withDatabase && $this->database !== '' ? $this->database : 'postgres');
        $dsn .= ';connect_timeout=' . $this->timeout;
        if ($this->sslMode !== 'disable') {
            $dsn .= ';sslmode=' . $this->sslMode;
            foreach (['sslrootcert' => $this->sslCa, 'sslcert' => $this->sslCert, 'sslkey' => $this->sslKey] as $k => $p) {
                if ($p !== null) {
                    $dsn .= ';' . $k . '=' . $p;
                }
            }
        }
        return $dsn;
    }

    /** @return array<int,mixed> */
    public function pdoOptions(): array
    {
        $o = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
            PDO::ATTR_PERSISTENT         => false,
            PDO::ATTR_TIMEOUT            => $this->timeout,
        ];
        if ($this->pdoDriver() === 'mysql' && $this->sslMode !== 'disable') {
            if ($this->sslCa !== null && defined('PDO::MYSQL_ATTR_SSL_CA')) {
                $o[PDO::MYSQL_ATTR_SSL_CA] = $this->sslCa;
            }
            if ($this->sslCert !== null && defined('PDO::MYSQL_ATTR_SSL_CERT')) {
                $o[PDO::MYSQL_ATTR_SSL_CERT] = $this->sslCert;
            }
            if ($this->sslKey !== null && defined('PDO::MYSQL_ATTR_SSL_KEY')) {
                $o[PDO::MYSQL_ATTR_SSL_KEY] = $this->sslKey;
            }
            if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                $o[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = in_array($this->sslMode, ['verify-ca', 'verify-full'], true);
            }
        }
        return $o;
    }

    /** Open a PDO connection (throws PDOException; callers classify it, see DatabasePreflight). */
    public function connect(bool $withDatabase = true): PDO
    {
        if (!$this->driverAvailable()) {
            throw new \RuntimeException('PHP extension pdo_' . $this->pdoDriver() . ' is not installed or enabled.');
        }
        $pdo = new PDO($this->dsn($withDatabase), $this->username, $this->password, $this->pdoOptions());
        if ($this->isPostgres()) {
            $pdo->exec("SET client_encoding TO 'UTF8'");
            if ($this->schema !== null && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $this->schema) === 1) {
                $pdo->exec('SET search_path TO "' . $this->schema . '"');
            }
        }
        return $pdo;
    }

    /**
     * Environment-file representation (for the setup writer). Keys with empty
     * values are omitted so a generated file stays minimal.
     *
     * @return array<string,string>
     */
    public function toEnv(): array
    {
        $e = [
            'DB_CONNECTION' => $this->engine,
            'DB_HOST'       => $this->host,
            'DB_PORT'       => (string) $this->port,
            'DB_DATABASE'   => $this->database,
            'DB_USERNAME'   => $this->username,
            'DB_PASSWORD'   => $this->password,
            'DB_CHARSET'    => $this->charset,
            'DB_TIMEOUT'    => (string) $this->timeout,
            'DB_SSL_MODE'   => $this->sslMode,
        ];
        foreach (['DB_SOCKET' => $this->socket, 'DB_COLLATION' => $this->collation, 'DB_SCHEMA' => $this->isPostgres() ? $this->schema : null,
                  'DB_SSL_CA' => $this->sslCa, 'DB_SSL_CERT' => $this->sslCert, 'DB_SSL_KEY' => $this->sslKey] as $k => $v) {
            if ($v !== null && $v !== '') {
                $e[$k] = $v;
            }
        }
        return $e;
    }

    /** @return array<string,mixed> Safe to log or display: no password. */
    public function describe(): array
    {
        return [
            'engine' => $this->engine, 'host' => $this->socket !== null ? 'socket:' . $this->socket : $this->host,
            'port' => $this->port, 'database' => $this->database, 'username' => $this->username,
            'password' => $this->password !== '' ? '********' : '(empty)',
            'charset' => $this->charset, 'schema' => $this->schema, 'ssl_mode' => $this->sslMode, 'timeout' => $this->timeout,
        ];
    }

    /** Same database with a different name (used for staging copies). */
    public function withDatabase(string $name): self
    {
        return self::fromArray(array_merge($this->toArray(), ['database' => $name]));
    }

    // ------------------------------------------------------------------ validation

    /** @throws InvalidArgumentException with a message that never contains the password. */
    public function validate(): void
    {
        if ($this->database !== '' && preg_match('/^[A-Za-z0-9_$.\-]{1,63}$/', $this->database) !== 1) {
            throw new InvalidArgumentException('Database name may contain only letters, digits, underscore, dollar, dot and hyphen (max 63 characters).');
        }
        if ($this->socket === null && ($this->host === '' || preg_match('/^[A-Za-z0-9._:\-\[\]]+$/', $this->host) !== 1)) {
            throw new InvalidArgumentException('Database host is empty or contains invalid characters.');
        }
        if ($this->port < 1 || $this->port > 65535) {
            throw new InvalidArgumentException('Database port must be between 1 and 65535.');
        }
        if (strlen($this->username) > 128) {
            throw new InvalidArgumentException('Database username is too long.');
        }
        if (!in_array($this->sslMode, ['disable', 'prefer', 'require', 'verify-ca', 'verify-full'], true)) {
            throw new InvalidArgumentException('SSL mode must be one of: disable, prefer, require, verify-ca, verify-full.');
        }
        if (preg_match('/^[A-Za-z0-9_\-]{1,30}$/', $this->charset) !== 1) {
            throw new InvalidArgumentException('Invalid character set name.');
        }
        if ($this->schema !== null && preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,62}$/', $this->schema) !== 1) {
            throw new InvalidArgumentException('Schema name may contain only letters, digits and underscore.');
        }
        foreach ([$this->socket, $this->sslCa, $this->sslCert, $this->sslKey] as $p) {
            if ($p !== null && (str_contains($p, ';') || str_contains($p, "\0") || str_contains($p, "\n"))) {
                throw new InvalidArgumentException('File paths must not contain ";" or control characters.');
            }
        }
    }

    // ------------------------------------------------------------------ internals

    /**
     * All settings including the password. Internal use only (Config arrays, staging copies).
     *
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'engine' => $this->engine, 'driver' => $this->engine, 'host' => $this->host, 'port' => $this->port, 'database' => $this->database,
            'username' => $this->username, 'password' => $this->password, 'socket' => $this->socket,
            'charset' => $this->charset, 'collation' => $this->collation, 'schema' => $this->schema, 'timeout' => $this->timeout,
            'ssl_mode' => $this->sslMode, 'ssl_ca' => $this->sslCa, 'ssl_cert' => $this->sslCert, 'ssl_key' => $this->sslKey,
        ];
    }

    private static function optPath(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));
        return $s === '' ? null : $s;
    }
}
