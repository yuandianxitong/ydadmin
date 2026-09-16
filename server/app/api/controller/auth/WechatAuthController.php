<?php

declare(strict_types=1);

namespace app\api\controller\auth;

use app\service\wechat\WechatAuthService;
use app\service\wechat\WechatOaBindCookie;
use core\base\Controller;
use core\exception\BusinessException;
use core\http\ClientIp;
use core\permission\PermissionSkip;
use core\wechat\exception\WechatNotConfiguredException;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/**
 * C 端微信登录（M6a spec §4）。全部公开——换 token 的就是这些端点本身；C 端没有权限体系，方法一律 #[PermissionSkip]。
 *
 * 端点：
 *   POST /api/auth/wechat-web-login   webLogin    PC 扫码（开放平台）
 *   POST /api/auth/wechat-login       miniLogin   小程序静默登录
 *   POST /api/auth/wechat-quick-login quickLogin  小程序快捷登录（未命中返回 need_bindphone + temp_token）
 *   POST /api/auth/wechat-bindphone   bindPhone   用 temp_token + 手机号授权码完成登录
 *   POST /api/auth/wechat-h5-login    h5Login     公众号静默登录（未绑定返回 need_login，并下发绑定证明 cookie）
 */
class WechatAuthController extends Controller
{
    #[Inject]
    protected WechatAuthService $wechatAuthService;

    #[Inject]
    protected WechatOaBindCookie $oaBindCookie;

    #[PermissionSkip]
    public function webLogin(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->webLoginRules(), $this->codeMessages());

        return $this->success(
            $this->wechatAuthService->webLogin((string) $data['code'], ClientIp::resolve($request)),
            lang('messages.login_success')
        );
    }

    #[PermissionSkip]
    public function miniLogin(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->miniLoginRules(), $this->codeMessages());

        return $this->success(
            $this->wechatAuthService->miniLogin((string) $data['code'], ClientIp::resolve($request)),
            lang('messages.login_success')
        );
    }

    #[PermissionSkip]
    public function quickLogin(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->quickLoginRules(), $this->codeMessages());

        return $this->success(
            $this->wechatAuthService->quickLogin((string) $data['code'], ClientIp::resolve($request)),
            lang('messages.login_success')
        );
    }

    #[PermissionSkip]
    public function bindPhone(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->bindPhoneRules(), $this->bindPhoneMessages());

        return $this->success(
            $this->wechatAuthService->bindPhone((string) $data['temp_token'], (string) $data['phone_code'], ClientIp::resolve($request)),
            lang('messages.login_success')
        );
    }

    /**
     * 未绑定时在同一响应下发绑定证明 cookie（spec §5）。用户 JWT 密钥为空签不出证明：回「微信登录未配置」，
     * 不让 WechatNotConfiguredException 漏成 HTTP 500，也不下发 cookie。
     */
    #[PermissionSkip]
    public function h5Login(Request $request): Response
    {
        $data = $this->validate($this->body($request), $this->h5LoginRules(), $this->codeMessages());
        $result = $this->wechatAuthService->h5Login((string) $data['code'], ClientIp::resolve($request));
        $response = $this->success($result, lang('messages.login_success'));
        if ($result['status'] !== 'need_login') {
            return $response;
        }

        try {
            return $this->oaBindCookie->attach($response, $result['openid']);
        } catch (WechatNotConfiguredException) {
            throw new BusinessException(lang('wechat.not_configured'));
        }
    }

    /** @return array<string, string> */
    private function webLoginRules(): array
    {
        return ['code' => 'required|string|max:128'];
    }

    /** @return array<string, string> */
    private function miniLoginRules(): array
    {
        return ['code' => 'required|string|max:128'];
    }

    /** @return array<string, string> */
    private function quickLoginRules(): array
    {
        return ['code' => 'required|string|max:128'];
    }

    /** @return array<string, string> */
    private function h5LoginRules(): array
    {
        return ['code' => 'required|string|max:128'];
    }

    /** @return array<string, string> */
    private function bindPhoneRules(): array
    {
        return [
            'temp_token' => 'required|string|size:32',
            'phone_code' => 'required|string|max:128',
        ];
    }

    /** @return array<string, string> */
    private function bindPhoneMessages(): array
    {
        return [
            'temp_token.required' => 'validation.temp_token_invalid',
            'temp_token.string'   => 'validation.temp_token_invalid',
            'temp_token.size'     => 'validation.temp_token_invalid',
            'phone_code.required' => 'validation.phone_code_require',
            'phone_code.string'   => 'validation.phone_code_require',
            'phone_code.max'      => 'validation.phone_code_require',
        ];
    }

    /** @return array<string, string> */
    private function codeMessages(): array
    {
        return [
            'code.required' => 'validation.wechat_code_require',
            'code.string'   => 'validation.wechat_code_require',
            'code.max'      => 'validation.wechat_code_require',
        ];
    }
}
