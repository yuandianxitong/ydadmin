<?php
use think\facade\Route;

// 协议管理
Route::group('agreement', function () {
    Route::get('list', 'v1.agreement.AgreementController/list');
    Route::get('detail/:id', 'v1.agreement.AgreementController/detail');
    Route::post('', 'v1.agreement.AgreementController/create');
    Route::put(':id', 'v1.agreement.AgreementController/update');
    Route::delete(':id', 'v1.agreement.AgreementController/delete');
})->middleware(['admin_auth', 'admin_permission', 'admin_log']);
