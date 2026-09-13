<?php

use app\adminapi\controller\auth\AuthController;
use app\adminapi\controller\dashboard\DashboardController;
use app\adminapi\controller\HealthController;
use app\adminapi\controller\system\AdminController;
use app\adminapi\controller\system\DepartmentController;
use app\adminapi\controller\system\DictionaryController;
use app\adminapi\controller\system\FileController;
use app\adminapi\controller\system\GeneratorController;
use app\adminapi\controller\system\LogController;
use app\adminapi\controller\system\MenuController;
use app\adminapi\controller\system\NotificationController;
use app\adminapi\controller\system\RoleController;
use app\adminapi\controller\system\SystemConfigController;
use app\adminapi\controller\upload\UploadController;
use app\controller\SpaController;
use app\middleware\AdminAuthMiddleware;
use app\middleware\AdminLogMiddleware;
use app\middleware\AdminPermissionMiddleware;
use app\middleware\CorsMiddleware;
use app\middleware\LocaleMiddleware;
use app\middleware\LoginRateLimitMiddleware;
use app\middleware\RequestContextMiddleware;
use core\response\Api;
use Webman\Route;

// 所有 API 路由组的外层中间件：先种 trace，再定 locale，再处理跨域（fallback 同样挂载）。
$apiOuter = [RequestContextMiddleware::class, LocaleMiddleware::class, CorsMiddleware::class];

