<?php

use app\adminapi\controller\auth\AuthController;
use app\api\controller\auth\AuthController as ApiAuthController;
use app\api\controller\auth\WechatAuthController;
use app\api\controller\payment\PaymentController;
use app\api\controller\payment\PaymentNotifyController;
use app\api\controller\user\UserController;
use app\api\controller\wechat\WechatController;
use app\adminapi\controller\HealthController;
use app\adminapi\controller\system\ApiDocController;
use app\adminapi\controller\region\RegionController;
use app\api\controller\agreement\AgreementController as ApiAgreementController;
use app\api\controller\announcement\AnnouncementController as ApiAnnouncementController;
use app\api\controller\article\ArticleCategoryController as ApiArticleCategoryController;
use app\api\controller\article\ArticleController as ApiArticleController;
use app\api\controller\common\CommonController;
use app\api\controller\feedback\FeedbackController as ApiFeedbackController;
use app\api\controller\message\MessageController;
use app\api\controller\region\RegionController as ApiRegionController;
use app\api\controller\mobile\DiyPageController as ApiDiyPageController;
use app\api\controller\mobile\MobileConfigController as ApiMobileConfigController;
use app\api\controller\version\VersionController as ApiVersionController;
use app\controller\InstallController;
use app\controller\SpaController;
use app\middleware\AdminAuthMiddleware;
use app\middleware\AdminLogMiddleware;
use app\middleware\AdminPermissionMiddleware;
use app\middleware\ApiAuthMiddleware;
use app\middleware\LoginRateLimitMiddleware;
use core\response\Api;
use Webman\Route;

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

    // M7b：级联选择器（admin Region 组件走 /common/regions；与 /region/tree 同一动作）
    Route::get('/common/regions', [RegionController::class, 'tree'])->middleware($adminAuth);

    // 生成的模块路由：代码生成器每个模块产出一个文件，这里统一 require。
    // 被 require 的文件在本闭包体内执行（require 不新开作用域），因此文件里能直接用外层 use
    // 进来的 $adminAuth——这也是它必须被 require 进这个闭包、而不是在别处独立注册的原因。
    foreach (glob(config_path() . '/route/*.php') ?: [] as $moduleRoute) {
        require $moduleRoute;
    }
});

// ---- M5a：C 端 /api。与 /adminapi 并列，外层中间件相同；公开段与认证段分开写。
// 认证段按子组挂 $apiAuth（与 /adminapi 下各子组挂 $adminAuth 同一写法），不预先建一个空的无前缀组。
Route::group('/api', function () use ($apiAuth) {
    // ---- 公开段（不挂认证）
    Route::post('/common/sms-code', [CommonController::class, 'smsCode']);
    // M6a：C 端公开配置（spec §4.9），固定白名单
    Route::get('/common/config', [CommonController::class, 'config']);
    // 会员上传图片：必须登录。头像和反馈都带 user token。
    Route::post('/common/upload/image', [CommonController::class, 'uploadImage'])->middleware($apiAuth);
    Route::get('/article/list', [ApiArticleController::class, 'list']);
    Route::get('/article/detail/{id:\d+}', [ApiArticleController::class, 'detail']);
    Route::get('/article-category/list', [ApiArticleCategoryController::class, 'list']);
    Route::get('/region/tree', [ApiRegionController::class, 'tree']);
    Route::get('/region/children', [ApiRegionController::class, 'children']);
    Route::get('/version/check', [ApiVersionController::class, 'check']);
    Route::get('/mobile/diy-page', [ApiDiyPageController::class, 'show']);
    Route::get('/mobile/config', [ApiMobileConfigController::class, 'show']);
    Route::get('/announcement/list', [ApiAnnouncementController::class, 'list']);
    Route::get('/announcement/detail/{id:\d+}', [ApiAnnouncementController::class, 'detail']);
    Route::get('/agreement/{code:[a-z][a-z0-9_]{1,49}}', [ApiAgreementController::class, 'show']);
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
    Route::add(['GET', 'POST'], '/wechat/serve', [WechatController::class, 'serve']);

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

    // M6b：C 端站内信（spec §4.7）。只读写本人的通知，身份取自 token
    Route::group('/message', function () {
        Route::get('/list', [MessageController::class, 'list']);
        Route::get('/unread-count', [MessageController::class, 'unreadCount']);
        Route::post('/read', [MessageController::class, 'read']);
    })->middleware($apiAuth);

    // M7a：C 端反馈。只读写本人的行，身份取自 token；管理端不得注册创建路由
    Route::group('/feedback', function () {
        Route::post('/submit', [ApiFeedbackController::class, 'submit']);
        Route::get('/list', [ApiFeedbackController::class, 'list']);
        Route::get('/detail/{id:\d+}', [ApiFeedbackController::class, 'detail']);
    })->middleware($apiAuth);
});

// M8：浏览器安装向导。挂在 /adminapi、/api 之外（不进认证组、不进 config/route/*.php 的 adminapi glob）。
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
