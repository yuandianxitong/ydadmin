<?php
use think\facade\Route;

// 反馈管理
Route::group('feedback', function () {
    Route::get('list', 'v1.feedback.FeedbackController/list');
    Route::get('detail/:id', 'v1.feedback.FeedbackController/detail');
    Route::post('reply', 'v1.feedback.FeedbackController/reply');
    Route::post('close/:id', 'v1.feedback.FeedbackController/close');
    Route::delete(':id', 'v1.feedback.FeedbackController/delete');
})->middleware(['admin_auth', 'admin_permission', 'admin_log']);
