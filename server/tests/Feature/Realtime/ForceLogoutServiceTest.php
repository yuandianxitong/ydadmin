<?php

declare(strict_types=1);

namespace tests\Feature\Realtime;

use app\service\realtime\ForceLogoutService;
use app\service\realtime\PresenceService;
use core\auth\TokenVersion;
use core\realtime\RealtimePublisher;
use support\Container;
use support\Redis;
use tests\Support\ApiTestCase;

/** 记录发布调用，不连 Redis。 */
final class SpyRealtimePublisher extends RealtimePublisher
{
    /** @var list<array{targets: string|array<int, int>, event: string, payload: array<string, mixed>}> */
    public array $published = [];

    public function publish(string|array $targets, string $event, array $payload): void
    {
        $this->published[] = ['targets' => $targets, 'event' => $event, 'payload' => $payload];
    }
}

/** 发布时记下目标管理员此刻的 token 版本号，用来断言「先 bump 再推送」。 */
final class VersionObservingPublisher extends RealtimePublisher
{
    public ?int $observedVersion = null;

    public function __construct(private readonly int $adminId)
    {
    }

    public function publish(string|array $targets, string $event, array $payload): void
    {
        $this->observedVersion = TokenVersion::current($this->adminId);
    }
}

/**
 * spec §7「强制下线」：先吊销（TokenVersion::bump）→ 再推送 force_logout → 最后清在线状态。
 * 发布器经 Container::set 换成 spy，服务用 Container::make 取新实例，拿到的就是 spy。
 */
final class ForceLogoutServiceTest extends ApiTestCase
{
    private SpyRealtimePublisher $spy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->spy = new SpyRealtimePublisher();
        Container::set(RealtimePublisher::class, $this->spy);
    }

    protected function tearDown(): void
    {
        Container::set(RealtimePublisher::class, new RealtimePublisher());
        parent::tearDown();
    }

    private function service(): ForceLogoutService
    {
        return Container::make(ForceLogoutService::class, []);
    }

    public function test_kick_bumps_version_publishes_and_forgets_presence(): void
    {
        $target = $this->actingAsAdmin();
        $presence = Container::get(PresenceService::class);
        $presence->join($target->id, 'node:1:1', ['ip' => '10.0.0.1', 'ua' => 'ua-1', 'connected_at' => date('Y-m-d H:i:s')]);
        $presence->join($target->id, 'node:1:2', ['ip' => '10.0.0.2', 'ua' => 'ua-2', 'connected_at' => date('Y-m-d H:i:s')]);
        $versionBefore = TokenVersion::current($target->id);

        $kicked = $this->service()->kick($target->id);

        $this->assertSame(2, $kicked, '返回被清掉的连接数');
        $this->assertSame($versionBefore + 1, TokenVersion::current($target->id), '版本号必须自增，旧 token 立即失效');
        $this->assertSame([[
            'targets' => [$target->id],
            'event'   => 'force_logout',
            'payload' => ['reason' => 'kicked', 'message' => lang('business.realtime_force_logout')],
        ]], $this->spy->published);
        $this->assertNull($presence->describe($target->id), '在线状态必须被清掉');
        $this->assertNotContains($target->id, $presence->onlineAdminIds());
    }

    public function test_kick_of_an_offline_admin_still_revokes_and_returns_zero(): void
    {
        $target = $this->actingAsAdmin();
        $versionBefore = TokenVersion::current($target->id);

        $this->assertSame(0, $this->service()->kick($target->id, 'revoked'));
        $this->assertSame($versionBefore + 1, TokenVersion::current($target->id));
        $this->assertSame('revoked', $this->spy->published[0]['payload']['reason']);
    }

    public function test_bump_happens_before_publish(): void
    {
        $target = $this->actingAsAdmin();
        $versionBefore = TokenVersion::current($target->id);
        $spy = new VersionObservingPublisher($target->id);
        Container::set(RealtimePublisher::class, $spy);

        $this->service()->kick($target->id);

        $this->assertSame($versionBefore + 1, $spy->observedVersion, '推送时版本号必须已经自增：推送丢了也不能留下吊销空档');
        Redis::del("ws:online:{$target->id}");
    }
}
