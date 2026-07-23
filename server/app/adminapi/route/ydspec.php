<?php
// server/app/adminapi/route/ydspec.php
use think\facade\Route;

Route::group('system/ydspec', function () {
    Route::post('refine', 'v1.system.YdSpecController/refine');
    Route::post('confirm', 'v1.system.YdSpecController/confirm');
})->middleware(['admin_auth', 'admin_permission', 'admin_log']);
