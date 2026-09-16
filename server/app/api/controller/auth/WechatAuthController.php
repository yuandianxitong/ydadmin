<?php

declare(strict_types=1);

namespace app\api\controller\auth;

use app\service\wechat\WechatAuthService;
use core\base\Controller;
use core\http\ClientIp;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/**
 * C 端微信登录（M6a spec §4）。全部公开——换 token 的就是这些端点本身；C 端没有权限体系，方法一律 #[PermissionSkip]。
 *
 * 端点：
 *   POST /api/auth/wechat-web-login   webLogin    PC 扫码（开放平台）
 *   POST /api/auth/wechat-login       miniLogin   小程序静默登录
 */
class WechatAuthController extends Controller
{
    #[Inject]
    protected WechatAuthService $wechatAuthService;

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
    private function codeMessages(): array
    {
        return [
            'code.required' => 'validation.wechat_code_require',
            'code.string'   => 'validation.wechat_code_require',
            'code.max'      => 'validation.wechat_code_require',
        ];
    }
}
