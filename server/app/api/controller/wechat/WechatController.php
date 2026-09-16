<?php

declare(strict_types=1);

namespace app\api\controller\wechat;

use app\service\wechat\WechatAuthService;
use core\base\Controller;
use core\permission\PermissionSkip;
use DI\Attribute\Inject;
use support\Response;
use Webman\Http\Request;

/**
 * C 端微信辅助接口（M6a spec §4.7）：
 *
 *   GET /api/wechat/oauth-url   oauthUrl   公开
 *
 * 1.x 的 oauth-callback / get-openid 不做：前端不调用，且那一对是开放跳转。
 */
class WechatController extends Controller
{
    #[Inject]
    protected WechatAuthService $wechatAuthService;

    #[PermissionSkip]
    public function oauthUrl(Request $request): Response
    {
        $data = $this->validate($request->get(), $this->oauthUrlRules(), $this->oauthUrlMessages());
        $scope = (string) ($data['scope'] ?? '') ?: 'snsapi_base';

        return $this->success(['url' => $this->wechatAuthService->oauthUrl((string) $data['redirect_url'], $scope)], lang('messages.get_success'));
    }

    /** @return array<string, string> */
    private function oauthUrlRules(): array
    {
        return [
            'redirect_url' => 'required|string|max:500',
            'scope'        => 'nullable|string|in:snsapi_base,snsapi_userinfo',
        ];
    }

    /** @return array<string, string> */
    private function oauthUrlMessages(): array
    {
        return [
            'redirect_url.required' => 'validation.oauth_redirect_url_require',
            'redirect_url.string'   => 'validation.oauth_redirect_url_require',
            'redirect_url.max'      => 'validation.oauth_redirect_url_require',
            'scope.string'          => 'validation.oauth_scope_invalid',
            'scope.in'              => 'validation.oauth_scope_invalid',
        ];
    }
}
