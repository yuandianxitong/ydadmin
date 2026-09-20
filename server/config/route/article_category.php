<?php
// 由代码生成器生成，`php start.php reload` 后生效。
use app\adminapi\controller\article\ArticleCategoryController;
use Webman\Route;

Route::group('/article-category', function () {
    Route::get('/list', [ArticleCategoryController::class, 'index']);
    Route::get('/options', [ArticleCategoryController::class, 'options']);
    Route::post('', [ArticleCategoryController::class, 'store']);
    Route::put('/{id:\d+}/status', [ArticleCategoryController::class, 'status']);
    Route::put('/{id:\d+}', [ArticleCategoryController::class, 'update']);
    Route::delete('/{id:\d+}', [ArticleCategoryController::class, 'delete']);
})->middleware($adminAuth);
