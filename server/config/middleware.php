<?php

return [
    '' => [
        app\middleware\InstallGuardMiddleware::class,
        app\middleware\RequestContextMiddleware::class,
        app\middleware\LocaleMiddleware::class,
        app\middleware\CorsMiddleware::class,
    ],
];
