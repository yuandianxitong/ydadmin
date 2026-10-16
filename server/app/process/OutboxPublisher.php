<?php

declare(strict_types=1);

namespace app\process;

use app\service\operations\OutboxService;
use core\observability\Heartbeat;
use support\Container;
use support\Context;
use support\Log;
use Workerman\Timer;
use Workerman\Worker;

final class OutboxPublisher
{
    public function onWorkerStart(Worker $worker): void
    {
        $lastFailureLog = 0;
        Timer::add(1, static function () use (&$lastFailureLog): void {
            try {
                Container::get(Heartbeat::class)->beat('outbox');
                Container::get(OutboxService::class)->publish();
            } catch (\Throwable $e) {
                if (time() - $lastFailureLog >= 60) {
                    Log::error('outbox.tick_failed', ['exception' => $e::class]);
                    $lastFailureLog = time();
                }
            } finally {
                Context::destroy();
            }
        });
        Timer::add(60, static function (): void {
            try {
                Container::get(\app\service\operations\MaintenanceService::class)->cleanup();
            } catch (\Throwable $e) {
                Log::warning('operations.cleanup_failed', ['exception' => $e::class]);
            } finally {
                Context::destroy();
            }
        });
    }
}
