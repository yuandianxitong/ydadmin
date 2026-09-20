<?php
// 由代码生成器生成，`php start.php reload` 后生效。
use app\adminapi\controller\agreement\AgreementController;
use Webman\Route;

Route::group('/agreement', function () {
    Route::get('/list', [AgreementController::class, 'index']);
    Route::get('/detail/{id:\d+}', [AgreementController::class, 'show']);
    Route::post('', [AgreementController::class, 'store']);
    Route::put('/{id:\d+}', [AgreementController::class, 'update']);
    Route::delete('/{id:\d+}', [AgreementController::class, 'delete']);
})->middleware($adminAuth);
