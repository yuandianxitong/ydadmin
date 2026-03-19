<?php
use think\facade\Route;

// 文章分类管理
Route::group('article-category', function () {
    Route::get('list', 'v1.article.ArticleCategoryController/list');
    Route::get('options', 'v1.article.ArticleCategoryController/options');
    Route::post('', 'v1.article.ArticleCategoryController/create');
    Route::put(':id/status', 'v1.article.ArticleCategoryController/updateStatus');
    Route::put(':id', 'v1.article.ArticleCategoryController/update');
    Route::delete(':id', 'v1.article.ArticleCategoryController/delete');
})->middleware(['admin_auth', 'admin_permission', 'admin_log']);

// 文章管理
Route::group('article', function () {
    Route::get('list', 'v1.article.ArticleController/list');
    Route::get('detail/:id', 'v1.article.ArticleController/detail');
    Route::post('', 'v1.article.ArticleController/create');
    Route::put(':id/status', 'v1.article.ArticleController/updateStatus');
    Route::put(':id', 'v1.article.ArticleController/update');
    Route::delete(':id', 'v1.article.ArticleController/delete');
})->middleware(['admin_auth', 'admin_permission', 'admin_log']);
