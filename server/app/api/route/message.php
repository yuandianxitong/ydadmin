<?php
use think\facade\Route;

// 用户消息/通知（需要登录）
Route::group('message', function () {
    Route::get('list', 'v1.message.MessageController/list');
    Route::get('unread-count', 'v1.message.MessageController/unreadCount');
    Route::post('read', 'v1.message.MessageController/read');
})->middleware(['api_auth']);
