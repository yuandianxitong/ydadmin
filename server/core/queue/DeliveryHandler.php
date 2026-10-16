<?php

declare(strict_types=1);

namespace core\queue;

interface DeliveryHandler extends QueueHandler
{
    /** @param array<string, mixed> $data */
    public function deliver(array $data): void;
}
