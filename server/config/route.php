<?php

use app\adminapi\controller\system\ApiDocController;
use app\controller\InstallController;
use app\controller\SpaController;
use core\response\Api;
use Webman\Route;

// 管理端业务路由已改用控制器上的路由属性（#[RouteGroup]/#[Get] 等），认证三件套由 AuthenticatedController 的 #[Middleware] 提供。
// 此处只留调试期的 API 文档两条（见下）。

// ---- M2b：API 文档。生产环境（APP_DEBUG=false）整个功能都不存在——最诚实的生产闸门实现，
// 没有可探测的面，也不依赖控制器内部判断没被绕过（与 M2a GeneratorService::assertWritesEnabled()
// 不同：那里生产仍要保留只读端点，只关写操作；这里整个功能都不该在生产出现，粒度不同）。
// 两条都必须公开（Test6 白名单已加）：前端「Swagger UI」「下载 JSON」按钮走 window.open，
// 浏览器导航带不了 Authorization 头。代价被这个 if 兜住：生产环境的公开路由数量仍是三条。
// 路径写完整，不放进 /adminapi 分组，否则会变成 /adminapi/adminapi/...。ApiDocController 不写路径属性。
if (config('app.debug')) {
    Route::get('/adminapi/system/api-doc/openapi.json', [ApiDocController::class, 'openapi']);
    Route::get('/adminapi/system/api-doc', [ApiDocController::class, 'index']);
}

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
Route::fallback(fn () => Api::errorWithStatus(lang('messages.api_not_found'), 404));
Route::disableDefaultRoute();
