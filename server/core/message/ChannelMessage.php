<?php

declare(strict_types=1);

namespace core\message;

/**
 * 发往单个外发通道的载荷（计划设计决定 4）。由 MessageDeliveryService 渲染好后交给通道，通道不再碰模板。
 *
 *   - 短信：receiver 手机号；templateId 短信模板 ID；data {key: value}；link 空。
 *   - 公众号：receiver openid；data {field: {value}}；link 为跳转 url（空则不发 url 字段）。
 *   - 小程序：receiver openid；data {field: {value}}；link 为 page（空则不发 page 字段）。
 */
final readonly class ChannelMessage
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public string $receiver,
        public string $templateId,
        public array $data,
        public string $link = '',
    ) {
    }
}
