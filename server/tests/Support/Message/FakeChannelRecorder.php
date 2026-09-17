<?php

declare(strict_types=1);

namespace tests\Support\Message;

use core\message\ChannelMessage;

/** 三个假通道共用的记录器：一个用例一份，由 FakeMessageChannels 创建。 */
final class FakeChannelRecorder
{
    /** @var list<array{channel: string, message: ChannelMessage}> 每次 send() 调用，含被排队失败打断的 */
    public array $sent = [];

    /** @var array<string, list<\Throwable>> 通道 => 依次抛出的异常，抛一个少一个 */
    public array $failures = [];
}
