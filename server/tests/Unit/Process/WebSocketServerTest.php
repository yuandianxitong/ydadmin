<?php

declare(strict_types=1);

namespace tests\Unit\Process;

use app\process\WebSocketServer;
use app\service\realtime\PresenceService;
use app\service\realtime\WsTicketService;
use core\realtime\ConnectionRegistry;
use core\realtime\RealtimeMessage;
use support\Container;
use support\Redis;
use tests\fixtures\Realtime\FakeConnection;
use tests\fixtures\Realtime\FakeHandshakeRequest;
use tests\TestCase;

/**
 * spec §5「WebSocketServer」、§7：直接调用公开方法，不起真实 Workerman。
 * 票据与在线状态用真实服务（测试 Redis DB 15）；token 版本号与 jti 黑名单用注入的闭包控制，
 * 这样「版本号不符」「jti 已拉黑」可以精确构造，不依赖其他测试遗留的 Redis 状态。
 */
final class WebSocketServerTest extends TestCase
{
    private const ADMIN_A = 910001;
    private const ADMIN_B = 910002;

    /** @var array<int, int> 管理员 → 当前版本号 */
    private array $versions = [];

    /** @var list<string> 已拉黑的 jti */
    private array $revoked = [];

    private WsTicketService $tickets;

    private PresenceService $presence;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tickets = Container::get(WsTicketService::class);
        $this->presence = Container::get(PresenceService::class);
        $this->versions = [self::ADMIN_A => 7, self::ADMIN_B => 3];
        $this->revoked = [];
        $this->cleanRedis();
    }

    protected function tearDown(): void
    {
        $this->cleanRedis();
        parent::tearDown();
    }

    private function cleanRedis(): void
    {
        foreach ([self::ADMIN_A, self::ADMIN_B] as $adminId) {
            Redis::del("ws:online:{$adminId}");
            Redis::zRem(PresenceService::INDEX_KEY, (string) $adminId);
        }
    }

    private function server(?\Closure $versionOf = null): WebSocketServer
    {
        return new WebSocketServer(
            $this->tickets,
            $this->presence,
            new ConnectionRegistry(),
            $versionOf ?? fn (int $adminId): int => $this->versions[$adminId] ?? 0,
            fn (string $jti): bool => in_array($jti, $this->revoked, true),
        );
    }

    /** 走完整握手：onWebSocketConnect → vendor 发出握手响应 → onWebSocketConnected。 */
    private function connect(WebSocketServer $server, string $ticket): FakeConnection
    {
        $connection = new FakeConnection();
        $server->onWebSocketConnect($connection, new FakeHandshakeRequest(['ticket' => $ticket]));
        if (is_callable($connection->onWebSocketConnected)) {
            ($connection->onWebSocketConnected)($connection);
        }

        return $connection;
    }

    private function ticketFor(int $adminId, ?int $ver = null, string $jti = 'jti-default'): string
    {
        return $this->tickets->issue($adminId, $ver ?? $this->versions[$adminId], $jti, '10.0.0.8', 'phpunit-ua');
    }

    public function test_invalid_ticket_is_closed_with_4001_after_the_handshake_completes(): void
    {
        $server = $this->server();

        $connection = $this->connect($server, 'not-a-real-ticket');

        $this->assertTrue($connection->closed);
        $this->assertSame(WebSocketServer::CLOSE_TICKET, $connection->closeCode);
        $this->assertSame([], $connection->sent, '被拒绝的连接不应收到任何业务帧');
        $this->assertNull($this->presence->describe(self::ADMIN_A));
    }

    public function test_ticket_is_one_time_so_a_replayed_ticket_gets_4001(): void
    {
        $server = $this->server();
        $ticket = $this->ticketFor(self::ADMIN_A);

        $first = $this->connect($server, $ticket);
        $second = $this->connect($server, $ticket);

        $this->assertFalse($first->closed);
        $this->assertSame(WebSocketServer::CLOSE_TICKET, $second->closeCode);
    }

    public function test_version_mismatch_is_closed_with_4003(): void
    {
        $server = $this->server();
        $ticket = $this->ticketFor(self::ADMIN_A, 6);

        $connection = $this->connect($server, $ticket);

        $this->assertSame(WebSocketServer::CLOSE_REVOKED, $connection->closeCode);
        $this->assertNull($this->presence->describe(self::ADMIN_A));
    }

    public function test_revoked_jti_is_closed_with_4003(): void
    {
        $this->revoked = ['jti-logged-out'];
        $server = $this->server();

        $connection = $this->connect($server, $this->ticketFor(self::ADMIN_A, null, 'jti-logged-out'));

        $this->assertSame(WebSocketServer::CLOSE_REVOKED, $connection->closeCode);
    }

    public function test_valid_handshake_registers_presence_and_sends_connected(): void
    {
        $server = $this->server();

        $connection = $this->connect($server, $this->ticketFor(self::ADMIN_A));

        $this->assertFalse($connection->closed);
        $frames = $connection->frames();
        $this->assertCount(1, $frames);
        $this->assertSame('connected', $frames[0]['event']);
        $this->assertSame(['admin_id' => self::ADMIN_A, 'heartbeat' => 25], $frames[0]['payload']);
        $this->assertIsString($frames[0]['id']);
        $this->assertIsInt($frames[0]['ts']);

        $online = $this->presence->describe(self::ADMIN_A);
        $this->assertNotNull($online);
        $this->assertSame(1, $online['connections']);
        $this->assertSame('10.0.0.8', $online['ip']);
        $this->assertSame('phpunit-ua', $online['ua']);
    }

    public function test_ping_answers_pong_and_touches_presence(): void
    {
        $server = $this->server();
        $connection = $this->connect($server, $this->ticketFor(self::ADMIN_A));
        Redis::zAdd(PresenceService::INDEX_KEY, 1, (string) self::ADMIN_A);

        $server->handleMessage($connection, '{"event":"ping"}');

        $this->assertSame(['connected', 'pong'], $connection->events());
        $this->assertGreaterThan(1, (int) Redis::zScore(PresenceService::INDEX_KEY, (string) self::ADMIN_A), 'ping 必须刷新最后心跳分数');
    }

    public function test_non_ping_and_malformed_messages_are_ignored(): void
    {
        $server = $this->server();
        $connection = $this->connect($server, $this->ticketFor(self::ADMIN_A));

        $server->handleMessage($connection, '{"event":"notification.created"}');
        $server->handleMessage($connection, 'not json');
        $server->handleMessage($connection, '[]');

        $this->assertSame(['connected'], $connection->events());
        $this->assertFalse($connection->closed);
    }

    public function test_dispatch_delivers_only_to_targeted_admins(): void
    {
        $server = $this->server();
        $a = $this->connect($server, $this->ticketFor(self::ADMIN_A));
        $b = $this->connect($server, $this->ticketFor(self::ADMIN_B));
        $message = new RealtimeMessage([self::ADMIN_B], 'notification.created', ['id' => 5, 'title' => 't', 'type' => 1, 'created_at' => '2026-09-15 10:00:00']);

        $server->dispatch($message->encode());

        $this->assertSame(['connected'], $a->events());
        $this->assertSame(['connected', 'notification.created'], $b->events());
        $this->assertSame(['id' => 5, 'title' => 't', 'type' => 1, 'created_at' => '2026-09-15 10:00:00'], $b->frames()[1]['payload']);
    }

    public function test_dispatch_all_reaches_every_connection_and_bad_raw_is_ignored(): void
    {
        $server = $this->server();
        $a = $this->connect($server, $this->ticketFor(self::ADMIN_A));
        $b = $this->connect($server, $this->ticketFor(self::ADMIN_B));

        $server->dispatch('garbage');
        $server->dispatch((new RealtimeMessage('all', 'task.progress', ['task_id' => 'x', 'percent' => 50, 'message' => 'half']))->encode());

        $this->assertSame(['connected', 'task.progress'], $a->events());
        $this->assertSame(['connected', 'task.progress'], $b->events());
    }

    public function test_force_logout_is_sent_before_closing_with_4003(): void
    {
        $server = $this->server();
        $connection = $this->connect($server, $this->ticketFor(self::ADMIN_A));

        $server->dispatch((new RealtimeMessage([self::ADMIN_A], 'force_logout', ['reason' => 'kicked', 'message' => '您已被强制下线']))->encode());

        $this->assertSame(['connected', 'force_logout'], $connection->events());
        $this->assertTrue($connection->closed);
        $this->assertSame(WebSocketServer::CLOSE_REVOKED, $connection->closeCode);
    }

    public function test_sweep_closes_connections_silent_for_more_than_ninety_seconds(): void
    {
        $server = $this->server();
        $connection = $this->connect($server, $this->ticketFor(self::ADMIN_A));
        $fresh = $this->connect($server, $this->ticketFor(self::ADMIN_B));

        $server->sweep(time() + WebSocketServer::HEARTBEAT_TIMEOUT - 1);
        $this->assertFalse($connection->closed, '未超时不应关闭');

        $server->handleMessage($fresh, '{"event":"ping"}');
        $server->sweep(time() + WebSocketServer::HEARTBEAT_TIMEOUT + 1);

        $this->assertSame(WebSocketServer::CLOSE_HEARTBEAT, $connection->closeCode);
        $this->assertSame(WebSocketServer::CLOSE_HEARTBEAT, $fresh->closeCode, 'ping 发生在 now 之前 90 秒以上同样超时');
    }

    public function test_recheck_closes_connections_whose_version_changed_or_jti_was_revoked(): void
    {
        $server = $this->server();
        $a = $this->connect($server, $this->ticketFor(self::ADMIN_A, null, 'jti-a'));
        $b = $this->connect($server, $this->ticketFor(self::ADMIN_B, null, 'jti-b'));

        $server->recheckRevocation();
        $this->assertFalse($a->closed);
        $this->assertFalse($b->closed);

        $this->versions[self::ADMIN_A] = 8;
        $this->revoked = ['jti-b'];
        $server->recheckRevocation();

        $this->assertSame(['connected', 'force_logout'], $a->events());
        $this->assertSame('revoked', $a->frames()[1]['payload']['reason']);
        $this->assertSame(WebSocketServer::CLOSE_REVOKED, $a->closeCode);
        $this->assertSame(WebSocketServer::CLOSE_REVOKED, $b->closeCode);
    }

    public function test_close_removes_presence_and_registry_entry(): void
    {
        $server = $this->server();
        $connection = $this->connect($server, $this->ticketFor(self::ADMIN_A));

        $server->handleClose($connection);
        $server->dispatch((new RealtimeMessage([self::ADMIN_A], 'task.progress', ['task_id' => 'x', 'percent' => 1, 'message' => '']))->encode());

        $this->assertNull($this->presence->describe(self::ADMIN_A));
        $this->assertSame(['connected'], $connection->events(), '关闭后不应再收到投递');
    }

    public function test_exceptions_inside_workerman_callbacks_never_escape(): void
    {
        $server = $this->server(static function (int $adminId): int {
            throw new \RuntimeException('模拟 Redis 不可用');
        });
        $connection = new FakeConnection();

        $server->onWebSocketConnect($connection, new FakeHandshakeRequest(['ticket' => $this->ticketFor(self::ADMIN_A)]));
        if (is_callable($connection->onWebSocketConnected)) {
            ($connection->onWebSocketConnected)($connection);
        }
        $server->onMessage($connection, '{"event":"ping"}');
        $server->onClose($connection);

        $this->assertTrue($connection->closed, '校验抛异常时按票据无效关闭，fail closed');
        $this->assertSame(WebSocketServer::CLOSE_TICKET, $connection->closeCode);
    }

    public function test_close_with_builds_an_rfc6455_close_frame_and_truncates_long_reasons(): void
    {
        $server = $this->server();
        $connection = new FakeConnection();

        $server->closeWith($connection, 4003, str_repeat('x', 200));

        $this->assertSame(4003, $connection->closeCode);
        $this->assertSame(123, strlen($connection->closeReason));
    }
}
