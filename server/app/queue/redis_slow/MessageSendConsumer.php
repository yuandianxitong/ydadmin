<?php

declare(strict_types=1);

namespace app\queue\redis_slow;

class_alias(\app\queue\redis_messages\MessageSendConsumer::class, __NAMESPACE__ . '\\MessageSendConsumer');
