<?php
// 由代码生成器生成，`php start.php reload` 后生效。
use app\adminapi\controller\version\AppVersionController;
use Webman\Route;

Route::group('/version', function () {
    Route::get('/list', [AppVersionController::class, 'index']);
    Route::get('/detail/{id:\d+}', [AppVersionController::class, 'show']);
    Route::post('', [AppVersionController::class, 'store']);
    Route::put('/{id:\d+}', [AppVersionController::class, 'update']);
    Route::delete('/{id:\d+}', [AppVersionController::class, 'delete']);
})->middleware($adminAuth);
