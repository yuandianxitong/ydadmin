<?php

use app\adminapi\controller\payment\PaymentOrderController;
use Webman\Route;

Route::group('/payment/order', function () {
    Route::get('/list', [PaymentOrderController::class, 'index']);
    Route::post('/refund', [PaymentOrderController::class, 'refund']);
    Route::get('/{orderNo:[A-Za-z0-9]+}', [PaymentOrderController::class, 'show']);
})->middleware($adminAuth);
