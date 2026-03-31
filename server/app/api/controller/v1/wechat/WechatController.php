<?php
declare(strict_types=1);

namespace app\api\controller\v1\wechat;

use core\base\Controller;
use app\service\wechat\OfficialAccountService;
use app\service\wechat\MiniAppService;
use app\service\user\UserService;
use think\Response;

class WechatController extends Controller
{
    protected OfficialAccountService $officialAccountService;
    protected MiniAppService $miniAppService;
    protected UserService $userService;

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
     * 支持两种模式：
     * - API 模式：直接返回 JSON（原有行为）
     * - SPA 重定向模式：state 参数存在时，缓存 openid 并 302 重定向
     */
    public function oauthCallback(): Response
    {
        try {
            $code = (string)$this->request->param('code', '');
            $state = (string)$this->request->param('state', '');

            if (empty($code)) {
                return $this->error(lang('business.missing_code'));
            }

            $user = $this->officialAccountService->getUserByCode($code);
            $openid = $user['openid'] ?? '';
            $unionid = $user['unionid'] ?? '';

            // SPA 重定向模式
            if (!empty($state)) {
                $token = md5(uniqid((string)mt_rand(), true));
                cache('wechat_oauth_' . $token, [
                    'openid'  => $openid,
                    'unionid' => $unionid,
                ], 300);

                // 如果已登录，直接关联 oa_openid
                $userId = (int)($this->request->userId ?? 0);
                if ($userId > 0 && $openid) {
                    $this->userService->bindOaOpenid($userId, $openid);
                }

                $separator = str_contains($state, '?') ? '&' : '?';
                return redirect($state . $separator . 'wechat_token=' . $token);
            }

            // API 模式（原有行为）
            return $this->success(lang('messages.authorize_success'), $user);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 通过 wechat_token 换取 openid（前端 SPA 使用）
     */
    public function getOpenid(): Response
    {
        try {
            $token = (string)$this->request->param('token', '');
            if (empty($token)) {
                return $this->error('缺少 token 参数');
            }

            $data = cache('wechat_oauth_' . $token);
            if (empty($data)) {
                return $this->error('token 已过期，请重新授权');
            }

            // 一次性使用
            cache('wechat_oauth_' . $token, null);

            // 如果已登录，关联到用户
            $userId = (int)($this->request->userId ?? 0);
            if ($userId > 0 && !empty($data['openid'])) {
                $this->userService->bindOaOpenidAndUnionid($userId, $data['openid'], $data['unionid'] ?: null);
            }

            return $this->success('ok', $data);
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
