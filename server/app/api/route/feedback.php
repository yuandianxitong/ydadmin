<?php
use think\facade\Route;

// 反馈相关（需要登录）
Route::group('feedback', function () {
    Route::post('submit', 'v1.feedback.FeedbackController/submit');
    Route::get('list', 'v1.feedback.FeedbackController/list');
    Route::get('detail/:id', 'v1.feedback.FeedbackController/detail');
})->middleware(['api_auth']);
