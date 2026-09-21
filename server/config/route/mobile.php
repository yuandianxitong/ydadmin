<?php

use app\adminapi\controller\mobile\MobileConfigController;
use Webman\Route;

Route::group('/mobile', function () {
    Route::get('/config/eligible', [MobileConfigController::class, 'eligible']);
    Route::get('/config', [MobileConfigController::class, 'show']);
    Route::put('/config', [MobileConfigController::class, 'update']);
})->middleware($adminAuth);
