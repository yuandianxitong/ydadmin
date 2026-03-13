<?php
declare(strict_types=1);

namespace app\api\controller\v1\wechat;

use core\base\Controller;
use app\service\wechat\OfficialAccountService;
use app\service\wechat\MiniAppService;
use think\Response;

class WechatController extends Controller
{
    protected OfficialAccountService $officialAccountService;
    protected MiniAppService $miniAppService;

    /**
     * 微信公众号服务端验证 & 消息回调
     * GET  请求：URL 接入验证（echostr）
     * POST 请求：接收消息/事件推送
     */
    public function serve(): Response
    {
        try {
            // GET 请求为 URL 接入验证
            if ($this->request->isGet()) {
                $echostr = $this->request->param('echostr', '');
                return response($echostr, 200, ['Content-Type' => 'text/plain']);
            }

            $psr7Response = $this->officialAccountService->handleServerRequest();

            return response(
                (string)$psr7Response->getBody(),
                $psr7Response->getStatusCode(),
                ['Content-Type' => 'application/xml']
            );
        } catch (\Exception $e) {
            return response('success', 200, ['Content-Type' => 'text/plain']);
        }
    }

    /**
     * 获取公众号 OAuth 授权跳转 URL
     */
    public function oauthUrl(): Response
    {
        try {
            $redirectUrl = (string)$this->request->param('redirect_url', '');
            $scope = (string)$this->request->param('scope', 'snsapi_userinfo');

            if (empty($redirectUrl)) {
                return $this->error(lang('business.missing_redirect_url'));
            }

            $url = $this->officialAccountService->getOAuthUrl($redirectUrl, $scope);

            return $this->success(lang('messages.get_success'), ['url' => $url]);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 公众号 OAuth 回调 — 通过 code 获取用户信息
     */
    public function oauthCallback(): Response
    {
        try {
            $code = (string)$this->request->param('code', '');
            if (empty($code)) {
                return $this->error(lang('business.missing_code'));
            }

            $user = $this->officialAccountService->getUserByCode($code);

            return $this->success(lang('messages.authorize_success'), $user);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 小程序解密手机号
     */
    public function decryptPhone(): Response
    {
        try {
            $code = (string)$this->request->param('code', '');
            if (empty($code)) {
                return $this->error(lang('business.missing_code'));
            }

            $phone = $this->miniAppService->decryptPhoneNumber($code);

            return $this->success(lang('messages.get_success'), $phone);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
