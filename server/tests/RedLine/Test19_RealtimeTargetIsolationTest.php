<?php

declare(strict_types=1);

namespace tests\RedLine;

use app\process\WebSocketServer;
use app\service\realtime\WsTicketService;
use core\auth\TokenVersion;
use core\realtime\RealtimeMessage;
use support\Container;
use support\Redis;
use tests\fixtures\Realtime\FakeConnection;
use tests\Support\ApiTestCase;

/**
 * 红线（spec §5「WebSocketServer::dispatch」、§8 红线）：定向推送只能投给目标管理员的连接。
 * 同一 worker 里挂着多个管理员的连接时，发给 A 的消息（例如指定 A 的通知、踢 A 下线）绝不能出现在 B 的连接上。
 */
final class Test19_RealtimeTargetIsolationTest extends ApiTestCase
{
    /** @var list<int> */
    private array $touchedAdmins = [];

    protected function tearDown(): void
    {
        foreach ($this->touchedAdmins as $adminId) {
            Redis::del("ws:online:{$adminId}");
            Redis::zRem('ws:online:index', (string) $adminId);
        }
        $this->touchedAdmins = [];
        parent::tearDown();
    }

    private function connect(WebSocketServer $server, int $adminId): FakeConnection
    {
        $this->touchedAdmins[] = $adminId;
        $ticket = Container::get(WsTicketService::class)
            ->issue($adminId, TokenVersion::current($adminId), bin2hex(random_bytes(16)), '10.0.0.9', 'phpunit-redline');
        $request = new class ($ticket) {
            public function __construct(private readonly string $ticket)
            {
            }

            public function get(string $name, mixed $default = null): mixed
            {
                return $name === 'ticket' ? $this->ticket : $default;
            }
        };
        $connection = new FakeConnection();
        $server->handleHandshake($connection, $request);
        $server->afterHandshake($connection);
        $this->assertNull($connection->closeCode, "前置条件：管理员 {$adminId} 的连接应当建立");

        return $connection;
    }

    /** @return list<string> 握手之后收到的事件（去掉 connected） */
    private static function eventsAfterHandshake(FakeConnection $connection): array
    {
        return array_values(array_filter(
            $connection->events(),
            static fn (string $event): bool => $event !== 'connected'
        ));
    }

    public function test_targeted_message_reaches_only_the_target_admin(): void
    {
        $a = $this->actingAsAdmin();
        $b = $this->actingAsAdmin();
        $server = Container::make(WebSocketServer::class, []);
        $connA = $this->connect($server, $a->id);
        $connB = $this->connect($server, $b->id);

        $server->dispatch((new RealtimeMessage([$a->id], 'task.progress', ['task_id' => 'rl', 'percent' => 50, 'message' => 'only-a']))->encode());

        $this->assertSame(['task.progress'], self::eventsAfterHandshake($connA), 'A 必须收到发给 A 的消息');
        $this->assertSame([], self::eventsAfterHandshake($connB), 'B 的连接上不得出现发给 A 的任何帧');
    }

    public function test_force_logout_for_one_admin_does_not_close_another(): void
    {
        $a = $this->actingAsAdmin();
        $b = $this->actingAsAdmin();
        $server = Container::make(WebSocketServer::class, []);
        $connA = $this->connect($server, $a->id);
        $connB = $this->connect($server, $b->id);

        $server->dispatch((new RealtimeMessage([$a->id], 'force_logout', ['reason' => 'kicked', 'message' => 'rl']))->encode());

        $this->assertSame(WebSocketServer::CLOSE_REVOKED, $connA->closeCode, '被踢的 A 必须以 4003 关闭');
        $this->assertNull($connB->closeCode, 'B 的连接必须保持打开');
        $this->assertSame([], self::eventsAfterHandshake($connB));
    }

    public function test_broadcast_reaches_everyone(): void
    {
        $a = $this->actingAsAdmin();
        $b = $this->actingAsAdmin();
        $server = Container::make(WebSocketServer::class, []);
        $connA = $this->connect($server, $a->id);
        $connB = $this->connect($server, $b->id);

        $server->dispatch((new RealtimeMessage('all', 'notification.created', ['id' => 1, 'title' => 'rl', 'type' => 1, 'created_at' => date('Y-m-d H:i:s')]))->encode());

        $this->assertSame(['notification.created'], self::eventsAfterHandshake($connA));
        $this->assertSame(['notification.created'], self::eventsAfterHandshake($connB));
    }
}
