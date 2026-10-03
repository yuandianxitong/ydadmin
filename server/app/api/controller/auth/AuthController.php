<?php

declare(strict_types=1);

namespace app\api\controller\auth;

use app\middleware\ApiAuthMiddleware;
use app\middleware\LoginRateLimitMiddleware;
use app\service\user\UserAuthService;
use core\auth\TokenManager;
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
 * C 端认证（spec §6.2、§4.3）。
 *
 * 端点：
 *   POST /api/auth/login          login         公开
 *   POST /api/auth/register       register      公开
 *   POST /api/auth/sms-login      smsLogin      公开
 *   POST /api/auth/refresh-token  refreshToken  ApiAuthMiddleware
 *   GET  /api/auth/info           info          ApiAuthMiddleware
 *   POST /api/auth/logout         logout        ApiAuthMiddleware
 *
 * C 端没有权限体系（计划设计决定 2），全部方法标 #[PermissionSkip]，认证只由 ApiAuthMiddleware 负责。
 */
#[RouteGroup('/api/auth')]
class AuthController extends Controller
{
    #[Inject]
    protected UserAuthService $userAuthService;

    #[Middleware(LoginRateLimitMiddleware::class)]
    #[Post('/login')]
    #[PermissionSkip]
    public function login(Request $request): Response
    {
        $data = $this->validate($this->normalizeLoginBody($this->body($request)), $this->loginRules(), $this->loginMessages());

        return $this->success(
            $this->userAuthService->loginByPassword((string) $data['account'], (string) $data['password'], ClientIp::resolve($request)),
            lang('messages.login_success')
        );
    }

    #[Post('/register')]
    #[PermissionSkip]
    public function register(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->registerRules(), $this->registerMessages());

        return $this->success(
            $this->userAuthService->register((string) $data['mobile'], (string) $data['password'], (string) $data['code'], ClientIp::resolve($request)),
            lang('messages.login_success')
        );
    }

    #[Post('/sms-login')]
    #[PermissionSkip]
    public function smsLogin(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->smsLoginRules(), $this->smsLoginMessages());

        return $this->success(
            $this->userAuthService->loginBySmsCode((string) $data['mobile'], (string) $data['code'], ClientIp::resolve($request)),
            lang('messages.login_success')
        );
    }

    #[Middleware(ApiAuthMiddleware::class)]
    #[Post('/refresh-token')]
    #[PermissionSkip]
    public function refreshToken(Request $request): Response
    {
        $token = (string) TokenManager::scope('user')->getTokenFromHeader($request);

        return $this->success(['token' => $this->userAuthService->refresh($token)], lang('messages.refresh_success'));
    }

    #[Middleware(ApiAuthMiddleware::class)]
    #[Get('/info')]
    #[PermissionSkip]
    public function info(Request $request): Response
    {
        return $this->success($this->userAuthService->getSelfInfo((int) $request->userId), lang('messages.get_success'));
    }

    #[Middleware(ApiAuthMiddleware::class)]
    #[Post('/logout')]
    #[PermissionSkip]
    public function logout(Request $request): Response
    {
        $token = TokenManager::scope('user')->getTokenFromHeader($request);
        if ($token !== null) {
            $this->userAuthService->logout($token);
        }

        return $this->success([], lang('messages.logout_success'));
    }

    /**
     * pc 端登录发 account 字段，uniapp 发 mobile 字段——两端前端自己就不一致，M5a 不改前端
     * （协调者裁定 1）。以 account 为准，account 缺失但 mobile 存在时顶替；都缺失时留给下面
     * loginRules() 的 account.required 命中，errors.account。
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function normalizeLoginBody(array $body): array
    {
        if (!isset($body['account']) && isset($body['mobile'])) {
            $body['account'] = $body['mobile'];
        }

        return $body;
    }

    /** @return array<string, string> */
    private function loginRules(): array
    {
        // account 按 spec §4.3「手机号或其他账号」，不加格式限制（真正的匹配规则在 UserRepository::findByAccount）
        return [
            'account'  => 'required|string|max:50',
            'password' => 'required|string|min:6|max:20',
        ];
    }

    /** @return array<string, string> */
    private function loginMessages(): array
    {
        return [
            'account.required'  => 'validation.account_require',
            'account.string'    => 'validation.account_require',
            'password.required' => 'validation.password_require',
            'password.min'      => 'validation.password_length',
            'password.max'      => 'validation.password_length',
        ];
    }

    /**
     * 字段与 pc/uniapp 两端一致：mobile + password + password_confirmation + code（协调者裁定 2）。
     * password 追加 Laravel 内置的 confirmed：自动比对请求体里的 password_confirmation，
     * 不一致时错误挂在 password 字段上，不需要单独给 password_confirmation 写规则。
     *
     * @return array<string, string>
     */
    private function registerRules(): array
    {
        return [
            'mobile'   => 'required|string|regex:/^1[3-9]\d{9}$/',
            'password' => 'required|string|min:6|max:20|confirmed',
            'code'     => 'required|string|size:6',
        ];
    }

    /** @return array<string, string> */
    private function registerMessages(): array
    {
        return [
            'mobile.required'    => 'validation.mobile_require',
            'mobile.regex'       => 'validation.mobile_format',
            'password.required'  => 'validation.password_require',
            'password.min'       => 'validation.password_length',
            'password.max'       => 'validation.password_length',
            'password.confirmed' => 'validation.password_confirmation_mismatch',
            'code.required'      => 'validation.sms_code_require',
            'code.size'          => 'validation.sms_code_require',
        ];
    }

    /** @return array<string, string> */
    private function smsLoginRules(): array
    {
        return [
            'mobile' => 'required|string|regex:/^1[3-9]\d{9}$/',
            'code'   => 'required|string|size:6',
        ];
    }

    /** @return array<string, string> */
    private function smsLoginMessages(): array
    {
        return [
            'mobile.required' => 'validation.mobile_require',
            'mobile.regex'    => 'validation.mobile_format',
            'code.required'   => 'validation.sms_code_require',
            'code.size'       => 'validation.sms_code_require',
        ];
    }
}
