<?php

declare(strict_types=1);

namespace core\wechat;

use core\wechat\exception\WechatApiException;
use core\wechat\exception\WechatUnavailableException;

/**
 * 网页授权接口，公众号（snsapi_base / snsapi_userinfo）与开放平台网站应用（snsapi_login，由前端自己拼二维码链接）共用
 * code 换 access_token 与拉用户信息（M6a spec §3.3）。
 *
 * userInfo 失败照常抛出：「拉不到昵称头像也能登录」是 PC 网页登录的业务语义，由调用方捕获后回退，
 * API 层与其它方法保持同一分类契约。
 */
final class OAuthApi
{
    private const AUTHORIZE_URL = 'https://open.weixin.qq.com/connect/oauth2/authorize';

    private const SCOPES = ['snsapi_base', 'snsapi_userinfo'];

    /** 后端不校验 state（前端回跳后剥掉了，spec §11 已知限制），固定值只为满足微信参数要求 */
    private const STATE = 'ydadmin';

    public function __construct(private readonly WechatHttpClient $http)
    {
    }

    public function authorizeUrl(string $appId, string $redirectUrl, string $scope): string
    {
        if (!in_array($scope, self::SCOPES, true)) {
            throw new \InvalidArgumentException('不支持的网页授权 scope');
        }

        return self::AUTHORIZE_URL
            . '?appid=' . urlencode($appId)
            . '&redirect_uri=' . urlencode($redirectUrl)
            . '&response_type=code'
            . '&scope=' . $scope
            . '&state=' . self::STATE
            . '#wechat_redirect';
    }

    /**
     * @return array{openid: string, unionid: ?string, access_token: string}
     * @throws WechatApiException|WechatUnavailableException
     */
    public function exchangeCode(WechatAppConfig $config, string $code): array
    {
        $data = $this->http->get('sns/oauth2/access_token', [
            'appid'      => $config->appId,
            'secret'     => $config->secret,
            'code'       => $code,
            'grant_type' => 'authorization_code',
        ]);

        $openid = self::nonEmptyString($data['openid'] ?? null);
        $accessToken = self::nonEmptyString($data['access_token'] ?? null);
        if ($openid === null || $accessToken === null) {
            throw new WechatUnavailableException('微信接口 sns/oauth2/access_token 应答缺少 openid 或 access_token');
        }

        return ['openid' => $openid, 'unionid' => self::nonEmptyString($data['unionid'] ?? null), 'access_token' => $accessToken];
    }

    /**
     * @return array{nickname: ?string, avatar: ?string}
     * @throws WechatApiException|WechatUnavailableException
     */
    public function userInfo(string $accessToken, string $openid): array
    {
        $data = $this->http->get('sns/userinfo', [
            'access_token' => $accessToken,
            'openid'       => $openid,
            'lang'         => 'zh_CN',
        ]);

        return [
            'nickname' => self::nonEmptyString($data['nickname'] ?? null),
            'avatar'   => self::nonEmptyString($data['headimgurl'] ?? null),
        ];
    }

    private static function nonEmptyString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
