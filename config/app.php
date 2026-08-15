<?php

declare(strict_types=1);

use Core\Support\Env;

return [
    'name'     => Env::get('APP_NAME', 'School ERP'),
    'env'      => Env::get('APP_ENV', 'production'),
    'debug'    => (bool) Env::get('APP_DEBUG', false),
    'url'      => Env::get('APP_URL', ''),
    'key'      => Env::get('APP_KEY', ''),
    'timezone' => Env::get('APP_TIMEZONE', 'UTC'),
    'locale'   => Env::get('APP_LOCALE', 'en'),

    // Application version.
    'version'  => '1.6.14',

    // Pagination defaults.
    'per_page' => 15,
];
