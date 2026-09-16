<?php

declare(strict_types=1);

namespace core\wechat\exception;

/**
 * 微信接口返回了非 0 的 errcode（请求确实到达微信并被处理）。
 *
 * 消息只含接口名与 errcode；errmsg 单独存放（getErrmsg()），由调用方决定是否写日志——
 * 它不含请求参数，但也不回显给客户端（spec §4.10）。
 */
final class WechatApiException extends WechatException
{
    public function __construct(
        private readonly string $api,
        private readonly int $errcode,
        private readonly string $errmsg,
    ) {
        parent::__construct("微信接口 {$api} 返回 errcode {$errcode}");
    }

    public function getApi(): string
    {
        return $this->api;
    }

    public function getErrcode(): int
    {
        return $this->errcode;
    }

    public function getErrmsg(): string
    {
        return $this->errmsg;
    }
}
