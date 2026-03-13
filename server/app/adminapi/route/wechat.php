<?php
use think\facade\Route;

// 微信管理
Route::group('wechat', function () {

    // 公众号管理
    Route::group('official', function () {
        Route::get('menu', 'v1.wechat.OfficialAccountController/getMenu');
        Route::post('menu', 'v1.wechat.OfficialAccountController/createMenu');
        Route::delete('menu', 'v1.wechat.OfficialAccountController/deleteMenu');
        Route::post('template/send', 'v1.wechat.OfficialAccountController/sendTemplate');
        Route::get('followers', 'v1.wechat.OfficialAccountController/followers');
        Route::get('user-info', 'v1.wechat.OfficialAccountController/userInfo');
    });

    // 自动回复管理
    Route::group('auto-reply', function () {
        Route::get('', 'v1.wechat.AutoReplyController/index');
        Route::get(':id', 'v1.wechat.AutoReplyController/show');
        Route::post('', 'v1.wechat.AutoReplyController/store');
        Route::put(':id', 'v1.wechat.AutoReplyController/update');
        Route::delete(':id', 'v1.wechat.AutoReplyController/delete');
    });

})->middleware(['admin_auth', 'admin_permission', 'admin_log']);
