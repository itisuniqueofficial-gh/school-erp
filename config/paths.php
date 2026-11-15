<?php

declare(strict_types=1);

$root = dirname(__DIR__);

// STORAGE_PATH moves uploads, logs, cache, sessions and backups out of the project
// (for example to /home/user/erp-storage, outside the web root on cPanel / VPS).
$storage = trim((string) (\Core\Support\Env::get('STORAGE_PATH', '') ?? ''));
$storage = $storage !== '' ? rtrim(str_replace('\\', '/', $storage), '/') : $root . '/storage';

// Automated test runs (APP_TESTING=1) write logs and backups to their own folders, so test output never
// shows up in the live application's logs, Database dashboard or backup list.
$testing = getenv('APP_TESTING') === '1';

return [
    'root'      => $root,
    'app'       => $root . '/app',
    'core'      => $root . '/core',
    'config'    => $root . '/config',
    'database'  => $root . '/database',
    'public'    => $root . '/public',
    'resources' => $root . '/resources',
    'views'     => $root . '/resources/views',
    'storage'   => $storage,
    'uploads'   => $storage . '/uploads',
    'logs'      => $storage . '/logs' . ($testing ? '/testing' : ''),
    'cache'     => $storage . '/cache',
    'sessions'  => $storage . '/sessions',
    'backups'   => $storage . '/backups' . ($testing ? '/testing' : ''),
    'routes'    => $root . '/routes',
];
