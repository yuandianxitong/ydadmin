<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
use think\facade\Route;

// 文件上传相关路由
Route::group('upload', function () {
    Route::post('image', 'v1.upload.UploadController/image');
    Route::post('file', 'v1.upload.UploadController/file');
})->middleware(['admin_auth']);
