<?php
use think\facade\Route;

// C端公告（无需登录）
Route::group('announcement', function () {
    Route::get('list', 'v1.announcement.AnnouncementController/list');
    Route::get('detail/:id', 'v1.announcement.AnnouncementController/detail');
});
