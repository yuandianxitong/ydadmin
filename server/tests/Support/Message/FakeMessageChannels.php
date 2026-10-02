<?php

declare(strict_types=1);

namespace tests\Support\Message;

use core\message\channel\EmailChannel;
use core\message\channel\SmsChannel;
use core\message\channel\WechatMiniChannel;
use core\message\channel\WechatOfficialChannel;
use support\Container;

/**
 * 用假通道替换三个外发通道（M6b 设计决定 7）。测试进程队列是 sync，任何触发 sendToUser() 的用例都会同步走到通道，
 * 不换就会真的去调短信、微信接口。
 *
 * 注册表每次 get() 都从容器取通道，换完其实立即生效；仍按依赖顺序重建注册表、投递服务、MessageService 与消费者单例，
 * 免得以后谁在它们的实例属性里缓存通道而让替换静默失效（类尚不存在的跳过，Task 6 之前没有投递服务与消费者）。
 * 注入了 MessageService 的业务服务（注册、微信登录、支付）不必重建：它们持有的旧 MessageService 无状态，照样经注册表取到假通道。
 */
trait FakeMessageChannels
{
    private ?FakeChannelRecorder $messageChannelRecorder = null;

    private const MESSAGE_CHANNEL_CLASSES = [
        'sms'             => SmsChannel::class,
        'wechat_official' => WechatOfficialChannel::class,
        'wechat_mini'     => WechatMiniChannel::class,
        'email'           => EmailChannel::class,
    ];

    private const MESSAGE_SERVICE_CLASSES = [
        'app\service\message\MessageChannelRegistry',
        'app\service\message\MessageDeliveryService',
        'app\service\message\MessageService',
        'app\queue\redis_slow\MessageSendConsumer',
    ];

    private function fakeMessageChannels(): void
    {
        $this->messageChannelRecorder = new FakeChannelRecorder();
        foreach (self::MESSAGE_CHANNEL_CLASSES as $channel => $class) {
            Container::set($class, new FakeChannel($channel, $this->messageChannelRecorder));
        }
        $this->rebuildMessageServices();
    }

    /** @return list<array{channel: string, message: \core\message\ChannelMessage}> */
    private function sentMessages(): array
    {
        return $this->messageChannelRecorder?->sent ?? [];
    }

    private function failNextSend(string $channel, \Throwable $e): void
    {
        if ($this->messageChannelRecorder === null) {
            throw new \LogicException('先调用 fakeMessageChannels()');
        }
        $this->messageChannelRecorder->failures[$channel][] = $e;
    }

    private function restoreMessageChannels(): void
    {
        foreach (self::MESSAGE_CHANNEL_CLASSES as $class) {
            Container::set($class, Container::make($class));
        }
        $this->messageChannelRecorder = null;
        $this->rebuildMessageServices();
    }

    private function rebuildMessageServices(): void
    {
        foreach (self::MESSAGE_SERVICE_CLASSES as $class) {
            if (class_exists($class)) {
                Container::set($class, Container::make($class));
            }
        }
    }
}
