<?php

declare(strict_types=1);

namespace app\bootstrap;

use Illuminate\Database\Events\QueryExecuted;
use support\Db;
use support\Log;
use Webman\Bootstrap;
use Workerman\Worker;

final class DatabaseTelemetry implements Bootstrap
{
    public static function start(?Worker $worker): void
    {
        Db::connection()->listen(static function (QueryExecuted $event): void {
            if ($event->time >= (int) config('operations.slow_query_ms', 200)) {
                try {
                    Log::warning('database.slow', ['query_hash' => hash('sha256', $event->sql), 'duration_ms' => $event->time]);
                } catch (\Throwable) {
                    // 日志介质故障不能改变数据库操作的结果。
                }
            }
        });
    }
}
