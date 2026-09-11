<?php

return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver'    => 'mysql',
            'host'      => env('DB_HOST', '127.0.0.1'),
            'port'      => env('DB_PORT', 3306),
            'database'  => env('DB_NAME', 'dev007_ydadmin'),
            'username'  => env('DB_USER', 'root'),
            'password'  => env('DB_PASSWORD', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_0900_ai_ci',
            'prefix'    => env('DB_PREFIX', ''),
            'strict'    => true,
            'engine'    => null,
        ],
    ],
];
