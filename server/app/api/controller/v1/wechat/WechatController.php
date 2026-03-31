<?php
declare(strict_types=1);

namespace app\api\controller\v1\wechat;

use core\base\Controller;
use app\service\wechat\OfficialAccountService;
use app\service\wechat\MiniAppService;
use app\service\user\UserService;
use think\Response;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '微信', description: '微信公众号 OAuth、消息服务、小程序手机号')]
class WechatController extends Controller
{
    protected OfficialAccountService $officialAccountService;
    protected MiniAppService $miniAppService;
    protected UserService $userService;

    #[OA\Get(
        path: '/wechat/serve',
        summary: '微信公众号 URL 接入验证',
        tags: ['微信'],
        parameters: [
            new OA\Parameter(name: 'echostr', in: 'query', description: '微信验证字符串', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'echostr 回显'),
        ]
    )]
    #[OA\Post(
        path: '/wechat/serve',
        summary: '微信公众号消息/事件推送回调',
        tags: ['微信'],
        responses: [
            new OA\Response(response: 200, description: 'success'),
        ]
    )]
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

    #[OA\Get(
        path: '/wechat/oauth-url',
        summary: '获取公众号 OAuth 授权跳转 URL',
        tags: ['微信'],
        parameters: [
            new OA\Parameter(name: 'redirect_url', in: 'query', required: true, description: '授权后回调地址', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'scope', in: 'query', description: '授权范围（snsapi_base/snsapi_userinfo）', schema: new OA\Schema(type: 'string', default: 'snsapi_userinfo')),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功，返回授权URL', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
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

    #[OA\Get(
        path: '/wechat/oauth-callback',
        summary: '公众号 OAuth 回调',
        tags: ['微信'],
        parameters: [
            new OA\Parameter(name: 'code', in: 'query', required: true, description: '微信授权 code', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'state', in: 'query', description: 'SPA 重定向地址（存在时重定向，否则返回 JSON）', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'API模式：返回用户 openid 信息', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\Response(response: 302, description: 'SPA模式：重定向至 state 地址'),
        ]
    )]
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

    #[OA\Get(
        path: '/wechat/get-openid',
        summary: '通过 wechat_token 换取 openid（SPA 使用）',
        tags: ['微信'],
        parameters: [
            new OA\Parameter(name: 'token', in: 'query', required: true, description: 'OAuth 回调后缓存的临时 token', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: '获取成功，返回 openid/unionid', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
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

    #[OA\Post(
        path: '/wechat/decrypt-phone',
        summary: '小程序解密手机号',
        security: [['bearerAuth' => []]],
        tags: ['微信'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code'],
                properties: [
                    new OA\Property(property: 'code', type: 'string', description: '小程序 getPhoneNumber 返回的 code'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '解密成功，返回手机号信息', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse')),
        ]
    )]
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
