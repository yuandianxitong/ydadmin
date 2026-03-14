<?php
use think\facade\Route;

// 版本管理
Route::group('version', function () {
    Route::get('list', 'v1.version.AppVersionController/list');
    Route::get('detail/:id', 'v1.version.AppVersionController/detail');
    Route::post('', 'v1.version.AppVersionController/create');
    Route::put(':id', 'v1.version.AppVersionController/update');
    Route::delete(':id', 'v1.version.AppVersionController/delete');
})->middleware(['admin_auth', 'admin_permission', 'admin_log']);
