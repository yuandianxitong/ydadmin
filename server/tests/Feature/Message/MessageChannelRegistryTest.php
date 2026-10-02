<?php

declare(strict_types=1);

namespace tests\Feature\Message;

use app\service\message\MessageChannelRegistry;
use core\message\channel\EmailChannel;
use core\message\channel\SmsChannel;
use core\message\channel\WechatMiniChannel;
use core\message\channel\WechatOfficialChannel;
use core\message\ChannelMessage;
use core\message\exception\MessageTransientFailure;
use support\Container;
use tests\Support\Message\FakeChannel;
use tests\Support\Message\FakeMessageChannels;
use tests\TestCase;

/** M6b 设计决定 7：注册表按固定映射从容器取通道；测试夹具替换三通道并重建依赖它们的单例。 */
final class MessageChannelRegistryTest extends TestCase
{
    use FakeMessageChannels;

    protected function tearDown(): void
    {
        try {
            $this->restoreMessageChannels();
        } finally {
            parent::tearDown();
        }
    }

    private function registry(): MessageChannelRegistry
    {
        return Container::get(MessageChannelRegistry::class);
    }

    public function test_known_channels_resolve_to_their_implementations(): void
    {
        $this->assertInstanceOf(SmsChannel::class, $this->registry()->get('sms'));
        $this->assertInstanceOf(WechatOfficialChannel::class, $this->registry()->get('wechat_official'));
        $this->assertInstanceOf(WechatMiniChannel::class, $this->registry()->get('wechat_mini'));
        $this->assertInstanceOf(EmailChannel::class, $this->registry()->get('email'));
    }

    public function test_site_and_unknown_channels_are_rejected(): void
    {
        foreach (['site', 'fax', ''] as $channel) {
            try {
                $this->registry()->get($channel);
                $this->fail("{$channel} 不是外发通道，应当拒绝");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_external_channel_columns_cover_exactly_the_three_registered_channels(): void
    {
        $this->assertSame(['sms', 'wechat_official', 'wechat_mini', 'email'], array_keys(MessageChannelRegistry::EXTERNAL_CHANNELS));
        $this->assertSame('mobile', MessageChannelRegistry::EXTERNAL_CHANNELS['sms']['receiver']);
        $this->assertSame('oa_openid', MessageChannelRegistry::EXTERNAL_CHANNELS['wechat_official']['receiver']);
        $this->assertSame('mini_openid', MessageChannelRegistry::EXTERNAL_CHANNELS['wechat_mini']['receiver']);
        $this->assertSame('email', MessageChannelRegistry::EXTERNAL_CHANNELS['email']['receiver']);
    }

    public function test_fake_channels_record_calls_and_throw_queued_failures_once(): void
    {
        $this->fakeMessageChannels();
        $sms = $this->registry()->get('sms');
        $this->assertInstanceOf(FakeChannel::class, $sms);

        $failure = new MessageTransientFailure('wechat unavailable');
        $this->failNextSend('wechat_official', $failure);
        $first = new ChannelMessage('o-1', 'tpl-1', ['thing1' => ['value' => 'a']], 'https://example.com');
        try {
            $this->registry()->get('wechat_official')->send($first);
            $this->fail('排队的失败应当抛出');
        } catch (MessageTransientFailure $e) {
            $this->assertSame($failure, $e);
        }
        $second = new ChannelMessage('13800138000', 'SMS_1', ['nickname' => '张三']);
        $this->registry()->get('wechat_official')->send($first);
        $sms->send($second);

        $sent = $this->sentMessages();
        $this->assertCount(3, $sent, '被打断的那次调用也记录');
        $this->assertSame(['wechat_official', 'wechat_official', 'sms'], array_column($sent, 'channel'));
        $this->assertSame($second, $sent[2]['message']);
    }

    public function test_restore_puts_the_real_channels_back(): void
    {
        $this->fakeMessageChannels();
        $this->restoreMessageChannels();

        $this->assertInstanceOf(SmsChannel::class, $this->registry()->get('sms'));
        $this->assertSame([], $this->sentMessages());
    }
}
