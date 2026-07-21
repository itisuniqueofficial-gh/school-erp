<?php

declare(strict_types=1);

use Core\Support\Env;

return [
    // Session key under which the authenticated user id is stored.
    'session_key'   => 'auth_user_id',
    'remember_name' => 'remember_token',
    'remember_days' => 30,

    // Password reset token lifetime in minutes.
    'reset_token_ttl' => 60,

    // API token settings.
    'api' => [
        'ttl' => (int) Env::get('API_TOKEN_TTL', 3600),
    ],

    // Landing route per role after login.
    'home_routes' => [
        'super_admin'       => '/admin/dashboard',
        'school_admin'      => '/admin/dashboard',
        'principal'         => '/admin/dashboard',
        'vice_principal'    => '/admin/dashboard',
        'teacher'           => '/teacher/dashboard',
        'class_teacher'     => '/teacher/dashboard',
        'accountant'        => '/accountant/dashboard',
        'librarian'         => '/library/dashboard',
        'receptionist'      => '/admin/dashboard',
        'transport_manager' => '/transport/dashboard',
        'hr'                => '/admin/dashboard',
        'student'           => '/student/dashboard',
        'parent'            => '/parent/dashboard',
    ],
];
