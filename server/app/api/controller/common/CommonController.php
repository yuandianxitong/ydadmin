<?php

declare(strict_types=1);

namespace app\api\controller\common;

use app\middleware\ApiAuthMiddleware;
use app\service\common\CommonConfigService;
use app\service\system\UploadService;
use app\service\user\SmsCodeService;
use core\base\Controller;
use core\http\ClientIp;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\annotation\Middleware;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use support\Response;
use Webman\Http\Request;

/**
 * C 端公共接口（契约 §6.2）。M5a 只有一条，M6a 新增一条：
 *
 *   POST /api/common/sms-code        smsCode      公开（不挂 ApiAuthMiddleware）
 *   GET  /api/common/config          config       公开
 *   POST /api/common/upload/image    uploadImage  已登录会员
 *
 * C 端控制器一律 #[PermissionSkip]（计划「设计决定」第 2 条）：权限点体系是管理端的，
 * C 端的准入由路由组挂不挂 ApiAuthMiddleware 决定。也不经 AdminLogMiddleware，不登记操作日志文案。
 */
#[RouteGroup('/api/common')]
class CommonController extends Controller
{
    #[Inject]
    protected SmsCodeService $smsCodeService;

    #[Inject]
    protected CommonConfigService $commonConfigService;

    #[Inject]
    protected UploadService $uploadService;

    #[Post('/sms-code')]
    #[PermissionSkip]
    public function smsCode(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->smsCodeRules(), $this->smsCodeMessages());
        // scene 可选，缺省 login：两个 C 端前端的类型都是 scene?（uniapp/src/api/auth.ts、pc 同形），
        // TP8 原实现也是 param('scene', 'login')。默认值在控制器归一——服务层只认白名单内的显式 scene。
        $scene = (string) ($data['scene'] ?? 'login');

        // IP 由控制器取（它手上才有 Request）再传给服务：core\http\ClientIp::resolve() 只在直连地址是
        // 可信代理时才信任 X-Forwarded-For，避免伪造该头绕过按 IP 的限流（与 LoginRateLimitMiddleware 同一先例）。
        $this->smsCodeService->send((string) $data['mobile'], $scene, ClientIp::resolve($request));

        return $this->success([], lang('messages.sms_code_sent'));
    }

    #[Get('/config')]
    #[PermissionSkip]
    public function config(): Response
    {
        return $this->success($this->commonConfigService->publicConfig(), lang('messages.get_success'));
    }

    /**
     * 会员上传图片（头像、反馈）。校验和落盘与管理端同一套，upload_by 保持 0：
     * acting user 只代表管理员，不把会员 id 写进那一列。
     */
    #[Middleware(ApiAuthMiddleware::class)]
    #[Post('/upload/image')]
    #[PermissionSkip]
    public function uploadImage(Request $request): Response
    {
        return $this->success($this->uploadService->uploadImage($request->file('file')), lang('messages.upload_success'));
    }

    /**
     * scene 的白名单直接取自服务的常量：白名单只有一处定义，扩场景时不会漏改文档与校验
     * （API 文档按 {action}Rules() 反射求值，看到的就是这份实时规则）。
     *
     * @return array<string, string>
     */
    private function smsCodeRules(): array
    {
        return [
            'mobile' => 'required|regex:/^1[3-9]\d{9}$/',
            // sometimes 而不是 required：不传就走默认的 login；传了（哪怕是空串）就必须在白名单里
            'scene'  => 'sometimes|string|in:' . implode(',', SmsCodeService::SCENES),
        ];
    }

    /** @return array<string, string> 值即 lang key（ValidatorFactory 会翻译） */
    private function smsCodeMessages(): array
    {
        return [
            'mobile.required' => 'validation.mobile_require',
            'mobile.regex'    => 'validation.mobile_format',
            'scene.string'    => 'validation.sms_scene_invalid',
            'scene.in'        => 'validation.sms_scene_invalid',
        ];
    }
}
