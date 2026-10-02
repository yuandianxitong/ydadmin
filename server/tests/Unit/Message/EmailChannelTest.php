<?php

declare(strict_types=1);

namespace tests\Unit\Message;

use core\mail\MailerInterface;
use core\message\channel\EmailChannel;
use core\message\ChannelMessage;
use core\message\exception\MessageDefiniteFailure;
use core\message\exception\MessageTransientFailure;
use support\Container;
use tests\TestCase;

final class EmailChannelTest extends TestCase
{
    protected function tearDown(): void
    {
        try {
            Container::set(MailerInterface::class, \DI\get(\core\mail\SmtpMailer::class));
        } finally {
            parent::tearDown();
        }
    }

    public function test_passes_subject_and_body_to_the_mailer(): void
    {
        $box = new \stdClass();
        $box->seen = null;
        Container::set(MailerInterface::class, new class ($box) implements MailerInterface {
            public function __construct(private \stdClass $box)
            {
            }

            public function send(string $to, string $subject, string $body): void
            {
                $this->box->seen = [$to, $subject, $body];
            }
        });

        (new EmailChannel())->send(new ChannelMessage('user@example.com', '欢迎', [], '你好'));

        $this->assertSame(['user@example.com', '欢迎', '你好'], $box->seen);
    }

    public function test_transient_failure_is_not_rewritten(): void
    {
        Container::set(MailerInterface::class, new class implements MailerInterface {
            public function send(string $to, string $subject, string $body): void
            {
                throw new MessageTransientFailure('smtp connect failed');
            }
        });

        $this->expectException(MessageTransientFailure::class);
        (new EmailChannel())->send(new ChannelMessage('user@example.com', '欢迎', [], '你好'));
    }

    public function test_unexpected_exception_becomes_definite_and_hides_the_message(): void
    {
        Container::set(MailerInterface::class, new class implements MailerInterface {
            public function send(string $to, string $subject, string $body): void
            {
                throw new \RuntimeException('password=secret-should-not-leak');
            }
        });

        try {
            (new EmailChannel())->send(new ChannelMessage('user@example.com', '欢迎', [], '你好'));
            $this->fail('应当转成确定失败');
        } catch (MessageDefiniteFailure $e) {
            $this->assertStringNotContainsString('secret-should-not-leak', $e->getMessage());
            $this->assertStringContainsString(\RuntimeException::class, $e->getMessage());
        }
    }
}
