<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
use think\facade\Route;

// 认证相关（无需认证的路由）
Route::group('auth', function () {
    Route::get('captcha', 'v1.auth.AuthController/captcha');
    Route::post('login', 'v1.auth.AuthController/login');
})->middleware([\app\adminapi\middleware\LoginRateLimitMiddleware::class]);

// 需要认证的认证相关路由
Route::group('auth', function () {
    Route::get('info', 'v1.auth.AuthController/info');
    Route::post('refresh', 'v1.auth.AuthController/refresh');
    Route::post('logout', 'v1.auth.AuthController/logout');
})->middleware(['admin_auth', 'admin_permission', 'admin_log']);
