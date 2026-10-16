<?php

declare(strict_types=1);

namespace app\queue\redis_messages;

use app\queue\ConsumerBase;
use app\service\message\MessageService;
use DI\Attribute\Inject;

final class MessageRequestConsumer extends ConsumerBase
{
    public string $queue = 'message-request';

    #[Inject]
    protected MessageService $messages;

    public function handle(array $data): void
    {
        $this->messages->deliverScheduled($data);
    }
}
