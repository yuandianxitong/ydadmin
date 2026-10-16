<?php

declare(strict_types=1);

namespace app\repository\operations;

use support\Db;

final class MonitoringRepository
{
    public function databaseReady(): bool
    {
        Db::connection()->selectOne('SELECT 1 AS ready');
        foreach (['queue_outbox', 'background_tasks', 'http_idempotency'] as $table) {
            Db::table($table)->limit(1)->value('id');
        }

        return (int) Db::table('configuration_state')->where('id', 1)->value('revision') > 0;
    }
}
