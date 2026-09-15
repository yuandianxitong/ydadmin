<?php

declare(strict_types=1);

namespace tests\Feature\System;

use app\service\realtime\PresenceService;
use core\auth\TokenVersion;
use core\realtime\RealtimeMessage;
use core\realtime\RealtimePublisher;
use support\Container;
use support\Redis;
use tests\Support\ApiTestCase;

/** spec §6：GET /adminapi/system/online、POST /adminapi/system/online/{adminId}/logout。 */
final class OnlineAdminApiTest extends ApiTestCase
{
    private const BASE = '/adminapi/system/online';

    /** @var list<int> 本用例写过在线状态的管理员 */
    private array $onlineIds = [];

    protected function tearDown(): void
    {
        foreach ($this->onlineIds as $id) {
            Redis::del("ws:online:{$id}");
            Redis::zRem(PresenceService::INDEX_KEY, (string) $id);
        }
        $this->onlineIds = [];
        parent::tearDown();
    }

    private function goOnline(int $adminId, int $connections = 1): void
    {
        $presence = Container::get(PresenceService::class);
        for ($i = 1; $i <= $connections; $i++) {
            $presence->join($adminId, "test:{$adminId}:{$i}", ['ip' => "10.1.0.{$i}", 'ua' => "ua-{$i}", 'connected_at' => date('Y-m-d H:i:s')]);
        }
        $this->onlineIds[] = $adminId;
    }

    /** @return list<array<string, mixed>> */
    private function rowsFor(string $token): array
    {
        return (array) $this->get(self::BASE, ['page' => 1, 'limit' => 100], $token)->assertOk()->data()['list'];
    }

    public function test_requires_permissions(): void
    {
        $nobody = $this->actingAsAdmin();

        $this->get(self::BASE, [], $nobody->token)->assertCode(403);
        $this->post(self::BASE . '/1/logout', [], $nobody->token)->assertCode(403);
    }

    public function test_list_aggregates_connections_per_admin_with_profile_fields(): void
    {
        $viewer = $this->actingAsAdmin(['system.online.list']);
        $target = $this->actingAsAdmin([], ['nickname' => '在线探针']);
        $this->goOnline($target->id, 2);

        $response = $this->get(self::BASE, ['page' => 1, 'limit' => 100], $viewer->token)->assertOk();
        $data = $response->data();
        $this->assertSame(['list', 'pagination'], array_keys($data));
        $this->assertSame(['current_page', 'per_page', 'total', 'last_page'], array_keys($data['pagination']));

        $rows = array_values(array_filter($data['list'], static fn (array $r): bool => $r['admin_id'] === $target->id));
        $this->assertCount(1, $rows, '同一管理员多个连接聚合成一行');
        $row = $rows[0];
        $this->assertSame(['admin_id', 'username', 'nickname', 'connections', 'ip', 'ua', 'connected_at', 'last_seen'], array_keys($row));
        $this->assertSame($target->username, $row['username']);
        $this->assertSame('在线探针', $row['nickname']);
        $this->assertSame(2, $row['connections']);
    }

    public function test_list_hides_admins_outside_the_data_scope(): void
    {
        // data_scope 4 = 仅本人：只看得见自己
        $viewer = $this->actingAsAdmin(['system.online.list', 'system.online.logout'], [], ['data_scope' => 4]);
        $other = $this->actingAsAdmin();
        $this->goOnline($other->id);
        $this->goOnline($viewer->id);

        $ids = array_column($this->rowsFor($viewer->token), 'admin_id');
        $this->assertNotContains($other->id, $ids);
        $this->assertContains($viewer->id, $ids);

        $this->post(self::BASE . "/{$other->id}/logout", [], $viewer->token)->assertCode(404);
    }

    public function test_cannot_kick_self_or_a_super_admin(): void
    {
        $operator = $this->actingAsAdmin('super');
        $super = $this->actingAsAdmin('super');

        $self = $this->post(self::BASE . "/{$operator->id}/logout", [], $operator->token)->assertCode(422);
        $this->assertSame(lang('validation.online_kick_self'), $self->data()['errors']['admin_id']);

        $versionBefore = TokenVersion::current($super->id);
        $superResponse = $this->post(self::BASE . "/{$super->id}/logout", [], $operator->token)->assertCode(422);
        $this->assertSame(lang('validation.online_kick_super'), $superResponse->data()['errors']['admin_id']);
        $this->assertSame($versionBefore, TokenVersion::current($super->id), '被拒绝时不能吊销');
    }

