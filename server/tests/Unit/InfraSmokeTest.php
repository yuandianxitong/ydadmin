<?php

declare(strict_types=1);

namespace tests\Unit;

use support\Cache;
use support\Db;
use tests\TestCase;

final class InfraSmokeTest extends TestCase
{
    public function test_runs_on_test_database(): void
    {
        $this->assertStringEndsWith('_test', Db::connection()->getDatabaseName(), '测试必须跑在 _test 库');
    }

    public function test_cache_store_is_redis_on_test_db(): void
    {
        $this->assertSame('redis', config('cache.default'));
        $this->assertSame(15, (int) config('redis.default.database'), '测试 Redis 必须是 DB 15');

        Cache::set('infra_smoke', 'ok', 10);
        $this->assertSame('ok', Cache::get('infra_smoke'));
        Cache::delete('infra_smoke');
    }
}
