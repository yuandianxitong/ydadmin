<?php

declare(strict_types=1);

namespace tests\Unit\Mail;

use core\contract\ConfigValueReader;
use core\mail\SmtpMailer;
use core\message\exception\MessageDefiniteFailure;
use tests\TestCase;

/** SMTP 发信在连服务器之前拒绝缺配置和带换行的头。 */
final class SmtpMailerTest extends TestCase
{
    public function test_missing_host_is_a_definite_failure(): void
    {
        $mailer = new SmtpMailer($this->config(['smtp_host' => '']));

        $this->expectException(MessageDefiniteFailure::class);
        $mailer->send('user@example.com', 'hello', 'body');
    }

    public function test_header_break_in_subject_is_rejected_before_connect(): void
    {
        $mailer = new SmtpMailer($this->config([
            'smtp_host'         => 'smtp.example.com',
            'smtp_from_address' => 'from@example.com',
        ]));

        $this->expectException(MessageDefiniteFailure::class);
        $this->expectExceptionMessage('smtp header rejected');
        $mailer->send('user@example.com', "hello\r\nBcc: evil@example.com", 'body');
    }

    /** @param array<string, mixed> $values */
    private function config(array $values): ConfigValueReader
    {
        return new class ($values) implements ConfigValueReader {
            /** @param array<string, mixed> $values */
            public function __construct(private array $values)
            {
            }

            public function getConfigValue(string $key, mixed $default = null): mixed
            {
                return $this->values[$key] ?? $default;
            }
        };
    }
}
