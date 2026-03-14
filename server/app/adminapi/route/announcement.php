<?php
use think\facade\Route;

// 公告管理
Route::group('announcement', function () {
    Route::get('list', 'v1.announcement.AnnouncementController/list');
    Route::get('detail/:id', 'v1.announcement.AnnouncementController/detail');
    Route::post('', 'v1.announcement.AnnouncementController/create');
    Route::put(':id', 'v1.announcement.AnnouncementController/update');
    Route::put(':id/status', 'v1.announcement.AnnouncementController/updateStatus');
    Route::delete(':id', 'v1.announcement.AnnouncementController/delete');
})->middleware(['admin_auth', 'admin_permission', 'admin_log']);
