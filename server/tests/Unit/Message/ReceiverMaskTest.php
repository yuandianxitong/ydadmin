<?php

declare(strict_types=1);

namespace tests\Unit\Message;

use app\service\message\ReceiverMask;
use PHPUnit\Framework\Attributes\DataProvider;
use tests\TestCase;

/** M6b 设计决定 6：message_logs.receiver 只存遮蔽后的展示值。 */
final class ReceiverMaskTest extends TestCase
{
    /** @return iterable<string, array{string, string, string}> */
    public static function cases(): iterable
    {
        yield '11 位手机号保留前 3 后 4' => ['sms', '13812345678', '138****5678'];
        yield '其它长度保留首尾各 2'     => ['sms', '+8613812345678', '+8****78'];
        yield '过短全替换'               => ['sms', '1234', '****'];
        yield '公众号 openid 前 6 位'    => ['wechat_official', 'oAbCdEfGhIjKlMnOpQrStUvWxYz0', 'oAbCdE…'];
        yield '小程序 openid 前 6 位'    => ['wechat_mini', 'o9x8y7z6w5', 'o9x8y7…'];
        yield '恰 6 位也全替换'          => ['wechat_mini', 'abcdef', '…'];
        yield '不足 6 位全替换'          => ['wechat_official', 'abc', '…'];
        yield '站内信原样'               => ['site', 'user#12', 'user#12'];
        yield '邮箱保留首字符和域名'    => ['email', 'someone@example.com', 's***@example.com'];
        yield '未知通道 fail closed'     => ['fax', 'someone@example.com', '…'];
    }

    #[DataProvider('cases')]
    public function test_mask(string $channel, string $receiver, string $expected): void
    {
        $this->assertSame($expected, ReceiverMask::mask($channel, $receiver));
    }
}
