<?php

declare(strict_types=1);

namespace core\message\channel;

use core\message\ChannelInterface;
use core\message\ChannelMessage;
use core\message\exception\MessageDefiniteFailure;
use core\sms\SmsInterface;
use support\Container;

/**
 * 短信通道（M6b spec §4.6）。
 *
 * 按发送解析 SmsInterface：容器共享无状态 SmsManager，每次 send() 使用当前配置快照选驱动。
 * 构造管理器不读取凭据；配置缺失只影响实际发送，不影响消息体系其他通道。
 *
 * 一律确定失败、不重试（计划设计决定 1）：两个驱动把配置缺失、网关拒绝、网络故障包成同一个 BusinessException，
 * 分不出来；网络故障时短信可能已送达，重试会重复发。驱动自己已记带遮蔽手机号的日志，这里只留异常类名，不挂 previous。
 */
final class SmsChannel implements ChannelInterface
{
    public function send(ChannelMessage $message): void
    {
        $params = [];
        foreach ($message->data as $key => $value) {
            $params[$key] = is_scalar($value) ? (string) $value : '';
        }

        try {
            Container::get(SmsInterface::class)->send($message->receiver, $message->templateId, $params);
        } catch (\Throwable $e) {
            throw new MessageDefiniteFailure('sms send failed: ' . $e::class);
        }
    }
}
