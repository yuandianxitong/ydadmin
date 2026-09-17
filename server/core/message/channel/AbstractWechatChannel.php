<?php

declare(strict_types=1);

namespace core\message\channel;

use core\message\ChannelInterface;
use core\message\ChannelMessage;
use core\message\exception\MessageDefiniteFailure;
use core\message\exception\MessageTransientFailure;
use core\wechat\AccessTokenProvider;
use core\wechat\exception\WechatApiException;
use core\wechat\exception\WechatNotConfiguredException;
use core\wechat\exception\WechatUnavailableException;
use core\wechat\WechatAppConfig;
use core\wechat\WechatConfigResolver;
use core\wechat\WechatHttpClient;

/**
 * 公众号模板消息与小程序订阅消息的共用发送与错误分类（M6b spec §4.6，计划设计决定 3、18）。
 *
 *   - 未配置 → 确定失败；微信不可用（网络、非 200、非 JSON）→ 暂时失败；
 *   - errcode 40001/40014/42001（access_token 失效）→ invalidate 后重试一次；重试仍失效或 -1/45009 → 暂时失败，
 *     重试得到其它 errcode 按其它 errcode 判；
 *   - 首次 -1（系统繁忙）/45009（频率限制）→ 暂时失败，交队列退避重试，不在这里原地重试；
 *   - 其余 errcode（含 43101 用户拒收、43004 未关注、40003 openid 错、40037 模板错、47003 参数错，
 *     以及 cgi-bin/token 自身的 errcode）→ 确定失败。
 *
 * 异常消息只含 errcode 或固定短语：底层异常可能带 openid（errmsg）或带 access_token 的 URL，一律不挂 previous。
 * 容器单例、无状态；每次发送现读配置（WechatConfigResolver 不缓存）。
 */
abstract class AbstractWechatChannel implements ChannelInterface
{
    private const EXPIRED_TOKEN_ERRCODES = [40001, 40014, 42001];

    private const TRANSIENT_ERRCODES = [-1, 45009];

    public function __construct(
        private readonly WechatHttpClient $http,
        private readonly AccessTokenProvider $tokens,
        protected readonly WechatConfigResolver $configs,
    ) {
    }

    /** @throws WechatNotConfiguredException */
    abstract protected function appConfig(): WechatAppConfig;

    /** 微信接口路径（不含前导斜杠） */
    abstract protected function api(): string;

    /** 跳转字段名：公众号 url，小程序 page */
    abstract protected function linkField(): string;

    final public function send(ChannelMessage $message): void
    {
        try {
            $config = $this->appConfig();
        } catch (WechatNotConfiguredException) {
            throw new MessageDefiniteFailure('wechat not configured');
        }

        $body = ['touser' => $message->receiver, 'template_id' => $message->templateId];
        if ($message->link !== '') {
            $body[$this->linkField()] = $message->link;
        }
        $body['data'] = $message->data;

        try {
            try {
                $this->post($config, $body);
            } catch (WechatApiException $e) {
                if (!in_array($e->getErrcode(), self::EXPIRED_TOKEN_ERRCODES, true)) {
                    throw $e;
                }
                $this->tokens->invalidate($config->appId);
                $this->post($config, $body);
            }
        } catch (WechatApiException $e) {
            throw self::classify($e->getErrcode());
        } catch (WechatUnavailableException) {
            throw new MessageTransientFailure('wechat unavailable');
        }
    }

    /**
     * @param array<string, mixed> $body
     * @throws WechatApiException|WechatUnavailableException
     */
    private function post(WechatAppConfig $config, array $body): void
    {
        $this->http->postJson($this->api(), ['access_token' => $this->tokens->token($config)], $body);
    }

    /**
     * 能走到这里的 token 失效类 errcode 只可能来自重试（首次的已在 send() 里转去重试），所以按暂时失败判。
     */
    private static function classify(int $errcode): MessageDefiniteFailure|MessageTransientFailure
    {
        $message = "wechat errcode {$errcode}";
        if (in_array($errcode, self::TRANSIENT_ERRCODES, true) || in_array($errcode, self::EXPIRED_TOKEN_ERRCODES, true)) {
            return new MessageTransientFailure($message);
        }

        return new MessageDefiniteFailure($message);
    }
}
