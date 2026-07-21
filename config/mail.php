<?php

declare(strict_types=1);

use Core\Support\Env;

return [
    'mailer' => Env::get('MAIL_MAILER', 'smtp'),
    'host'   => Env::get('MAIL_HOST', 'localhost'),
    'port'   => (int) Env::get('MAIL_PORT', 587),
    'username'   => Env::get('MAIL_USERNAME', ''),
    'password'   => Env::get('MAIL_PASSWORD', ''),
    'encryption' => Env::get('MAIL_ENCRYPTION', 'tls'),
    'from'   => [
        'address' => Env::get('MAIL_FROM_ADDRESS', 'no-reply@schoolerp.test'),
        'name'    => Env::get('MAIL_FROM_NAME', 'School ERP'),
    ],

    'sms' => [
        'driver'    => Env::get('SMS_DRIVER', 'log'),
        'api_key'   => Env::get('SMS_API_KEY', ''),
        'sender_id' => Env::get('SMS_SENDER_ID', ''),
    ],
];
