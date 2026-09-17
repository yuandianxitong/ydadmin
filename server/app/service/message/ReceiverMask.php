<?php

declare(strict_types=1);

namespace app\service\message;

use app\repository\message\MessageLogRepository;

/**
 * message_logs.receiver 的展示值（M6b 设计决定 6）。日志里只存遮蔽后的值，发送时按 user_id 重读真实接收人。
 *
 * 微信 openid 长度大于 6 才保留前 6 位：恰好 6 位时按「前 6 位 + …」会原样暴露。未知通道一律全替换（fail closed）。
 */
final class ReceiverMask
{
    private const WECHAT_VISIBLE = 6;

    private const ELLIPSIS = '…';

    public static function mask(string $channel, string $receiver): string
    {
        return match ($channel) {
            MessageLogRepository::CHANNEL_SITE => $receiver,
            MessageLogRepository::CHANNEL_SMS  => self::mobile($receiver),
            MessageLogRepository::CHANNEL_WECHAT_OFFICIAL,
            MessageLogRepository::CHANNEL_WECHAT_MINI => mb_strlen($receiver) > self::WECHAT_VISIBLE
                ? mb_substr($receiver, 0, self::WECHAT_VISIBLE) . self::ELLIPSIS
                : self::ELLIPSIS,
            default => self::ELLIPSIS,
        };
    }

    private static function mobile(string $mobile): string
    {
        if (preg_match('/^\d{11}$/D', $mobile) === 1) {
            return substr($mobile, 0, 3) . '****' . substr($mobile, -4);
        }

        return mb_strlen($mobile) > 4 ? mb_substr($mobile, 0, 2) . '****' . mb_substr($mobile, -2) : '****';
    }
}
