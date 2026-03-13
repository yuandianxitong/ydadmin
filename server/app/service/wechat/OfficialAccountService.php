<?php
declare(strict_types=1);

namespace app\service\wechat;

use core\base\Service;
use core\wechat\WechatManager;
use core\exception\BusinessException;

class OfficialAccountService extends Service
{
    protected AutoReplyService $autoReplyService;

    /**
     * 获取网页授权 URL
     * @param string $redirectUrl 回调地址
     * @param string $scope snsapi_base / snsapi_userinfo
     * @return string
     */
    public function getOAuthUrl(string $redirectUrl, string $scope = 'snsapi_userinfo'): string
    {
        $app = WechatManager::officialAccount();
        $oauth = $app->getOAuth();

        return $oauth->redirect($redirectUrl, $scope);
    }

    /**
     * 通过授权 code 获取用户信息
     */
    public function getUserByCode(string $code): array
    {
        $app = WechatManager::officialAccount();
        $oauth = $app->getOAuth();
        $user = $oauth->userFromCode($code);

        return [
            'openid'   => $user->getId(),
            'nickname' => $user->getNickname(),
            'avatar'   => $user->getAvatar(),
            'raw'      => $user->getRaw(),
        ];
    }

    /**
     * 获取自定义菜单
     */
    public function getMenu(): array
    {
        $app = WechatManager::officialAccount();
        $api = $app->getClient();
        $response = $api->get('/cgi-bin/get_current_selfmenu_info');
        $result = $response->toArray();
        $this->checkResponse($result);

        return $result;
    }

    /**
     * 创建自定义菜单
     */
    public function createMenu(array $buttons): array
    {
        $app = WechatManager::officialAccount();
        $api = $app->getClient();
        $response = $api->postJson('/cgi-bin/menu/create', ['button' => $buttons]);
        $result = $response->toArray();
        $this->checkResponse($result);

        return $result;
    }

    /**
     * 删除自定义菜单
     */
    public function deleteMenu(): array
    {
        $app = WechatManager::officialAccount();
        $api = $app->getClient();
        $response = $api->get('/cgi-bin/menu/delete');
        $result = $response->toArray();
        $this->checkResponse($result);

        return $result;
    }

    /**
     * 发送模板消息
     */
    public function sendTemplateMessage(string $openid, string $templateId, array $data, string $url = ''): array
    {
        $app = WechatManager::officialAccount();
        $api = $app->getClient();

        $message = [
            'touser'      => $openid,
            'template_id' => $templateId,
            'data'        => $data,
        ];

        if ($url) {
            $message['url'] = $url;
        }

        $response = $api->postJson('/cgi-bin/message/template/send', $message);
        $result = $response->toArray();
        $this->checkResponse($result);

        return $result;
    }

    /**
     * 获取粉丝列表
     */
    public function getUserList(string $nextOpenid = ''): array
    {
        $app = WechatManager::officialAccount();
        $api = $app->getClient();

        $query = $nextOpenid ? ['next_openid' => $nextOpenid] : [];
        $response = $api->get('/cgi-bin/user/get', $query);
        $result = $response->toArray();
        $this->checkResponse($result);

        return $result;
    }

    /**
     * 获取用户基本信息
     */
    public function getUserInfo(string $openid): array
    {
        $app = WechatManager::officialAccount();
        $api = $app->getClient();
        $response = $api->get('/cgi-bin/user/info', ['openid' => $openid, 'lang' => 'zh_CN']);
        $result = $response->toArray();
        $this->checkResponse($result);

        return $result;
    }

    /**
     * 处理服务器消息/事件推送
     */
    public function handleServerRequest(): \Psr\Http\Message\ResponseInterface
    {
        $app = WechatManager::officialAccount();
        $server = $app->getServer();

        // 关注事件 — 从自动回复表查询，无配置则使用默认
        $server->addEventListener('subscribe', function ($message) {
            return $this->autoReplyService->getSubscribeReply() ?? '感谢关注！';
        });

        // 文本消息 — 先匹配关键词，未命中则使用默认回复
        $server->addMessageListener('text', function ($message) {
            $content = $message['Content'] ?? '';

            // 关键词匹配
            $reply = $this->autoReplyService->matchKeyword($content);
            if ($reply !== null) {
                return $reply;
            }

            // 默认回复
            return $this->autoReplyService->getDefaultReply() ?? '';
        });

        return $server->serve();
    }

    /**
     * 校验微信 API 响应
     */
    protected function checkResponse(array $result): void
    {
        $errcode = $result['errcode'] ?? 0;
        if ($errcode !== 0) {
            throw new BusinessException(lang('business.wechat_api_error') . ': ' . ($result['errmsg'] ?? '') . " (errcode: {$errcode})");
        }
    }
}
