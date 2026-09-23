<?php

/**
 * 静态文件设置：public/ 下存在的文件由 webman 直接返回（先于路由匹配）。
 */
return [
    'enable'     => true,
    'middleware' => [
        app\middleware\InstallGuardMiddleware::class,
        app\middleware\StaticFile::class,
    ],
];
