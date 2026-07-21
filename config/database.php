<?php

declare(strict_types=1);

use Core\Support\Env;

return [
    'default' => Env::get('DB_CONNECTION', 'mysql'),

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
            ],
        ],
    ],

    // Directory holding migration files.
    'migrations_path' => dirname(__DIR__) . '/database/migrations',
    'seeds_path'      => dirname(__DIR__) . '/database/seeds',
];
