<?php
use think\facade\Route;

// 用户相关（需要登录）
Route::group('user', function () {
    Route::get('profile', 'v1.user.UserController/profile');
    Route::put('profile', 'v1.user.UserController/updateProfile');
    Route::put('change-password', 'v1.user.UserController/changePassword');
    Route::get('balance', 'v1.user.UserController/balance');
    Route::get('balance-logs', 'v1.user.UserController/balanceLogs');
    Route::get('points', 'v1.user.UserController/points');
    Route::get('points-logs', 'v1.user.UserController/pointsLogs');
    Route::post('recharge', 'v1.user.UserController/recharge');
})->middleware(['api_auth']);
