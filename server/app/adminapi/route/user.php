<?php
use think\facade\Route;

Route::group('user', function () {
    Route::get('list', 'v1.user.UserManageController/list');
    Route::get('detail/:id', 'v1.user.UserManageController/detail');
    Route::post('adjust-balance', 'v1.user.UserManageController/adjustBalance');
    Route::post('adjust-points', 'v1.user.UserManageController/adjustPoints');
    Route::put(':id/status', 'v1.user.UserManageController/updateStatus');
    Route::get('balance-logs', 'v1.user.UserManageController/balanceLogs');
    Route::get('points-logs', 'v1.user.UserManageController/pointsLogs');
})->middleware(['admin_auth', 'admin_permission', 'admin_log']);
