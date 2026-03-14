<?php
use think\facade\Route;

// 文章分类（无需登录）
Route::group('article-category', function () {
    Route::get('list', 'v1.article.ArticleCategoryController/getList');
});

// 文章（无需登录）
Route::group('article', function () {
    Route::get('list', 'v1.article.ArticleController/getList');
    Route::get('detail/:id', 'v1.article.ArticleController/getDetail');
});
