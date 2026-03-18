<?php
use think\facade\Route;

// 认证相关（无需登录）
Route::group('auth', function () {
    Route::post('login', 'v1.auth.AuthController/login');
    Route::post('sms-login', 'v1.auth.AuthController/smsLogin');
    Route::post('wechat-login', 'v1.auth.AuthController/wechatLogin');
    Route::post('wechat-web-login', 'v1.auth.AuthController/wechatWebLogin');
    Route::post('register', 'v1.auth.AuthController/register');
});

// 需要登录的认证路由
Route::group('auth', function () {
    Route::get('info', 'v1.auth.AuthController/info');
    Route::post('logout', 'v1.auth.AuthController/logout');
    Route::post('refresh-token', 'v1.auth.AuthController/refreshToken');
})->middleware(['api_auth']);
