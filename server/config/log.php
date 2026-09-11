<?php

return [
    'default' => [
        'handlers' => [
            [
                'class'       => Monolog\Handler\RotatingFileHandler::class,
                'constructor' => [
                    runtime_path() . '/logs/webman.log',
                    7,
                    Monolog\Logger::DEBUG,
                ],
                'formatter' => [
                    'class'       => Monolog\Formatter\LineFormatter::class,
                    'constructor' => [null, 'Y-m-d H:i:s', true],
                ],
            ],
        ],
        // LineFormatter 默认格式含 %extra%，trace_id / acting_user 会出现在每行末尾
        'processors' => [
            ['class' => app\log\ContextProcessor::class],
        ],
    ],
];
