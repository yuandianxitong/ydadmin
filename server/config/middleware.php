<?php

return [
    // 顺序：安装守卫、trace、语言、CORS、请求遥测。
    '' => [
        app\middleware\InstallGuardMiddleware::class,
        app\middleware\RequestContextMiddleware::class,
        app\middleware\LocaleMiddleware::class,
        app\middleware\CorsMiddleware::class,
        app\middleware\TelemetryMiddleware::class,
    ],
];
