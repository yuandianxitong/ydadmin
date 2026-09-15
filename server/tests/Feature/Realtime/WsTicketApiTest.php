<?php

declare(strict_types=1);

namespace tests\Feature\Realtime;

use app\service\realtime\WsTicketService;
use core\auth\TokenVersion;
use support\Container;
use support\Db;
use support\Redis;
use tests\Support\ApiTestCase;

/** spec §6：POST /adminapi/ws/ticket——登录即可、签发一次性票据、按管理员限流、不记操作日志。 */
final class WsTicketApiTest extends ApiTestCase
{
    private const URI = '/adminapi/ws/ticket';

    /** @var list<int> */
    private array $adminIds = [];

    protected function tearDown(): void
    {
        foreach ((array) Redis::keys('ws:ticket:*') as $key) {
            Redis::del((string) $key);
        }
        foreach ($this->adminIds as $id) {
            Redis::del("ws:ticket:rate:{$id}");
        }
        $this->adminIds = [];
        parent::tearDown();
    }

    private static function jtiOf(string $token): string
    {
        $claims = json_decode((string) base64_decode(strtr(explode('.', $token)[1], '-_', '+/')), true);

        return (string) ($claims['jti'] ?? '');
    }

    public function test_requires_login(): void
    {
        $this->post(self::URI)->assertCode(401);
    }

    public function test_issues_a_one_time_ticket_bound_to_the_calling_token(): void
    {
        $admin = $this->actingAsAdmin();
        $this->adminIds[] = $admin->id;

        $data = $this->post(self::URI, [], $admin->token, ['User-Agent' => 'phpunit-ws'])->assertOk()->data();

        $this->assertSame(WsTicketService::TTL, $data['expires_in']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{48}$/', (string) $data['ticket']);
        $service = Container::get(WsTicketService::class);
        $payload = $service->consume((string) $data['ticket']);
        $this->assertNotNull($payload);
        $this->assertSame($admin->id, $payload['admin_id']);
        $this->assertSame(TokenVersion::current($admin->id), $payload['ver']);
        $this->assertSame(self::jtiOf($admin->token), $payload['jti']);
        $this->assertSame('phpunit-ws', $payload['ua']);
        $this->assertNotSame('', $payload['ip']);
        $this->assertNull($service->consume((string) $data['ticket']), '票据只能用一次');
    }

    public function test_the_thirty_first_request_within_a_minute_is_rate_limited(): void
    {
        $admin = $this->actingAsAdmin();
        $this->adminIds[] = $admin->id;

        for ($i = 1; $i <= WsTicketService::RATE_LIMIT; $i++) {
            $this->post(self::URI, [], $admin->token)->assertOk();
        }
        $response = $this->post(self::URI, [], $admin->token)->assertCode(429);

        $this->assertSame(lang('business.ws_ticket_rate_limited'), $response->message());
    }

    public function test_ticket_requests_are_not_written_to_the_operation_log(): void
    {
        $admin = $this->actingAsAdmin();
        $this->adminIds[] = $admin->id;

        $this->post(self::URI, [], $admin->token)->assertOk();

        $this->assertSame(0, Db::table('admin_operation_logs')->where('admin_id', $admin->id)->count());
    }
}
