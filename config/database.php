<?php

declare(strict_types=1);

use Core\Database\DatabaseConfig;
use Core\Support\Env;

/*
 * Database configuration. Every way of describing a connection (DATABASE_URL,
 * DB_* variables, .env file) is resolved by Core\Database\DatabaseConfig, so
 * validation and defaults live in one place. Engines: mysql, mariadb, pgsql.
 */
$get = static fn (string $k, mixed $d = null): mixed => Env::get($k, $d);

$requested = (string) Env::get('DB_CONNECTION', Env::get('DB_DRIVER', 'mysql'));
$active = [];
$default = $requested;
try {
    $cfg = DatabaseConfig::fromEnv($get);
    $default = $cfg->engine;
    $active = $cfg->toArray();
} catch (\Throwable) {
    // Invalid or unsupported settings: leave $active empty. Connection reports a
    // credential-free error and the installer/health pages explain what to fix.
}

$legacy = static fn (string $driver, int $port, string $user): array => [
    'driver' => $driver, 'engine' => $driver, 'host' => Env::get('DB_HOST', '127.0.0.1'), 'port' => (int) Env::get('DB_PORT', $port),
    'database' => Env::get('DB_DATABASE', 'school_erp'), 'username' => Env::get('DB_USERNAME', $user),
    'password' => Env::get('DB_PASSWORD', ''), 'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
];

$connections = [
    'mysql'    => $legacy('mysql', 3306, 'root'),
    'mariadb'  => $legacy('mariadb', 3306, 'root'),
    'pgsql'    => $legacy('pgsql', 5432, 'postgres'),
    'sqlite'   => [
        'driver'   => 'sqlite',
        'database' => Env::get('DB_DATABASE', Env::get('DB_SQLITE_PATH', dirname(__DIR__) . '/storage/database.sqlite')),
        'options'  => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
    ],
];
if ($active !== []) {
    $connections[$default] = $active;
    if (in_array($default, ['mysql', 'mariadb'], true)) {
        $connections['mysql'] = $active; // BackupService and tooling read the "mysql" entry for MySQL-family servers
    }
}
$connections['postgres'] = $connections['pgsql'];

return [
    'default'         => $default,
    'connections'     => $connections,
    'migrations_path' => dirname(__DIR__) . '/database/migrations',
    'rollbacks_path'  => dirname(__DIR__) . '/database/rollbacks',
    'migrations_pgsql_path' => dirname(__DIR__) . '/database/migrations_pgsql',
    'seeds_path'      => dirname(__DIR__) . '/database/Seeds',
];