// 认证组：先认身份，再按 #[Permission]/#[PermissionSkip] 判定（默认拒绝），最内层记操作日志（只记写请求）。
// 除下方三个公开路由外，/adminapi 下的路由都必须挂在这个组里——Test6 会逐条检查。
$adminAuth = [AdminAuthMiddleware::class, AdminPermissionMiddleware::class, AdminLogMiddleware::class];

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
        Route::get('', [MenuController::class, 'index']);
        Route::get('/options', [MenuController::class, 'options']);
        Route::get('/routes', [MenuController::class, 'routes']);
        Route::post('', [MenuController::class, 'store']);
        Route::post('/batch-delete', [MenuController::class, 'batchDelete']);
        Route::post('/batch-sort', [MenuController::class, 'batchSort']);
        Route::put('/{id:\d+}/status', [MenuController::class, 'status']);
        Route::put('/{id:\d+}', [MenuController::class, 'update']);
        Route::delete('/{id:\d+}', [MenuController::class, 'delete']);
    })->middleware($adminAuth);

    Route::group('/system/config', function () {
        Route::get('', [SystemConfigController::class, 'index']);
        Route::get('/groups', [SystemConfigController::class, 'groups']);
        Route::get('/global', [SystemConfigController::class, 'global']);
        Route::post('/batch-update', [SystemConfigController::class, 'batchUpdate']);
        Route::post('/clear-cache', [SystemConfigController::class, 'clearCache']);
        Route::get('/{id:\d+}', [SystemConfigController::class, 'show']);
        Route::put('/{id:\d+}', [SystemConfigController::class, 'update']);
    })->middleware($adminAuth);

    Route::group('/system/role', function () {
        Route::get('', [RoleController::class, 'index']);
        Route::get('/permission/tree', [RoleController::class, 'permissionTree']);
        Route::get('/menu/tree', [RoleController::class, 'menuTree']);
        Route::get('/options', [RoleController::class, 'options']);
        Route::post('/batch-delete', [RoleController::class, 'batchDelete']);
        Route::get('/{id:\d+}/permissions', [RoleController::class, 'permissions']);
        Route::put('/{id:\d+}/assign-permissions', [RoleController::class, 'assignPermissions']);
        Route::put('/{id:\d+}/status', [RoleController::class, 'status']);
        Route::get('/{id:\d+}', [RoleController::class, 'show']);
        Route::post('', [RoleController::class, 'store']);
        Route::put('/{id:\d+}', [RoleController::class, 'update']);
        Route::delete('/{id:\d+}', [RoleController::class, 'delete']);
    })->middleware($adminAuth);

    Route::group('/system/department', function () {
        Route::get('', [DepartmentController::class, 'index']);
        Route::get('/options', [DepartmentController::class, 'options']);
        Route::put('/{id:\d+}/status', [DepartmentController::class, 'status']);
        Route::get('/{id:\d+}', [DepartmentController::class, 'show']);
        Route::post('', [DepartmentController::class, 'store']);
        Route::put('/{id:\d+}', [DepartmentController::class, 'update']);
        Route::delete('/{id:\d+}', [DepartmentController::class, 'delete']);
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

    Route::group('/system/dictionary', function () {
        Route::get('', [DictionaryController::class, 'index']);
        Route::get('/options', [DictionaryController::class, 'options']);
        Route::get('/batch-options', [DictionaryController::class, 'batchOptions']);
        Route::post('/batch-delete', [DictionaryController::class, 'batchDelete']);
        Route::post('/item', [DictionaryController::class, 'storeItem']);
        Route::put('/item/{id:\d+}', [DictionaryController::class, 'updateItem']);
        Route::delete('/item/{id:\d+}', [DictionaryController::class, 'deleteItem']);
        Route::get('/{id:\d+}/items', [DictionaryController::class, 'items']);
        Route::get('/{id:\d+}', [DictionaryController::class, 'show']);
        Route::post('', [DictionaryController::class, 'store']);
        Route::put('/{id:\d+}', [DictionaryController::class, 'update']);
        Route::delete('/{id:\d+}', [DictionaryController::class, 'delete']);
    })->middleware($adminAuth);

    Route::group('/system/log', function () {
        Route::get('/login', [LogController::class, 'loginLog']);
        Route::get('/operation', [LogController::class, 'operationLog']);
        Route::post('/login/clear', [LogController::class, 'clearLoginLog']);
        Route::post('/operation/clear', [LogController::class, 'clearOperationLog']);
        Route::delete('/login/{id:\d+}', [LogController::class, 'deleteLoginLog']);
        Route::delete('/operation/{id:\d+}', [LogController::class, 'deleteOperationLog']);
    })->middleware($adminAuth);

    Route::group('/system/notification', function () {
        Route::get('', [NotificationController::class, 'index']);
        Route::get('/mine', [NotificationController::class, 'mine']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/read-all', [NotificationController::class, 'readAll']);
        Route::post('/{id:\d+}/read', [NotificationController::class, 'read']);
        Route::get('/{id:\d+}', [NotificationController::class, 'show']);
        Route::post('', [NotificationController::class, 'store']);
        Route::put('/{id:\d+}', [NotificationController::class, 'update']);
        Route::delete('/{id:\d+}', [NotificationController::class, 'delete']);
    })->middleware($adminAuth);

    Route::group('/dashboard', function () {
        Route::get('/stats', [DashboardController::class, 'stats']);
        Route::get('/recent-logs', [DashboardController::class, 'recentLogs']);
        Route::get('/recent-activities', [DashboardController::class, 'recentActivities']);
        Route::get('/active-ranking', [DashboardController::class, 'activeRanking']);
    })->middleware($adminAuth);

    Route::group('/system/file', function () {
        Route::get('', [FileController::class, 'index']);
        Route::get('/groups', [FileController::class, 'groups']);
        Route::post('/move-group', [FileController::class, 'moveGroup']);
        Route::post('/batch-delete', [FileController::class, 'batchDelete']);
        Route::put('/{id:\d+}/rename', [FileController::class, 'rename']);
        Route::delete('/{id:\d+}', [FileController::class, 'delete']);
    })->middleware($adminAuth);

    Route::group('/system/generator', function () {
        Route::get('/tables', [GeneratorController::class, 'tables']);
        Route::get('/columns', [GeneratorController::class, 'columns']);
    })->middleware($adminAuth);

    Route::group('/upload', function () {
        Route::post('/image', [UploadController::class, 'image']);
        Route::post('/file', [UploadController::class, 'file']);
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
