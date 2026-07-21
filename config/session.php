<?php

declare(strict_types=1);

use Core\Support\Env;

return [
    'driver'        => Env::get('SESSION_DRIVER', 'file'),
    'lifetime'      => (int) Env::get('SESSION_LIFETIME', 120), // minutes
    'path'          => dirname(__DIR__) . '/storage/sessions',
    'cookie_name'   => 'school_erp_session',
    'secure'        => (bool) Env::get('SESSION_SECURE_COOKIE', false),
    'http_only'     => true,
    'same_site'     => Env::get('SESSION_SAME_SITE', 'Lax'),
    'regenerate'    => 30, // regenerate id every N minutes
];
