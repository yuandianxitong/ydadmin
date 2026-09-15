<?php

declare(strict_types=1);

namespace tests\RedLine;

use app\process\WebSocketServer;
use app\service\realtime\ForceLogoutService;
use app\service\realtime\WsTicketService;
use core\auth\TokenManager;
use core\auth\TokenVersion;
use support\Container;
use support\Redis;
use tests\fixtures\Realtime\FakeConnection;
use tests\Support\ApiTestCase;

/**
 * 红线（spec §7「连接建立」「强制下线」、§8 红线）：实时通道不能成为绕过 token 吊销的后门。
 * - 吊销（版本号变化、jti 拉黑）之前签发的票据，握手必须以 4003 被拒——票据只是 30 秒的一次性凭证，
 *   不能把「签发时还有效」的身份带过吊销；
 * - 已建立的连接，在管理员被强制下线后，吊销复查必须下发 force_logout 并以 4003 关闭。
 */
final class Test18_RealtimeRevocationTest extends ApiTestCase
{
    /** @var list<int> */
    private array $touchedAdmins = [];

    protected function tearDown(): void
    {
        foreach ($this->touchedAdmins as $adminId) {
            Redis::del("ws:online:{$adminId}", "ws:ticket:rate:{$adminId}");
            Redis::zRem('ws:online:index', (string) $adminId);
        }
        $this->touchedAdmins = [];
        parent::tearDown();
    }

    private function server(): WebSocketServer
    {
        return Container::make(WebSocketServer::class, []);
    }

    private static function request(string $ticket): object
    {
        return new class ($ticket) {
            public function __construct(private readonly string $ticket)
            {
            }

            public function get(string $name, mixed $default = null): mixed
            {
                return $name === 'ticket' ? $this->ticket : $default;
            }
        };
    }

    private function issue(int $adminId, int $ver, string $jti): string
    {
        $this->touchedAdmins[] = $adminId;

        return Container::get(WsTicketService::class)->issue($adminId, $ver, $jti, '', time() + 3600, '10.0.0.8', 'phpunit-redline');
    }

    private static function jtiOf(string $token): string
    {
        $claims = (array) json_decode((string) base64_decode(strtr(explode('.', $token)[1], '-_', '+/')), true);

        return (string) ($claims['jti'] ?? '');
    }

    /**
     * 真实链路：HTTP 登录（token 带 sid）→ HTTP 取票据 → 握手建立连接。
     *
     * @return array{0: WebSocketServer, 1: FakeConnection, 2: string}
     */
    private function connectViaLogin(): array
    {
        $admin = $this->actingAsAdmin();
        $this->touchedAdmins[] = $admin->id;
        $token = (string) $this->login($admin->username, $admin->password)->assertOk()->data()['token'];
        $ticket = (string) $this->post('/adminapi/ws/ticket', [], $token)->assertOk()->data()['ticket'];

        $server = $this->server();
        $connection = new FakeConnection();
        $server->handleHandshake($connection, self::request($ticket));
        $server->afterHandshake($connection);
        $this->assertNull($connection->closeCode, '前置条件：登录后取的票据应当建立连接');

        return [$server, $connection, $token];
    }

    public function test_silent_token_refresh_keeps_the_open_connection(): void
    {
        [$server, $connection, $token] = $this->connectViaLogin();

        $refreshed = (string) $this->post('/adminapi/auth/refresh', [], $token)->assertOk()->data()['token'];
        $this->assertNotSame($token, $refreshed);
        $this->assertTrue(TokenManager::scope('admin')->isJtiRevoked(self::jtiOf($token)), '前置条件：刷新拉黑了连接建立时的 jti');
        $server->recheckRevocation(time());

        $this->assertFalse($connection->closed, '静默刷新是同一会话：复查不得断开连接');
        $this->assertSame(['connected'], $connection->events(), '不得下发 force_logout');
    }

    public function test_logout_after_a_refresh_closes_the_connection_with_4003(): void
    {
        [$server, $connection, $token] = $this->connectViaLogin();
        $refreshed = (string) $this->post('/adminapi/auth/refresh', [], $token)->assertOk()->data()['token'];
        $server->recheckRevocation(time());
        $this->assertFalse($connection->closed);

        $this->post('/adminapi/auth/logout', [], $refreshed)->assertOk();
        $server->recheckRevocation(time());

        $this->assertSame(WebSocketServer::CLOSE_REVOKED, $connection->closeCode, '登出（哪怕用的是刷新后的 token）必须以 4003 关闭该会话的连接');
        $this->assertContains('force_logout', $connection->events());
    }

    public function test_ticket_signed_before_a_version_bump_cannot_connect(): void
    {
        $admin = $this->actingAsAdmin();
        $staleVer = TokenVersion::current($admin->id);
        $ticket = $this->issue($admin->id, $staleVer, bin2hex(random_bytes(16)));
        TokenVersion::bump($admin->id); // 禁用 / 删除 / 改密码 / 强制下线都走这一步

        $connection = new FakeConnection();
        $server = $this->server();
        $server->handleHandshake($connection, self::request($ticket));
        $server->afterHandshake($connection); // 握手响应发出后（onWebSocketConnected）才登记或带码关闭

        $this->assertSame(WebSocketServer::CLOSE_REVOKED, $connection->closeCode, '版本号已变：旧票据必须以 4003 被拒');
        $this->assertSame(0, (int) Redis::hLen("ws:online:{$admin->id}"), '被拒的连接不得登记在线');
    }

    public function test_ticket_whose_jti_was_blacklisted_cannot_connect(): void
    {
        $admin = $this->actingAsAdmin();
        $manager = TokenManager::scope('admin');
        $jti = (string) $manager->verifyClaims($admin->token)['jti'];
        $ticket = $this->issue($admin->id, TokenVersion::current($admin->id), $jti);
        $manager->blacklist($admin->token); // 登出

        $this->assertTrue($manager->isJtiRevoked($jti));
        $connection = new FakeConnection();
        $server = $this->server();
        $server->handleHandshake($connection, self::request($ticket));
        $server->afterHandshake($connection); // 握手响应发出后（onWebSocketConnected）才登记或带码关闭

        $this->assertSame(WebSocketServer::CLOSE_REVOKED, $connection->closeCode, 'jti 已拉黑：票据必须以 4003 被拒');
    }

    public function test_open_connection_is_closed_by_recheck_after_force_logout(): void
    {
        $operator = $this->actingAsAdmin('super');
        $target = $this->actingAsAdmin();
        $ticket = $this->issue($target->id, TokenVersion::current($target->id), bin2hex(random_bytes(16)));
        $server = $this->server();
        $connection = new FakeConnection();
        $server->handleHandshake($connection, self::request($ticket));
        $server->afterHandshake($connection);
        $this->assertNull($connection->closeCode, '前置条件：有效票据应当建立连接');
        $this->assertSame('connected', $connection->events()[0] ?? null);

        Container::get(ForceLogoutService::class)->kick($target->id);
        $server->recheckRevocation(time());

        $this->assertSame(WebSocketServer::CLOSE_REVOKED, $connection->closeCode, '强制下线后吊销复查必须以 4003 关闭已打开的连接');
        $this->assertContains('force_logout', $connection->events(), '关闭前必须先下发 force_logout');
        $this->assertNotSame($operator->id, $target->id);
    }
}
