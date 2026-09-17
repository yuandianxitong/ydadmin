<?php

use app\adminapi\controller\auth\AuthController;
use app\api\controller\auth\AuthController as ApiAuthController;
use app\api\controller\auth\WechatAuthController;
use app\api\controller\payment\PaymentController;
use app\api\controller\payment\PaymentNotifyController;
use app\api\controller\user\UserController;
use app\api\controller\wechat\WechatController;
use app\adminapi\controller\dashboard\DashboardController;
use app\adminapi\controller\HealthController;
use app\adminapi\controller\message\MessageLogController;
use app\adminapi\controller\message\MessageTemplateController;
use app\adminapi\controller\system\AdminController;
use app\adminapi\controller\system\ApiDocController;
use app\adminapi\controller\system\CronJobController;
use app\adminapi\controller\system\DepartmentController;
use app\adminapi\controller\system\DictionaryController;
use app\adminapi\controller\system\FileController;
use app\adminapi\controller\system\GeneratorController;
use app\adminapi\controller\system\LogController;
use app\adminapi\controller\system\MenuController;
use app\adminapi\controller\system\NotificationController;
use app\adminapi\controller\system\OnlineController;
use app\adminapi\controller\system\RoleController;
use app\adminapi\controller\system\SystemConfigController;
use app\adminapi\controller\realtime\WsTicketController;
use app\adminapi\controller\upload\UploadController;
use app\adminapi\controller\user\UserManageController;
use app\api\controller\common\CommonController;
use app\controller\SpaController;
use app\middleware\AdminAuthMiddleware;
use app\middleware\AdminLogMiddleware;
use app\middleware\AdminPermissionMiddleware;
use app\middleware\ApiAuthMiddleware;
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

// C 端认证（M5a）：只有这一层。权限点体系是管理端的，C 端控制器一律 #[PermissionSkip]；
// C 端也不记管理端操作日志，所以不挂 AdminLogMiddleware（config/admin_log.php 里不登记 C 端动作）。
$apiAuth = [ApiAuthMiddleware::class];

Route::group('/adminapi', function () use ($adminAuth) {
    // ---- 公开路由（与 Test6 的白名单保持一致）
    Route::get('/health', [HealthController::class, 'index']);
    Route::get('/auth/captcha', [AuthController::class, 'captcha']);
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware([LoginRateLimitMiddleware::class]);

    // ---- M2b：API 文档。生产环境（APP_DEBUG=false）整个功能都不存在——最诚实的生产闸门实现，
    // 没有可探测的面，也不依赖控制器内部判断没被绕过（与 M2a GeneratorService::assertWritesEnabled()
    // 不同：那里生产仍要保留只读端点，只关写操作；这里整个功能都不该在生产出现，粒度不同）。
    // 两条都必须公开（Test6 白名单已加）：前端「Swagger UI」「下载 JSON」按钮走 window.open，
    // 浏览器导航带不了 Authorization 头。代价被这个 if 兜住：生产环境的公开路由数量仍是三条。
    if (config('app.debug')) {
        Route::get('/system/api-doc/openapi.json', [ApiDocController::class, 'openapi']);
        Route::get('/system/api-doc', [ApiDocController::class, 'index']);
    }

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
        Route::get('/admin-options', [NotificationController::class, 'adminOptions']);
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
        Route::post('/preview', [GeneratorController::class, 'preview']);
        Route::post('/generate', [GeneratorController::class, 'generate']);
    })->middleware($adminAuth);

    Route::group('/system/cron-job', function () {
        Route::get('', [CronJobController::class, 'index']);
        Route::get('/{id:\d+}/logs', [CronJobController::class, 'logs']);
        Route::post('/{id:\d+}/clear-logs', [CronJobController::class, 'clearLogs']);
        Route::put('/{id:\d+}/status', [CronJobController::class, 'status']);
        Route::post('/{id:\d+}/run', [CronJobController::class, 'run']);
        Route::get('/{id:\d+}', [CronJobController::class, 'show']);
        Route::post('', [CronJobController::class, 'store']);
        Route::put('/{id:\d+}', [CronJobController::class, 'update']);
        Route::delete('/{id:\d+}', [CronJobController::class, 'delete']);
    })->middleware($adminAuth);

    Route::group('/system/online', function () {
        Route::get('', [OnlineController::class, 'index']);
        Route::post('/{adminId:\d+}/logout', [OnlineController::class, 'logout']);
    })->middleware($adminAuth);

    // ---- M4：WebSocket 握手票据（登录即可；按管理员限流）
    Route::post('/ws/ticket', [WsTicketController::class, 'store'])->middleware($adminAuth);

    Route::group('/upload', function () {
        Route::post('/image', [UploadController::class, 'image']);
        Route::post('/file', [UploadController::class, 'file']);
    })->middleware($adminAuth);

    // ---- M5a：管理端会员管理。具名路由写在 {id} 通配路由之前
    Route::group('/user', function () {
        Route::get('/list', [UserManageController::class, 'index']);
        Route::get('/balance-logs', [UserManageController::class, 'balanceLogs']);
        Route::get('/points-logs', [UserManageController::class, 'pointsLogs']);
        Route::post('/adjust-balance', [UserManageController::class, 'adjustBalance']);
        Route::post('/adjust-points', [UserManageController::class, 'adjustPoints']);
        Route::get('/detail/{id:\d+}', [UserManageController::class, 'detail']);
        Route::put('/{id:\d+}/status', [UserManageController::class, 'updateStatus']);
    })->middleware($adminAuth);

    // ---- M6b：消息模板与消息日志（spec §4.1、§4.2）。具名路由写在 {id} 通配路由之前
    Route::group('/message', function () {
        Route::get('/template', [MessageTemplateController::class, 'index']);
        Route::get('/template/{id:\d+}', [MessageTemplateController::class, 'show']);
        Route::post('/template', [MessageTemplateController::class, 'store']);
        Route::put('/template/{id:\d+}', [MessageTemplateController::class, 'update']);
        Route::delete('/template/{id:\d+}', [MessageTemplateController::class, 'delete']);
        Route::get('/log', [MessageLogController::class, 'index']);
    })->middleware($adminAuth);

    // 生成的模块路由：代码生成器每个模块产出一个文件，这里统一 require。
    // 被 require 的文件在本闭包体内执行（require 不新开作用域），因此文件里能直接用外层 use
    // 进来的 $adminAuth——这也是它必须被 require 进这个闭包、而不是在别处独立注册的原因。
    foreach (glob(config_path() . '/route/*.php') ?: [] as $moduleRoute) {
        require $moduleRoute;
    }
})->middleware($apiOuter);

