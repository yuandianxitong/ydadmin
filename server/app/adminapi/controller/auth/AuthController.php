<?php

declare(strict_types=1);

namespace app\adminapi\controller\auth;

use app\service\common\CaptchaService;
use app\service\system\AdminService;
use app\service\system\MenuService;
use app\service\system\SystemConfigService;
use core\auth\TokenManager;
use core\auth\TokenVersion;
use core\base\Controller;
use core\context\RequestContext;
use core\exception\BusinessException;
use core\http\ClientIp;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/** 认证（契约 §2.1）。captcha/login 在公开组；info/refresh/logout 在认证组，登录即可访问。 */
class AuthController extends Controller
{
    #[Inject]
    protected AdminService $adminService;

    #[Inject]
    protected CaptchaService $captchaService;

    #[Inject]
    protected MenuService $menuService;

    #[Inject]
    protected SystemConfigService $systemConfigService;

    #[PermissionSkip]
    public function captcha(): Response
    {
        return $this->success($this->captchaService->generate(), lang('messages.get_success'));
    }

    #[PermissionSkip]
    public function login(Request $request): Response
    {
        // login_captcha 关闭时验证码可不填（spec §1.1 差异 2：TP8 忽略该配置、强制必填）。
        // 只有明确的关闭值才算关：认不出的值按「开」算，不能因为配置被写坏就静默放行（isSecuritySwitchOn()）
        $captchaRequired = $this->systemConfigService->isSecuritySwitchOn('login_captcha');
        $data = $this->validate($this->body($request), $this->loginRules(), $this->loginMessages());
        if ($captchaRequired && !$this->captchaService->verify((string) $data['captcha_key'], (string) $data['captcha'])) {
            throw new BusinessException(lang('auth.captcha_invalid'));
        }

        $result = $this->adminService->login(
            (string) $data['username'],
            (string) $data['password'],
            ClientIp::resolve($request),
            (string) $request->header('user-agent', '')
        );

        return $this->success($result, lang('messages.login_success'));
    }

    #[PermissionSkip]
    public function info(): Response
    {
        $admin = $this->adminService->getSelfInfo(RequestContext::actingUser());

        return $this->success([
            'admin'       => $admin,
            'routes'      => $this->menuService->getFrontendRoutes((array) $admin['menu_ids']),
            'permissions' => $admin['permissions'],
        ], lang('messages.get_success'));
    }

    #[PermissionSkip]
    public function refresh(Request $request): Response
    {
        $mgr = TokenManager::scope('admin');
        $token = (string) $mgr->getTokenFromHeader($request);
        $newToken = $mgr->refresh($token, ['ver' => TokenVersion::current(RequestContext::actingUser())]);

        return $this->success(['token' => $newToken], lang('messages.refresh_success'));
    }

    #[PermissionSkip]
    public function logout(Request $request): Response
    {
        $mgr = TokenManager::scope('admin');
        $token = $mgr->getTokenFromHeader($request);
        if ($token !== null) {
            $mgr->blacklist($token);
        }

        return $this->success([], lang('messages.logout_success'));
    }

    /** @return array<string, string> 与请求管道同一路径：按当前配置求值 */
    private function loginRules(): array
    {
        return $this->loginRulesFor($this->systemConfigService->isSecuritySwitchOn('login_captcha'));
    }

    /** @return array<string, string> */
    private function loginRulesFor(bool $captchaRequired): array
    {
        $presence = $captchaRequired ? 'required' : 'nullable';

        return [
            'username'    => 'required|string|min:3|max:50',
            'password'    => 'required|string|min:6|max:20',
            'captcha'     => "{$presence}|string|min:4|max:6",
            'captcha_key' => "{$presence}|string",
        ];
    }

    /** @return array<string, string> 值即 lang key（ValidatorFactory 会翻译） */
    private function loginMessages(): array
    {
        return [
            'username.required'    => 'validation.username_require',
            'username.min'         => 'validation.username_length_3_50',
            'username.max'         => 'validation.username_length_3_50',
            'password.required'    => 'validation.password_require',
            'password.min'         => 'validation.password_length',
            'password.max'         => 'validation.password_length',
            'captcha.required'     => 'validation.captcha_require',
            'captcha.min'          => 'validation.captcha_length',
            'captcha.max'          => 'validation.captcha_length',
            'captcha_key.required' => 'validation.captcha_require',
        ];
    }
}
