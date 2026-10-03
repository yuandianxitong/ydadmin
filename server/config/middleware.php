<?php

return [
    // 顺序：先安装守卫，再 trace，再语言，最后 CORS。
    '' => [
        app\middleware\InstallGuardMiddleware::class,
        app\middleware\RequestContextMiddleware::class,
        app\middleware\LocaleMiddleware::class,
        app\middleware\CorsMiddleware::class,
    ],
];