    public function test_kick_revokes_the_target_and_clears_presence(): void
    {
        $operator = $this->actingAsAdmin(['system.online.list', 'system.online.logout']);
        $target = $this->actingAsAdmin();
        $this->get('/adminapi/auth/info', [], $target->token)->assertOk();
        $this->goOnline($target->id, 3);

        $response = $this->post(self::BASE . "/{$target->id}/logout", [], $operator->token)->assertOk();

        $this->assertSame(['kicked' => 3], $response->data());
        $this->get('/adminapi/auth/info', [], $target->token)->assertCode(401);
        $this->assertNotContains($target->id, array_column($this->rowsFor($operator->token), 'admin_id'));
    }

    public function test_kicking_an_offline_admin_still_revokes(): void
    {
        $operator = $this->actingAsAdmin(['system.online.logout']);
        $target = $this->actingAsAdmin();

        $this->assertSame(['kicked' => 0], $this->post(self::BASE . "/{$target->id}/logout", [], $operator->token)->assertOk()->data());
        $this->get('/adminapi/auth/info', [], $target->token)->assertCode(401);
    }

    public function test_unknown_admin_is_404(): void
    {
        $operator = $this->actingAsAdmin('super');

        $this->post(self::BASE . '/999999999/logout', [], $operator->token)->assertCode(404);
    }

    /**
     * spec §8：强制下线必须经 realtime:admin 通道真实投递 force_logout，不能只停在吊销 token 版本号。
     * 用原始 RESP SUBSCRIBE 连一条独立的 Redis 连接（不经容器、不受 HTTP 请求生命周期影响），
     * 在发起下线请求之前订阅，避免消息发布早于订阅导致收不到（PUBLISH 不排队给迟到的订阅者）。
     */
    public function test_logout_publishes_force_logout_to_a_real_redis_subscriber(): void
    {
        $operator = $this->actingAsAdmin(['system.online.logout']);
        $target = $this->actingAsAdmin();

        $host = (string) config('redis.default.host');
        $port = (int) config('redis.default.port');
        $password = (string) config('redis.default.password');
        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 2);
        $this->assertIsResource($socket, "连不上 Redis：{$errstr}");
        stream_set_timeout($socket, 2);

        try {
            if ($password !== '') {
                fwrite($socket, self::resp(['AUTH', $password]));
                fgets($socket);
            }
            fwrite($socket, self::resp(['SUBSCRIBE', RealtimePublisher::CHANNEL]));
            $this->assertSame(['subscribe', RealtimePublisher::CHANNEL, 1], self::readArray($socket));

            $this->post(self::BASE . "/{$target->id}/logout", [], $operator->token)->assertOk();

            $frame = self::readArray($socket);
            $this->assertFalse(stream_get_meta_data($socket)['timed_out'], '2 秒内没有收到发布的 force_logout 消息');
            $this->assertSame('message', $frame[0]);
            $this->assertSame(RealtimePublisher::CHANNEL, $frame[1]);
            $decoded = RealtimeMessage::decode((string) $frame[2]);
            $this->assertNotNull($decoded);
            $this->assertSame('force_logout', $decoded->event);
            $this->assertSame([$target->id], $decoded->targets);
            $this->assertSame('kicked', $decoded->payload['reason']);
        } finally {
            fclose($socket);
        }
    }

    /** @param list<string> $parts */
    private static function resp(array $parts): string
    {
        $out = '*' . count($parts) . "\r\n";
        foreach ($parts as $part) {
            $out .= '$' . strlen($part) . "\r\n{$part}\r\n";
        }

        return $out;
    }

    /**
     * 读一个 RESP 数组帧（元素只可能是批量字符串或整数）；读超时返回已读到的部分。
     *
     * @param resource $socket
     * @return list<int|string>
     */
    private static function readArray($socket): array
    {
        $header = fgets($socket);
        if (!is_string($header) || !str_starts_with($header, '*')) {
            return [];
        }
        $items = [];
        for ($i = 0, $n = (int) substr($header, 1); $i < $n; $i++) {
            $line = fgets($socket);
            if (!is_string($line)) {
                break;
            }
            if ($line[0] === ':') {
                $items[] = (int) substr($line, 1);

                continue;
            }
            $length = (int) substr($line, 1);
            $data = '';
            while (strlen($data) < $length + 2) {
                $chunk = fread($socket, $length + 2 - strlen($data));
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $data .= $chunk;
            }
            $items[] = substr($data, 0, $length);
        }

        return $items;
    }
}
