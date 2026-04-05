<?php
return [
    'default'     => 'redis',
    'connections' => [
        'sync'  => [
            'type' => 'sync',
        ],
        'redis' => [
            'type'       => 'redis',
            'queue'      => 'default',
            'host'       => env('REDIS_HOST', '127.0.0.1'),
            'port'       => (int) env('REDIS_PORT', 6379),
            'password'   => env('REDIS_PASSWORD', ''),
            'select'     => (int) env('REDIS_DB', 0),
            'timeout'    => 0,
            'persistent' => false,
        ],
    ],
    'failed' => [
        'type'  => 'database',
        'table' => 'failed_jobs',
    ],
];
