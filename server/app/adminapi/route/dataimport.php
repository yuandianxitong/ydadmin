<?php
use think\facade\Route;

// 数据导入
Route::group('dataimport', function () {
    Route::post('upload', 'v1.dataimport.DataImportController/upload');
    Route::get('history', 'v1.dataimport.DataImportController/history');
})->middleware(['admin_auth', 'admin_permission', 'admin_log']);
