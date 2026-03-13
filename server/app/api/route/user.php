<?php
use think\facade\Route;

// 用户相关（需要登录）
Route::group('user', function () {
    Route::get('profile', 'v1.user.UserController/profile');
    Route::put('profile', 'v1.user.UserController/updateProfile');
    Route::put('change-password', 'v1.user.UserController/changePassword');
})->middleware(['api_auth']);
