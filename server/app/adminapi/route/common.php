<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
use think\facade\Route;

// 公共接口（无需权限验证）
Route::group('common', function () {
    Route::get('regions', 'v1.region.RegionController/tree');
})->middleware('admin_auth');

