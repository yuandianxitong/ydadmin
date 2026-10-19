<?php

use app\adminapi\controller\system\ApiDocController;
use app\controller\InstallController;
use app\controller\SpaController;
use app\middleware\CorsMiddleware;
use core\response\Api;
use Webman\Route;

// 管理端业务路由使用控制器路由属性。API 文档固定路径在此注册，
// ApiDocController 继承 AuthenticatedController，两个动作均检查 system.api_doc 权限。
// 与 APP_DEBUG 无关：生产环境也允许获授权管理员查看。
Route::get('/adminapi/system/api-doc/openapi.json', [ApiDocController::class, 'openapi']);
Route::get('/adminapi/system/api-doc', [ApiDocController::class, 'index']);

// C 端 /api 业务路由已改用控制器上的路由属性；认证由 AuthenticatedController 的 #[Middleware] 或方法上的 #[Middleware] 提供。

// M8：浏览器安装向导。挂在 /adminapi、/api 之外（不进认证组）。
Route::group('/install', function () {
    Route::get('', [InstallController::class, 'index']);
    Route::get('/', [InstallController::class, 'index']);
    Route::get('/environment', [InstallController::class, 'environment']);
    Route::post('/test-connection', [InstallController::class, 'testConnection']);
    Route::post('/run', [InstallController::class, 'run']);
});

// SPA：public/ 下真实存在的文件已被 webman 当静态资源返回，这里只收前端路由路径
Route::get('/', [SpaController::class, 'home']);
Route::get('/admin[/{path:.*}]', [SpaController::class, 'admin']);
Route::get('/pc[/{path:.*}]', [SpaController::class, 'pc']);
Route::get('/mobile[/{path:.*}]', [SpaController::class, 'mobile']);

// 未匹配的路由：HTTP 404 + 统一响应体（TP8 版未知路由同样是 HTTP 404）；关闭 webman 的「/控制器/方法」自动路由。
// 注意与「记录不存在」区分：后者是业务错误，HTTP 200 + code 404。
// 框架组装 fallback 时关掉全局中间件。浏览器预检是 OPTIONS，对不上只登记了实际动词的业务路由，会落到这里。
// 只挂 CORS：允许的来源在进 404 之前返回 204，其余来源 403；普通未命中请求在来源允许时带上同一套跨域头。
Route::fallback(fn () => Api::errorWithStatus(lang('messages.api_not_found'), 404))
    ->middleware([CorsMiddleware::class]);
Route::disableDefaultRoute();
