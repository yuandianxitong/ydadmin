<?php

declare(strict_types=1);

namespace app\process;

use core\observability\Heartbeat;
use support\Container;
use support\Context;
use support\Log;
use Workerman\Timer;

final class QueueConsumer extends \Webman\RedisQueue\Process\Consumer
{
    public function __construct(string $consumer_dir = '', private readonly string $group = 'logs')
    {
        parent::__construct($consumer_dir);
    }

    public function onWorkerStart(): void
    {
        parent::onWorkerStart();
        $beat = function (): void {
            try {
                Container::get(Heartbeat::class)->beat($this->group);
            } catch (\Throwable $e) {
                Log::warning('worker.heartbeat_failed', ['group' => $this->group, 'exception' => $e::class]);
            } finally {
                Context::destroy();
            }
        };
        $beat();
        Timer::add(15, $beat);
    }
}
