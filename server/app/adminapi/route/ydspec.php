<?php
// server/app/adminapi/route/ydspec.php
use think\facade\Route;

Route::group('system/ydspec', function () {
    Route::post('refine', 'v1.system.YdSpecController/refine');
    Route::post('confirm', 'v1.system.YdSpecController/confirm');
    Route::post('compile', 'v1.system.YdSpecCompileController/compile');
    Route::post('apply-dev', 'v1.system.YdSpecCompileController/applyDev');
})->middleware(['admin_auth', 'admin_permission', 'admin_log']);
