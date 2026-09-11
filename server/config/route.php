<?php

use app\adminapi\controller\HealthController;
use app\controller\SpaController;
use app\middleware\LocaleMiddleware;
use app\middleware\RequestContextMiddleware;
use core\response\Api;
use Webman\Route;

// 所有 API 路由组的外层中间件：先种 trace，再定 locale。
// 需要登录的路由在组内再嵌套 group，依次追加 AdminAuthMiddleware → AdminPermissionMiddleware（M1 起）。
$apiOuter = [RequestContextMiddleware::class, LocaleMiddleware::class];

Route::group('/adminapi', function () {
    Route::get('/health', [HealthController::class, 'index']);
})->middleware($apiOuter);

// SPA：public/ 下真实存在的文件已被 webman 当静态资源返回，这里只收前端路由路径
Route::get('/', [SpaController::class, 'home']);
Route::get('/admin[/{path:.*}]', [SpaController::class, 'admin']);
Route::get('/pc[/{path:.*}]', [SpaController::class, 'pc']);
Route::get('/mobile[/{path:.*}]', [SpaController::class, 'mobile']);

// 未匹配的路由：HTTP 404 + 统一响应体（TP8 版未知路由同样是 HTTP 404）；关闭 webman 的「/控制器/方法」自动路由。
// 注意与「记录不存在」区分：后者是业务错误，HTTP 200 + code 404。
Route::fallback(fn () => Api::errorWithStatus(lang('messages.api_not_found'), 404))->middleware($apiOuter);
Route::disableDefaultRoute();
