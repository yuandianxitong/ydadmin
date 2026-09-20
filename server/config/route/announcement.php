<?php
// 由代码生成器生成，`php start.php reload` 后生效。
use app\adminapi\controller\announcement\AnnouncementController;
use Webman\Route;

Route::group('/announcement', function () {
    Route::get('/list', [AnnouncementController::class, 'index']);
    Route::get('/detail/{id:\d+}', [AnnouncementController::class, 'show']);
    Route::post('', [AnnouncementController::class, 'store']);
    Route::put('/{id:\d+}/status', [AnnouncementController::class, 'status']);
    Route::put('/{id:\d+}', [AnnouncementController::class, 'update']);
    Route::delete('/{id:\d+}', [AnnouncementController::class, 'delete']);
})->middleware($adminAuth);
