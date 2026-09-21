<?php

use app\adminapi\controller\dataimport\DataImportController;
use Webman\Route;

Route::group('/dataimport', function () {
    Route::post('/upload', [DataImportController::class, 'upload']);
    Route::get('/history', [DataImportController::class, 'history']);
})->middleware($adminAuth);