// ---- M5a：C 端 /api。与 /adminapi 并列，外层中间件相同；公开段与认证段分开写。
// 认证段按子组挂 $apiAuth（与 /adminapi 下各子组挂 $adminAuth 同一写法），不预先建一个空的无前缀组。
Route::group('/api', function () use ($apiAuth) {
    // ---- 公开段（不挂认证）
    Route::post('/common/sms-code', [CommonController::class, 'smsCode']);
    // M6a：C 端公开配置（spec §4.9），固定白名单
    Route::get('/common/config', [CommonController::class, 'config']);
    // 复用管理端那套登录限流（最终评审第 2 条）：C 端口令规则只有 min:6，公开接口再没有锁定、没有计数、
    // 失败也不写日志的话，撞库的成本几乎为零。中间件按「IP + 账号」计数，账号字段兼容 account / mobile。
    Route::post('/auth/login', [ApiAuthController::class, 'login'])->middleware([LoginRateLimitMiddleware::class]);
    Route::post('/auth/register', [ApiAuthController::class, 'register']);
    Route::post('/auth/sms-login', [ApiAuthController::class, 'smsLogin']);

    // M6a：C 端微信登录（spec §4）。公开——换 token 的就是这些端点本身，没有会员 token 可带。
    Route::post('/auth/wechat-web-login', [WechatAuthController::class, 'webLogin']);
    Route::post('/auth/wechat-login', [WechatAuthController::class, 'miniLogin']);
    Route::post('/auth/wechat-quick-login', [WechatAuthController::class, 'quickLogin']);
    Route::post('/auth/wechat-bindphone', [WechatAuthController::class, 'bindPhone']);

    // M6a：公众号静默登录与网页授权地址（spec §4.6、§4.7）。公开：用户此时还没有会员 token。
    Route::post('/auth/wechat-h5-login', [WechatAuthController::class, 'h5Login']);
    Route::get('/wechat/oauth-url', [WechatController::class, 'oauthUrl']);

    // M5b：支付回调（spec §5.3）。公开、不挂 $apiAuth——渠道服务器没有会员 token，凭签名证明身份。
    // 应答不走统一响应体，由 PaymentNotifyController 按渠道原样返回。
    Route::post('/payment/notify/wechat', [PaymentNotifyController::class, 'wechat']);
    Route::post('/payment/notify/alipay', [PaymentNotifyController::class, 'alipay']);

    // ---- 认证段（挂 $apiAuth，逐请求比对 token 里的 ver）
    Route::group('/auth', function () {
        Route::post('/refresh-token', [ApiAuthController::class, 'refreshToken']);
        Route::get('/info', [ApiAuthController::class, 'info']);
        Route::post('/logout', [ApiAuthController::class, 'logout']);
    })->middleware($apiAuth);

    Route::group('/user', function () {
        Route::get('/profile', [UserController::class, 'profile']);
        Route::put('/profile', [UserController::class, 'updateProfile']);
        Route::put('/change-password', [UserController::class, 'changePassword']);
        Route::get('/balance', [UserController::class, 'balance']);
        Route::get('/balance-logs', [UserController::class, 'balanceLogs']);
        Route::get('/points', [UserController::class, 'points']);
        Route::get('/points-logs', [UserController::class, 'pointsLogs']);
        Route::post('/recharge', [UserController::class, 'recharge']);
        Route::post('/bind-oa-openid', [UserController::class, 'bindOaOpenid']);
    })->middleware($apiAuth);

    // M5b：支付订单查询（回调在公开段，由 Task 9 注册）
    Route::group('/payment', function () {
        Route::get('/query', [PaymentController::class, 'query']);
    })->middleware($apiAuth);
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
