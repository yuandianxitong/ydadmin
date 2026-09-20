<?php
// 由代码生成器生成，`php start.php reload` 后生效。
use app\adminapi\controller\article\ArticleController;
use Webman\Route;

Route::group('/article', function () {
    Route::get('/list', [ArticleController::class, 'index']);
    Route::get('/detail/{id:\d+}', [ArticleController::class, 'show']);
    Route::post('', [ArticleController::class, 'store']);
    Route::put('/{id:\d+}/status', [ArticleController::class, 'status']);
    Route::put('/{id:\d+}', [ArticleController::class, 'update']);
    Route::delete('/{id:\d+}', [ArticleController::class, 'delete']);
})->middleware($adminAuth);
