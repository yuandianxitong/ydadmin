<?php

use app\adminapi\controller\feedback\FeedbackController;
use Webman\Route;

Route::group('/feedback', function () {
    Route::get('/list', [FeedbackController::class, 'index']);
    Route::get('/detail/{id:\d+}', [FeedbackController::class, 'show']);
    Route::post('/reply', [FeedbackController::class, 'reply']);
    Route::post('/close/{id:\d+}', [FeedbackController::class, 'close']);
    Route::delete('/{id:\d+}', [FeedbackController::class, 'delete']);
})->middleware($adminAuth);
