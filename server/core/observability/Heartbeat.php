<?php

declare(strict_types=1);

namespace core\observability;

use support\Redis;

final class Heartbeat
{
    public const GROUPS = ['scheduler', 'outbox', 'logs', 'messages', 'cron', 'tasks'];

    public function beat(string $group): void
    {
        if (!in_array($group, self::GROUPS, true)) {
            throw new \InvalidArgumentException('未知进程组');
        }
        $key = 'ops:heartbeat:' . $group;
        Redis::zAdd($key, time(), gethostname() . ':' . getmypid());
        Redis::zRemRangeByScore($key, '-inf', time() - (int) config('operations.heartbeat_ttl', 120));
        Redis::expire($key, 240);
    }

    /** @return array<string, int> */
    public function snapshot(): array
    {
        $result = [];
        foreach (self::GROUPS as $group) {
            $result[$group] = (int) Redis::zCount('ops:heartbeat:' . $group, time() - (int) config('operations.heartbeat_ttl', 120), '+inf');
        }

        return $result;
    }
}
