<?php

return [
    'default' => [
        'host'     => env('REDIS_HOST', '127.0.0.1'),
        'port'     => (int) env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASSWORD', ''),
        'database' => (int) env('REDIS_DB', 0),
        'pool' => [
            'max_connections'    => 5,
            'min_connections'    => 1,
            'wait_timeout'       => 3,
            'idle_timeout'       => 60,
            'heartbeat_interval' => 50,
        ],
    ],
];
