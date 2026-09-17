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
 * 懒解析 SmsInterface（计划设计决定 2）：它在容器里是工厂绑定，凭据不全时解析即抛，
 * 注入到构造函数会让整个消息体系在「没配短信」时解析失败。
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
