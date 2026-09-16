<?php

declare(strict_types=1);

namespace core\wechat;

use core\wechat\exception\WechatApiException;
use core\wechat\exception\WechatUnavailableException;

/**
 * 小程序服务端接口（M6a spec §3.3）。
 *
 * code2Session 丢弃 session_key：本项目不解密前端加密数据，存它只会多一份需要保护的密钥。
 * getPhoneNumber 用新版「手机号快速验证组件」的 code 换号，需要 access_token；token 失效类 errcode
 * 时 invalidate 后重试一次（M6a 计划设计决定 4），再失败就抛出。
 */
final class MiniProgramApi
{
    /** access_token 无效 / 不合法 / 过期 */
    private const EXPIRED_TOKEN_ERRCODES = [40001, 40014, 42001];

    public function __construct(
        private readonly WechatHttpClient $http,
        private readonly AccessTokenProvider $tokens,
    ) {
    }

    /**
     * @return array{openid: string, unionid: ?string}
     * @throws WechatApiException|WechatUnavailableException
     */
    public function code2Session(WechatAppConfig $config, string $code): array
    {
        $data = $this->http->get('sns/jscode2session', [
            'appid'      => $config->appId,
            'secret'     => $config->secret,
            'js_code'    => $code,
            'grant_type' => 'authorization_code',
        ]);

        $openid = $data['openid'] ?? null;
        if (!is_string($openid) || $openid === '') {
            throw new WechatUnavailableException('微信接口 sns/jscode2session 应答缺少 openid');
        }

        return ['openid' => $openid, 'unionid' => self::nonEmptyString($data['unionid'] ?? null)];
    }

    /**
     * 返回不带国家码的手机号（purePhoneNumber）。
     *
     * @throws WechatApiException|WechatUnavailableException
     */
    public function getPhoneNumber(WechatAppConfig $config, string $phoneCode): string
    {
        try {
            $data = $this->requestPhoneNumber($config, $phoneCode);
        } catch (WechatApiException $e) {
            if (!in_array($e->getErrcode(), self::EXPIRED_TOKEN_ERRCODES, true)) {
                throw $e;
            }
            $this->tokens->invalidate($config->appId);
            $data = $this->requestPhoneNumber($config, $phoneCode);
        }

        $phoneInfo = $data['phone_info'] ?? null;
        $phone = is_array($phoneInfo) ? self::nonEmptyString($phoneInfo['purePhoneNumber'] ?? null) : null;
        if ($phone === null) {
            throw new WechatUnavailableException('微信接口 wxa/business/getuserphonenumber 应答缺少手机号');
        }

        return $phone;
    }

    /** @return array<string, mixed> */
    private function requestPhoneNumber(WechatAppConfig $config, string $phoneCode): array
    {
        return $this->http->postJson(
            'wxa/business/getuserphonenumber',
            ['access_token' => $this->tokens->token($config)],
            ['code' => $phoneCode],
        );
    }

    private static function nonEmptyString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
