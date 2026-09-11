<?php

return [
    'default' => 'redis',
    'stores' => [
        'redis' => [
            'driver'     => 'redis',
            'connection' => 'default',
        ],
        'file' => [
            'driver' => 'file',
            'path'   => runtime_path('cache'),
        ],
        'array' => [
            'driver' => 'array',
        ],
    ],
];
