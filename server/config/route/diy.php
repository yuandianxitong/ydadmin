<?php

use app\adminapi\controller\diy\DiyLinkController;
use app\adminapi\controller\diy\DiyPageController;
use Webman\Route;

Route::group('/diy', function () {
    Route::get('/home/summary', [DiyPageController::class, 'homeSummary']);
    Route::post('/home/publish', [DiyPageController::class, 'publishHome']);
    Route::get('/home/versions', [DiyPageController::class, 'versions']);
    Route::post('/home/versions/{id:\d+}/restore', [DiyPageController::class, 'restoreVersion']);
    Route::get('/home', [DiyPageController::class, 'getHome']);
    Route::put('/home', [DiyPageController::class, 'saveHome']);

    Route::get('/widgets', [DiyPageController::class, 'widgets']);
    Route::post('/widget-preview', [DiyPageController::class, 'previewWidget']);
    Route::get('/link-catalog', [DiyPageController::class, 'linkCatalog']);
    Route::get('/links', [DiyLinkController::class, 'index']);
    Route::post('/links', [DiyLinkController::class, 'store']);
    Route::put('/links/{id:\d+}', [DiyLinkController::class, 'update']);
    Route::delete('/links/{id:\d+}', [DiyLinkController::class, 'delete']);

    Route::get('/pages/{key:[a-z0-9-]+}/summary', [DiyPageController::class, 'pageSummary']);
    Route::get('/pages/{key:[a-z0-9-]+}/draft', [DiyPageController::class, 'getDraftByKey']);
    Route::put('/pages/{key:[a-z0-9-]+}/draft', [DiyPageController::class, 'saveDraftByKey']);
    Route::post('/pages/{key:[a-z0-9-]+}/publish', [DiyPageController::class, 'publishByKey']);
    Route::get('/pages/{key:[a-z0-9-]+}/versions', [DiyPageController::class, 'versionsByKey']);
    Route::post('/pages/{key:[a-z0-9-]+}/versions/{id:\d+}/restore', [DiyPageController::class, 'restoreVersionByKey']);

    Route::get('/pages', [DiyPageController::class, 'listPages']);
    Route::post('/pages', [DiyPageController::class, 'createPage']);
    Route::post('/pages/{id:\d+}/copy', [DiyPageController::class, 'copyPage']);
    Route::put('/pages/{id:\d+}', [DiyPageController::class, 'updatePage']);
    Route::delete('/pages/{id:\d+}', [DiyPageController::class, 'deletePage']);
})->middleware($adminAuth);
