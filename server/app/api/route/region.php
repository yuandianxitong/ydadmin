<?php
use think\facade\Route;

// C端地区数据（无需登录）
Route::group('region', function () {
    Route::get('tree', 'v1.region.RegionController/tree');
    Route::get('children', 'v1.region.RegionController/children');
});
