<?php

declare(strict_types=1);

use Core\Support\Env;

return [
    'default' => Env::get('DB_CONNECTION', Env::get('DB_DRIVER', 'mysql')),

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
            // Optional TLS for managed MySQL (Aiven, PlanetScale, etc.). Only
            // activates when DB_SSL is truthy, so local/CI is unaffected.
            //   DB_SSL=true              enable TLS
            //   DB_SSL_CA=/path/ca.pem   CA bundle for server-cert verification
            //   DB_SSL_VERIFY=false      skip server-cert verification (public proxies)
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
                // Ensure TLS is actually requested even when no CA/verify flag is given.
                if ($opts === [] && defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                    $opts[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
                }
                return $opts;
            })(),
        ],

        // PostgreSQL portability: connect the SAME env vars to a Postgres server
        // by setting DB_DRIVER=pgsql (or DB_CONNECTION=pgsql). No code changes.
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
    ],

    // Directory holding migration files.
    'migrations_path' => dirname(__DIR__) . '/database/migrations',
    'seeds_path'      => dirname(__DIR__) . '/database/Seeds',
];
