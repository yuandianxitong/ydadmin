<?php
// 由代码生成器生成，`php start.php reload` 后生效。
use app\adminapi\controller\demo\GenArticleController;
use Webman\Route;

Route::group('/demo/gen-article', function () {
    Route::get('', [GenArticleController::class, 'index']);
    Route::post('/batch-delete', [GenArticleController::class, 'batchDelete']);
    Route::put('/{id:\d+}/status', [GenArticleController::class, 'status']);
    Route::get('/{id:\d+}', [GenArticleController::class, 'show']);
    Route::post('', [GenArticleController::class, 'store']);
    Route::put('/{id:\d+}', [GenArticleController::class, 'update']);
    Route::delete('/{id:\d+}', [GenArticleController::class, 'delete']);
})->middleware($adminAuth);
