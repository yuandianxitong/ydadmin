<?php
// 由代码生成器生成，`php start.php reload` 后生效。
use app\adminapi\controller\region\RegionController;
use Webman\Route;

Route::group('/region', function () {
    Route::get('/list', [RegionController::class, 'index']);
    Route::get('/detail/{id:\d+}', [RegionController::class, 'show']);
    Route::post('', [RegionController::class, 'store']);
    Route::put('/{id:\d+}', [RegionController::class, 'update']);
    Route::delete('/{id:\d+}', [RegionController::class, 'delete']);
})->middleware($adminAuth);
