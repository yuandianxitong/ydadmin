<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
use think\facade\Route;

// 仪表板相关路由
Route::group('dashboard', function () {
    Route::get('stats', 'v1.dashboard.DashboardController/stats');
    Route::get('recent-logs', 'v1.dashboard.DashboardController/recentLogs');
})->middleware(['admin_auth', 'admin_permission', 'admin_log']);
