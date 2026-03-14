<?php
use think\facade\Route;

// 公共接口（无需登录）
Route::group('common', function () {
    Route::get('config', 'v1.common.CommonController/config');
    Route::post('sms-code', 'v1.common.CommonController/sendSmsCode')->middleware('api_sms_rate_limit');
});

// 需要登录的公共接口
Route::group('common', function () {
    Route::post('upload/image', 'v1.common.CommonController/uploadImage');
})->middleware(['api_auth']);
