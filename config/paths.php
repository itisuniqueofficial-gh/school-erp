<?php

declare(strict_types=1);

$root = dirname(__DIR__);

return [
    'root'      => $root,
    'app'       => $root . '/app',
    'core'      => $root . '/core',
    'config'    => $root . '/config',
    'database'  => $root . '/database',
    'public'    => $root . '/public',
    'resources' => $root . '/resources',
    'views'     => $root . '/resources/views',
    'storage'   => $root . '/storage',
    'uploads'   => $root . '/storage/uploads',
    'logs'      => $root . '/storage/logs',
    'cache'     => $root . '/storage/cache',
    'sessions'  => $root . '/storage/sessions',
    'backups'   => $root . '/storage/backups',
    'routes'    => $root . '/routes',
];
