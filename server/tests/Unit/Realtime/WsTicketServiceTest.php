<?php

declare(strict_types=1);

namespace tests\Unit\Realtime;

use app\service\realtime\WsTicketService;
use support\Redis;
use tests\TestCase;

final class WsTicketServiceTest extends TestCase
{
    /** @var list<string> */
    private array $tickets = [];

    protected function tearDown(): void
    {
        foreach ($this->tickets as $ticket) {
            Redis::del("ws:ticket:{$ticket}");
        }
        $this->tickets = [];
        parent::tearDown();
    }

    private function issue(WsTicketService $service): string
    {
        $ticket = $service->issue(11, 1000001, str_repeat('a', 32), '10.0.0.8', 'Mozilla/5.0');
        $this->tickets[] = $ticket;

        return $ticket;
    }

    public function test_issued_ticket_is_random_and_expires_in_thirty_seconds(): void
    {
        $service = new WsTicketService();

        $first = $this->issue($service);
        $second = $this->issue($service);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{48}$/', $first);
        $this->assertNotSame($first, $second);
        $ttl = (int) Redis::ttl("ws:ticket:{$first}");
        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(WsTicketService::TTL, $ttl);
    }

    public function test_consume_returns_the_payload_exactly_once(): void
    {
        $service = new WsTicketService();
        $ticket = $this->issue($service);

        $this->assertSame(
            ['admin_id' => 11, 'ver' => 1000001, 'jti' => str_repeat('a', 32), 'ip' => '10.0.0.8', 'ua' => 'Mozilla/5.0'],
            $service->consume($ticket)
        );
        $this->assertNull($service->consume($ticket), '票据只能用一次');
    }

    public function test_consume_rejects_malformed_unknown_and_corrupt_tickets(): void
    {
        $service = new WsTicketService();

        $this->assertNull($service->consume(''));
        $this->assertNull($service->consume('../../etc'));
        $this->assertNull($service->consume(str_repeat('b', 48)));

        $corrupt = str_repeat('c', 48);
        $this->tickets[] = $corrupt;
        Redis::set("ws:ticket:{$corrupt}", '{"admin_id":"x"}', 'EX', 30);
        $this->assertNull($service->consume($corrupt));
    }

    public function test_user_agent_is_truncated_to_255_characters(): void
    {
        $service = new WsTicketService();
        $ticket = $service->issue(11, 1, str_repeat('d', 32), '10.0.0.8', str_repeat('浏', 300));
        $this->tickets[] = $ticket;

        $this->assertSame(255, mb_strlen((string) ($service->consume($ticket)['ua'] ?? '')));
    }
}
