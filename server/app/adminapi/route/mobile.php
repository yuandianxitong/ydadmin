<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * ============================================================ */

use think\facade\Route;

Route::group('mobile', function () {
    // 更具体的路径放前面，避免被 config 路由抢先匹配
    Route::get('config/eligible', 'v1.mobile.MobileConfigController/eligible');
    Route::get('config', 'v1.mobile.MobileConfigController/get');
    Route::put('config', 'v1.mobile.MobileConfigController/update');
})->middleware(['admin_auth', 'admin_permission', 'admin_log']);
