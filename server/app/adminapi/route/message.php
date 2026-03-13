<?php
use think\facade\Route;

// 消息管理
Route::group('message', function () {

    // 消息模板
    Route::group('template', function () {
        Route::get('', 'v1.message.MessageTemplateController/index');
        Route::get(':id', 'v1.message.MessageTemplateController/show');
        Route::post('', 'v1.message.MessageTemplateController/store');
        Route::put(':id', 'v1.message.MessageTemplateController/update');
        Route::delete(':id', 'v1.message.MessageTemplateController/delete');
        Route::post('test-send', 'v1.message.MessageTemplateController/testSend');
    });

    // 发送记录
    Route::get('log', 'v1.message.MessageLogController/index');

})->middleware(['admin_auth', 'admin_permission', 'admin_log']);
