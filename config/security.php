<?php

declare(strict_types=1);

use Core\Support\Env;

return [
    'csrf_token_name' => Env::get('CSRF_TOKEN_NAME', '_token'),
    'bcrypt_cost'     => (int) Env::get('BCRYPT_COST', 12),

    'rate_limit' => [
        'max'    => (int) Env::get('RATE_LIMIT_MAX', 60),
        'window' => (int) Env::get('RATE_LIMIT_WINDOW', 60), // seconds
    ],

    // Content Security Policy header directives.
    'csp' => [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com https://code.jquery.com https://cdn.jsdelivr.net",
        "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com",
        "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com",
        "img-src 'self' data: https:",
        "connect-src 'self'",
        "frame-ancestors 'self'",
        "base-uri 'self'",
        "form-action 'self'",
    ],

    // Additional security response headers.
    'headers' => [
        'X-Content-Type-Options'   => 'nosniff',
        'X-Frame-Options'          => 'SAMEORIGIN',
        'X-XSS-Protection'         => '1; mode=block',
        'Referrer-Policy'          => 'strict-origin-when-cross-origin',
        'Permissions-Policy'       => 'geolocation=(), microphone=(), camera=()',
    ],

    // Login throttling.
    'login_max_attempts' => 5,
    'login_lockout_time' => 900, // seconds (15 min)
];
