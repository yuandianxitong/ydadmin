<?php

declare(strict_types=1);

namespace tests\Unit\Message;

use core\exception\BusinessException;
use core\message\channel\SmsChannel;
use core\message\ChannelMessage;
use core\message\exception\MessageDefiniteFailure;
use core\sms\SmsInterface;
use core\sms\SmsManager;
use PHPUnit\Framework\Attributes\DataProvider;
use support\Container;
use tests\TestCase;

/**
 * 计划设计决定 1、2：SmsChannel 懒解析 SmsInterface（凭据不全时解析即抛，必须在 try 里）；
 * 驱动抛出的任何异常都是确定失败——两个驱动把网关拒绝与网络故障包成同一个 BusinessException，
 * 且网络故障时短信可能已送达，重试会重复发。异常消息只含异常类名，不含手机号。
 */
final class SmsChannelTest extends TestCase
{
    private const MOBILE = '13812345678';

    protected function tearDown(): void
    {
        try {
            // 还原 config/container.php 的工厂绑定（setDefinition 会清掉已解析的实例）
            Container::set(SmsInterface::class, \DI\factory(
                static fn (SmsManager $manager): SmsInterface => $manager->driver()
            ));
        } finally {
            parent::tearDown();
        }
    }

    private function fakeDriver(?\Throwable $failWith = null): object
    {
        $driver = new class ($failWith) implements SmsInterface {
            /** @var list<array{mobile: string, template: string, vars: array<string, string>}> */
            public array $sent = [];

            public function __construct(private readonly ?\Throwable $failWith)
            {
            }

            public function send(string $mobile, string $templateId, array $vars): void
            {
                if ($this->failWith !== null) {
                    throw $this->failWith;
                }
                $this->sent[] = ['mobile' => $mobile, 'template' => $templateId, 'vars' => $vars];
            }
        };
        Container::set(SmsInterface::class, $driver);

        return $driver;
    }

    public function test_sends_receiver_template_and_string_params(): void
    {
        $driver = $this->fakeDriver();

        (new SmsChannel())->send(new ChannelMessage(self::MOBILE, 'SMS_001', ['order_no' => 'P1', 'amount' => 12]));

        $this->assertSame([['mobile' => self::MOBILE, 'template' => 'SMS_001', 'vars' => ['order_no' => 'P1', 'amount' => '12']]], $driver->sent);
    }

    public function test_driver_is_resolved_lazily_at_send_time(): void
    {
        $channel = new SmsChannel();
        $driver = $this->fakeDriver();

        $channel->send(new ChannelMessage(self::MOBILE, 'SMS_002', []));

        $this->assertCount(1, $driver->sent, '构造后才换的驱动也要生效：不得在构造时固化');
    }

    /** @return iterable<string, array{\Throwable}> */
    public static function driverFailures(): iterable
    {
        yield 'business' => [new BusinessException('短信发送失败，请稍后重试 ' . self::MOBILE)];
        yield 'runtime' => [new \RuntimeException('cURL error 28 for 13812345678')];
        yield 'error' => [new \TypeError('boom')];
    }

    #[DataProvider('driverFailures')]
    public function test_any_driver_failure_is_definite_without_phone(\Throwable $failure): void
    {
        $this->fakeDriver($failure);

        try {
            (new SmsChannel())->send(new ChannelMessage(self::MOBILE, 'SMS_003', []));
            $this->fail('应抛 MessageDefiniteFailure');
        } catch (MessageDefiniteFailure $e) {
            $this->assertSame('sms send failed: ' . $failure::class, $e->getMessage());
            $this->assertStringNotContainsString(self::MOBILE, $e->getMessage());
            $this->assertNull($e->getPrevious(), '不挂 previous：驱动异常消息可能带手机号');
        }
    }

    public function test_driver_resolution_failure_is_definite(): void
    {
        Container::set(SmsInterface::class, \DI\factory(static function (): SmsInterface {
            throw new BusinessException('短信服务未配置');
        }));

        $this->expectException(MessageDefiniteFailure::class);
        $this->expectExceptionMessageMatches('/^sms send failed: /');

        (new SmsChannel())->send(new ChannelMessage(self::MOBILE, 'SMS_004', []));
    }
}
