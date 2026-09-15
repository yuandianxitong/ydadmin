<?php

declare(strict_types=1);

namespace tests\Unit\Realtime;

use app\service\realtime\PresenceService;
use support\Redis;
use tests\TestCase;

final class PresenceServiceTest extends TestCase
{
    private const A = 910001;
    private const B = 910002;
    private const GHOST = 910003;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clean();
    }

    protected function tearDown(): void
    {
        $this->clean();
        parent::tearDown();
    }

    private function clean(): void
    {
        foreach ([self::A, self::B, self::GHOST] as $id) {
            Redis::del("ws:online:{$id}");
            Redis::zRem(PresenceService::INDEX_KEY, (string) $id);
        }
    }

    public function test_join_records_the_connection_indexes_the_admin_and_sets_ttl(): void
    {
        $presence = new PresenceService();

        $presence->join(self::A, 'node:1:1', ['ip' => '10.0.0.1', 'ua' => 'UA-1', 'connected_at' => '2026-09-15 10:00:00']);

        $ttl = (int) Redis::ttl('ws:online:' . self::A);
        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(PresenceService::TTL, $ttl);
        $this->assertContains(self::A, $presence->onlineAdminIds());
        $this->assertSame(
            ['connections' => 1, 'ip' => '10.0.0.1', 'ua' => 'UA-1', 'connected_at' => '2026-09-15 10:00:00'],
            array_diff_key((array) $presence->describe(self::A), ['last_seen' => true])
        );
    }

    public function test_describe_counts_connections_and_reports_the_latest_one(): void
    {
        $presence = new PresenceService();
        $presence->join(self::A, 'node:1:1', ['ip' => '10.0.0.1', 'ua' => 'old', 'connected_at' => '2026-09-15 09:00:00']);
        $presence->join(self::A, 'node:1:2', ['ip' => '10.0.0.2', 'ua' => 'new', 'connected_at' => '2026-09-15 10:00:00']);

        $info = $presence->describe(self::A);

        $this->assertNotNull($info);
        $this->assertSame(2, $info['connections']);
        $this->assertSame('10.0.0.2', $info['ip']);
        $this->assertSame('new', $info['ua']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $info['last_seen']);
        $this->assertNull($presence->describe(self::B));
    }

    public function test_leave_removes_the_field_and_drops_the_admin_when_empty(): void
    {
        $presence = new PresenceService();
        $presence->join(self::A, 'node:1:1', ['ip' => '', 'ua' => '', 'connected_at' => '2026-09-15 10:00:00']);
        $presence->join(self::A, 'node:1:2', ['ip' => '', 'ua' => '', 'connected_at' => '2026-09-15 10:00:00']);

        $presence->leave(self::A, 'node:1:1');
        $this->assertSame(1, $presence->describe(self::A)['connections'] ?? 0);

        $presence->leave(self::A, 'node:1:2');
        $this->assertNull($presence->describe(self::A));
        $this->assertNotContains(self::A, $presence->onlineAdminIds());
    }

    public function test_forget_returns_field_count_and_touch_refreshes_only_online_admins(): void
    {
        $presence = new PresenceService();
        $presence->join(self::A, 'node:1:1', ['ip' => '', 'ua' => '', 'connected_at' => '2026-09-15 10:00:00']);
        $presence->join(self::A, 'node:1:2', ['ip' => '', 'ua' => '', 'connected_at' => '2026-09-15 10:00:00']);
        Redis::expire('ws:online:' . self::A, 5);

        $presence->touch(self::A);
        $this->assertGreaterThan(5, (int) Redis::ttl('ws:online:' . self::A), 'touch 续期到 90 秒');

        $presence->touch(self::B);
        $this->assertSame(0, (int) Redis::exists('ws:online:' . self::B), 'touch 不会凭空建出不在线管理员的键');
        $this->assertNotContains(self::B, $presence->onlineAdminIds());

        $this->assertSame(2, $presence->forget(self::A));
        $this->assertSame(0, $presence->forget(self::A));
        $this->assertNotContains(self::A, $presence->onlineAdminIds());
    }

    /**
     * fix round 1：leave() 的 HDEL 与「置空判断 + DEL/ZREM」曾经不是原子操作，并发 join() 可能夹在中间
     * 把刚建好的记录连同索引一起删掉。改用 Lua 原子脚本后，同一管理员还有其它字段在线时，
     * leave() 绝不能动索引或整张 hash。真正的并发交错无法在 PHPUnit 里确定性复现，
     * 原子性由 Lua 脚本单命令执行保证。
     */
    public function test_leave_keeps_the_admin_online_when_another_field_remains(): void
    {
        $presence = new PresenceService();
        $presence->join(self::A, 'node:1:1', ['ip' => '10.0.0.1', 'ua' => 'UA-1', 'connected_at' => '2026-09-15 10:00:00']);
        $presence->join(self::A, 'node:1:2', ['ip' => '10.0.0.2', 'ua' => 'UA-2', 'connected_at' => '2026-09-15 10:00:01']);

        $presence->leave(self::A, 'node:1:1');

        $this->assertContains(self::A, $presence->onlineAdminIds(), 'leave() 不能连带删掉仍有其它连接的管理员索引');
        $this->assertNotFalse(Redis::zScore(PresenceService::INDEX_KEY, (string) self::A), '索引成员保留');
        $info = $presence->describe(self::A);
        $this->assertNotNull($info);
        $this->assertSame(1, $info['connections'], '另一个字段仍在');
        $this->assertSame('10.0.0.2', $info['ip']);
    }

    /** fix round 1：forget() 的 HLEN 读数与 DEL/ZREM 之间同样不能被并发 join() 插入，改用 Lua 原子脚本。 */
    public function test_forget_clears_both_hash_and_index_atomically(): void
    {
        $presence = new PresenceService();
        $presence->join(self::A, 'node:1:1', ['ip' => '', 'ua' => '', 'connected_at' => '2026-09-15 10:00:00']);
        $presence->join(self::A, 'node:1:2', ['ip' => '', 'ua' => '', 'connected_at' => '2026-09-15 10:00:00']);

        $count = $presence->forget(self::A);

        $this->assertSame(2, $count, '返回清除前的连接字段数');
        $this->assertSame(0, (int) Redis::exists('ws:online:' . self::A), 'hash 已删');
        $this->assertFalse(Redis::zScore(PresenceService::INDEX_KEY, (string) self::A), '索引已删');
        $this->assertSame(0, $presence->forget(self::A), '再次 forget 已清空的管理员返回 0');
    }

    public function test_online_ids_prune_index_members_whose_hash_expired(): void
    {
        $presence = new PresenceService();
        $presence->join(self::A, 'node:1:1', ['ip' => '', 'ua' => '', 'connected_at' => '2026-09-15 10:00:00']);
        Redis::zAdd(PresenceService::INDEX_KEY, time() + 10, (string) self::GHOST);

        $ids = $presence->onlineAdminIds();

        $this->assertContains(self::A, $ids);
        $this->assertNotContains(self::GHOST, $ids);
        $this->assertFalse(Redis::zScore(PresenceService::INDEX_KEY, (string) self::GHOST), '失效成员被移出 zset');
    }
}
