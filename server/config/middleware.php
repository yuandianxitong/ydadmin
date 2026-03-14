<?php
// 中间件配置
return [
    // 别名或分组
    'alias'    => [
        'admin_auth' => app\adminapi\middleware\AdminAuthMiddleware::class,
        'admin_permission' => app\adminapi\middleware\AdminPermissionMiddleware::class,
        'admin_log' => app\adminapi\middleware\AdminLogMiddleware::class,
        'api_auth' => app\api\middleware\ApiAuthMiddleware::class,
        'api_rate_limit' => app\api\middleware\ApiRateLimitMiddleware::class,
    ],
    // 优先级设置，此数组中的中间件会按照数组中的顺序优先执行
    'priority' => [],
];
