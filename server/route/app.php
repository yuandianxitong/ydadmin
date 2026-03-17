<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\facade\Route;

// 前端 SPA 回退：/admin 下非静态资源的请求统一返回 index.html
Route::get('admin/<any?>', function () {
    return response(file_get_contents(public_path() . 'admin/index.html'), 200, [
        'Content-Type' => 'text/html; charset=utf-8',
    ]);
})->pattern(['any' => '(?!css/|js/|fonts/|favicon\.ico).*']);

// PC SPA 回退：/pc 下非静态资源的请求统一返回 index.html
Route::get('pc/<any?>', function () {
    $file = public_path() . 'pc/index.html';
    if (!file_exists($file)) {
        return response('PC site not deployed', 404);
    }
    return response(file_get_contents($file), 200, [
        'Content-Type' => 'text/html; charset=utf-8',
    ]);
})->pattern(['any' => '(?!css/|js/|fonts/|favicon\.ico|_nuxt/).*']);

// Mobile SPA 回退：/mobile 下非静态资源的请求统一返回 index.html
Route::get('mobile/<any?>', function () {
    $file = public_path() . 'mobile/index.html';
    if (!file_exists($file)) {
        return response('Mobile site not deployed', 404);
    }
    return response(file_get_contents($file), 200, [
        'Content-Type' => 'text/html; charset=utf-8',
    ]);
})->pattern(['any' => '(?!css/|js/|fonts/|favicon\.ico|static/).*']);