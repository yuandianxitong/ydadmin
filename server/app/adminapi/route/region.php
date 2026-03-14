<?php
use think\facade\Route;

// 地区管理
Route::group('region', function () {
    Route::get('list', 'v1.region.RegionController/list');
    Route::get('tree', 'v1.region.RegionController/tree');
    Route::get('detail/:id', 'v1.region.RegionController/detail');
    Route::post('', 'v1.region.RegionController/create');
    Route::put(':id', 'v1.region.RegionController/update');
    Route::delete(':id', 'v1.region.RegionController/delete');
})->middleware(['admin_auth', 'admin_permission', 'admin_log']);
