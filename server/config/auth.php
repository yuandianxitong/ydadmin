<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
return [
    // JWT配置
    'jwt' => [
        'key' => env('JWT_SECRET', 'your-secret-key'),
        'algorithm' => 'HS256',
        'expire' => 7200, // 2小时
        'refresh_expire' => 86400, // 1天
        'issuer' => 'thinkphp8-admin',
    ],

    // 权限配置
    'permission' => [
        'cache_expire' => 3600, // 权限缓存时间
        'super_admin_role' => 'super_admin', // 超级管理员角色
    ],
];
