<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
return [
    // 认证配置
    'auth' => [
        'token_expire' => 7200, // Token过期时间（秒）
        'refresh_expire' => 86400, // 刷新Token过期时间（秒）
        'max_login_attempts' => 5, // 最大登录尝试次数
        'lockout_duration' => 900, // 账户锁定时间（秒）
    ],

    // 缓存配置
    'cache' => [
        'default_expire' => 3600,
        'tags' => [
            'user' => 'user_cache',
            'system' => 'system_cache',
        ],
    ],

    // 文件上传配置
    'upload' => [
        'max_size' => 10 * 1024 * 1024, // 10MB
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],
        'storage_path' => 'uploads',
    ],

    // 队列配置
    'queue' => [
        'default' => 'redis',
        'connections' => [
            'redis' => [
                'driver' => 'redis',
                'host' => '127.0.0.1',
                'port' => 6379,
                'database' => 0,
                'prefix' => 'queue:',
            ],
        ],
    ],

    // 日志配置
    'log' => [
        'user_action' => true, // 是否记录用户操作日志
        'api_access' => true, // 是否记录API访问日志
        'slow_query' => true, // 是否记录慢查询
        'performance' => false, // 是否记录性能日志
    ],

    // 安全配置
    'security' => [
        'encrypt_key' => env('APP_KEY', ''),
        'password_min_length' => 6,
        'password_strength' => 2, // 密码强度等级
        'session_timeout' => 1800, // 会话超时时间（秒）
    ],
];
