<?php
// server/app/adminapi/route/ydspec.php
use think\facade\Route;

Route::group('system/ydspec', function () {
    Route::post('refine', 'v1.system.YdSpecController/refine');
    Route::post('confirm', 'v1.system.YdSpecController/confirm');
    Route::post('compile', 'v1.system.YdSpecCompileController/compile');
    Route::get('artifacts', 'v1.system.YdSpecCompileController/artifacts');
    Route::post('artifacts/recheck/:id', 'v1.system.YdSpecCompileController/recheck');
    Route::post('artifacts/apply/:id', 'v1.system.YdSpecCompileController/apply');
    // 兼容 SP2 旧入口（CLI/脚本），内部走门禁
    Route::post('apply-dev', 'v1.system.YdSpecCompileController/applyDevCompat');
})->middleware(['admin_auth', 'admin_permission', 'admin_log']);
