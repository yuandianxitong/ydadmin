<?php

use app\adminapi\controller\auth\AuthController;
use app\adminapi\controller\HealthController;
use app\adminapi\controller\system\AdminController;
use app\adminapi\controller\system\MenuController;
use app\adminapi\controller\system\SystemConfigController;
use app\controller\SpaController;
use app\middleware\AdminAuthMiddleware;
use app\middleware\AdminPermissionMiddleware;
use app\middleware\CorsMiddleware;
use app\middleware\LocaleMiddleware;
use app\middleware\LoginRateLimitMiddleware;
use app\middleware\RequestContextMiddleware;
use core\response\Api;
use Webman\Route;

// 所有 API 路由组的外层中间件：先种 trace，再定 locale，再处理跨域（fallback 同样挂载）。
$apiOuter = [RequestContextMiddleware::class, LocaleMiddleware::class, CorsMiddleware::class];

// 认证组：先认身份，再按 #[Permission]/#[PermissionSkip] 判定（默认拒绝）。
// 除下方三个公开路由外，/adminapi 下的路由都必须挂在这个组里——Test6 会逐条检查。
$adminAuth = [AdminAuthMiddleware::class, AdminPermissionMiddleware::class];

Route::group('/adminapi', function () use ($adminAuth) {
    // ---- 公开路由（与 Test6 的白名单保持一致）
    Route::get('/health', [HealthController::class, 'index']);
    Route::get('/auth/captcha', [AuthController::class, 'captcha']);
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware([LoginRateLimitMiddleware::class]);

    // ---- 认证组。具名路由必须写在 {id} 通配路由之前，否则会被 {id} 吞掉
    Route::group('/auth', function () {
        Route::get('/info', [AuthController::class, 'info']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/logout', [AuthController::class, 'logout']);
    })->middleware($adminAuth);

    Route::group('/system/menu', function () {
        Route::get('/routes', [MenuController::class, 'routes']);
    })->middleware($adminAuth);

    Route::group('/system/config', function () {
        Route::get('/global', [SystemConfigController::class, 'global']);
    })->middleware($adminAuth);

    Route::group('/system/admin', function () {
        Route::get('', [AdminController::class, 'index']);
        Route::get('/role/options', [AdminController::class, 'roleOptions']);
        Route::put('/change-password', [AdminController::class, 'changePassword']);
        Route::post('/batch-delete', [AdminController::class, 'batchDelete']);
        Route::put('/{id:\d+}/status', [AdminController::class, 'status']);
        Route::put('/{id:\d+}/reset-password', [AdminController::class, 'resetPassword']);
        Route::get('/{id:\d+}', [AdminController::class, 'show']);
        Route::post('', [AdminController::class, 'store']);
        Route::put('/{id:\d+}', [AdminController::class, 'update']);
        Route::delete('/{id:\d+}', [AdminController::class, 'delete']);
    })->middleware($adminAuth);
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
