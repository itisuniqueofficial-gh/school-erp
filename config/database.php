<?php

declare(strict_types=1);

use Core\Support\Env;

$default = Env::get('DB_CONNECTION', Env::get('DB_DRIVER', 'mysql'));

return [
    'default' => $default,

    'connections' => [
        'mysql' => [
            'driver'    => 'mysql',
            'host'      => Env::get('DB_HOST', '127.0.0.1'),
            'port'      => (int) Env::get('DB_PORT', 3306),
            'database'  => Env::get('DB_DATABASE', 'school_erp'),
            'username'  => Env::get('DB_USERNAME', 'root'),
            'password'  => Env::get('DB_PASSWORD', ''),
            'charset'   => Env::get('DB_CHARSET', 'utf8mb4'),
            'collation' => Env::get('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'options'   => [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
                PDO::ATTR_PERSISTENT         => false,
            ]
            + (static function (): array {
                $ssl = strtolower((string) Env::get('DB_SSL', ''));
                if (!in_array($ssl, ['1', 'true', 'on', 'yes', 'require', 'required'], true)) {
                    return [];
                }
                $opts = [];
                $ca = (string) Env::get('DB_SSL_CA', '');
                if ($ca !== '' && defined('PDO::MYSQL_ATTR_SSL_CA')) {
                    $opts[PDO::MYSQL_ATTR_SSL_CA] = $ca;
                }
                $verify = strtolower((string) Env::get('DB_SSL_VERIFY', 'true'));
                if (in_array($verify, ['0', 'false', 'off', 'no'], true) && defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                    $opts[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
                }
                if ($opts === [] && defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                    $opts[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
                }
                return $opts;
            })(),
        ],

        'mariadb' => [
            'driver'    => 'mariadb',
            'host'      => Env::get('DB_HOST', '127.0.0.1'),
            'port'      => (int) Env::get('DB_PORT', 3306),
            'database'  => Env::get('DB_DATABASE', 'school_erp'),
            'username'  => Env::get('DB_USERNAME', 'root'),
            'password'  => Env::get('DB_PASSWORD', ''),
            'charset'   => Env::get('DB_CHARSET', 'utf8mb4'),
            'collation' => Env::get('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'options'   => [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
                PDO::ATTR_PERSISTENT         => false,
            ],
        ],

        'pgsql' => [
            'driver'    => 'pgsql',
            'host'      => Env::get('DB_HOST', '127.0.0.1'),
            'port'      => (int) Env::get('DB_PORT', 5432),
            'database'  => Env::get('DB_DATABASE', 'school_erp'),
            'username'  => Env::get('DB_USERNAME', 'postgres'),
            'password'  => Env::get('DB_PASSWORD', ''),
            'charset'   => Env::get('DB_CHARSET', 'utf8'),
            'options'   => [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => false,
            ],
        ],

        'postgres' => [
            'driver'    => 'pgsql',
            'host'      => Env::get('DB_HOST', '127.0.0.1'),
            'port'      => (int) Env::get('DB_PORT', 5432),
            'database'  => Env::get('DB_DATABASE', 'school_erp'),
            'username'  => Env::get('DB_USERNAME', 'postgres'),
            'password'  => Env::get('DB_PASSWORD', ''),
            'charset'   => Env::get('DB_CHARSET', 'utf8'),
            'options'   => [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => false,
            ],
        ],

        'sqlite' => [
            'driver'    => 'sqlite',
            'database'  => Env::get('DB_DATABASE', Env::get('DB_SQLITE_PATH', dirname(__DIR__) . '/storage/database.sqlite')),
            'options'   => [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => false,
            ],
        ],

        'sqlsrv' => [
            'driver'    => 'sqlsrv',
            'host'      => Env::get('DB_HOST', '127.0.0.1'),
            'port'      => (int) Env::get('DB_PORT', 1433),
            'database'  => Env::get('DB_DATABASE', 'school_erp'),
            'username'  => Env::get('DB_USERNAME', 'sa'),
            'password'  => Env::get('DB_PASSWORD', ''),
            'options'   => [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
        ],
    ],

    // Directory holding migration files.
    'migrations_path' => dirname(__DIR__) . '/database/migrations',
    'seeds_path'      => dirname(__DIR__) . '/database/Seeds',
];
