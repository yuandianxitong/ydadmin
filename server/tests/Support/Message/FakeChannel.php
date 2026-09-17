<?php

declare(strict_types=1);

namespace tests\Support\Message;

use core\message\ChannelInterface;
use core\message\ChannelMessage;

/** 不触网的假通道：记录调用；该通道有排队的异常时抛出队首那个。 */
final class FakeChannel implements ChannelInterface
{
    public function __construct(
        private readonly string $channel,
        private readonly FakeChannelRecorder $recorder,
    ) {
    }

    public function send(ChannelMessage $message): void
    {
        $this->recorder->sent[] = ['channel' => $this->channel, 'message' => $message];
        if (($this->recorder->failures[$this->channel] ?? []) !== []) {
            throw array_shift($this->recorder->failures[$this->channel]);
        }
    }
}
