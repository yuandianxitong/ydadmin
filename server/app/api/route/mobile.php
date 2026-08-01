<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * ============================================================ */

use think\facade\Route;

Route::group('mobile', function () {
    Route::get('config', 'v1.mobile.MobileConfigController/get');
    Route::get('diy-page', 'v1.mobile.DiyPageController/get');
});
