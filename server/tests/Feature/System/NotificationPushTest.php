<?php

declare(strict_types=1);

namespace tests\Feature\System;

use app\service\system\NotificationService;
use core\realtime\RealtimePublisher;
use support\Container;
use support\Db;
use tests\Support\ApiTestCase;

/** 记录 publish() 调用而不真正发 Redis。 */
final class SpyRealtimePublisher extends RealtimePublisher
{
    /** @var list<array{targets: string|array<int, int>, event: string, payload: array<string, mixed>}> */
    public array $calls = [];

    public function publish(string|array $targets, string $event, array $payload): void
    {
        $this->calls[] = ['targets' => $targets, 'event' => $event, 'payload' => $payload];
    }
}

/** 底层发送失败：走真实 publish() 的 try/catch，验证推送失败不影响业务。 */
final class FailingRealtimePublisher extends RealtimePublisher
{
    public int $attempts = 0;

    protected function publishRaw(string $channel, string $message): void
    {
        $this->attempts++;
        throw new \RuntimeException('Redis 不可用');
    }
}

/**
 * spec §6「推送时机」：发布（新建 status=1 或 0→1）在事务提交后推 notification.created；
 * 广播 targets='all'，指定通知 targets=收件人 id 列表；编辑已发布不推送；推送失败不影响业务。
 */
final class NotificationPushTest extends ApiTestCase
{
    private const BASE = '/adminapi/system/notification';

    private const ALL = ['system.notification.list', 'system.notification.create', 'system.notification.update', 'system.notification.delete'];

    private ?RealtimePublisher $original = null;

    private function swapPublisher(RealtimePublisher $publisher): void
    {
        $service = Container::get(NotificationService::class);
        $property = new \ReflectionProperty($service, 'realtimePublisher');
        $this->original ??= $property->getValue($service);
        $property->setValue($service, $publisher);
    }

    protected function tearDown(): void
    {
        if ($this->original !== null) {
            (new \ReflectionProperty(NotificationService::class, 'realtimePublisher'))->setValue(Container::get(NotificationService::class), $this->original);
            $this->original = null;
        }
        Db::table('notification_reads')->whereNotIn('notification_id', Db::table('notifications')->pluck('id'))->delete();
        parent::tearDown();
    }

    /** @param array<string, mixed> $body */
    private function store(string $token, array $body): int
    {
        $id = (int) $this->post(self::BASE, array_merge(['title' => '推送' . bin2hex(random_bytes(3)), 'content' => '正文', 'type' => 2], $body), $token)->assertOk()->data()['id'];
        $this->track('notifications', $id);

        return $id;
    }

    public function test_publishing_a_broadcast_pushes_once_to_all_with_the_contract_payload(): void
    {
        $spy = new SpyRealtimePublisher();
        $this->swapPublisher($spy);
        $admin = $this->actingAsAdmin(self::ALL);

        $id = $this->store($admin->token, ['title' => '全员公告']);

        $this->assertCount(1, $spy->calls);
        $call = $spy->calls[0];
        $this->assertSame('all', $call['targets']);
        $this->assertSame('notification.created', $call['event']);
        $row = Db::table('notifications')->where('id', $id)->first();
        $this->assertSame(['id' => $id, 'title' => '全员公告', 'type' => 2, 'created_at' => (string) $row->created_at], $call['payload']);
    }

    public function test_publishing_a_targeted_notification_pushes_to_recipients_only(): void
    {
        $spy = new SpyRealtimePublisher();
        $this->swapPublisher($spy);
        $admin = $this->actingAsAdmin(self::ALL);
        $a = $this->actingAsAdmin();
        $b = $this->actingAsAdmin();

        $this->store($admin->token, ['target_type' => 2, 'admin_ids' => [$b->id, $a->id]]);

        $this->assertCount(1, $spy->calls);
        $expected = [$a->id, $b->id];
        sort($expected);
        $this->assertSame($expected, $spy->calls[0]['targets']);
    }

    public function test_drafts_and_edits_of_published_notifications_do_not_push(): void
    {
        $spy = new SpyRealtimePublisher();
        $this->swapPublisher($spy);
        $admin = $this->actingAsAdmin(self::ALL);

        $draft = $this->store($admin->token, ['status' => 0]);
        $this->put(self::BASE . "/{$draft}", ['title' => '草稿改标题'], $admin->token)->assertOk();
        $this->assertSame([], $spy->calls, '草稿新建与草稿编辑都不推送');

        $spy->calls = [];
        $published = $this->store($admin->token, []);
        $spy->calls = [];
        $this->put(self::BASE . "/{$published}", ['title' => '已发布改标题', 'status' => 1], $admin->token)->assertOk();
        $this->assertSame([], $spy->calls, '编辑已发布通知（status 1→1）不推送');
    }

    public function test_draft_turned_published_pushes_once_with_current_recipients(): void
    {
        $spy = new SpyRealtimePublisher();
        $this->swapPublisher($spy);
        $admin = $this->actingAsAdmin(self::ALL);
        $a = $this->actingAsAdmin();
        $b = $this->actingAsAdmin();
        $id = $this->store($admin->token, ['status' => 0, 'target_type' => 2, 'admin_ids' => [$a->id]]);

        $this->put(self::BASE . "/{$id}", ['status' => 1, 'admin_ids' => [$b->id]], $admin->token)->assertOk();

        $this->assertCount(1, $spy->calls);
        $this->assertSame([$b->id], $spy->calls[0]['targets'], '收件人取替换后的名单（本例 a 未读被移除）');
        $this->assertSame($id, $spy->calls[0]['payload']['id']);
    }

    public function test_rejected_requests_do_not_push(): void
    {
        $spy = new SpyRealtimePublisher();
        $this->swapPublisher($spy);
        $admin = $this->actingAsAdmin(self::ALL);

        $this->post(self::BASE, ['title' => '越界', 'content' => '正文', 'type' => 1, 'target_type' => 2, 'admin_ids' => [999999999]], $admin->token)->assertCode(422);

        $this->assertSame([], $spy->calls);
    }

    public function test_a_failing_push_never_breaks_publishing(): void
    {
        $failing = new FailingRealtimePublisher();
        $this->swapPublisher($failing);
        $admin = $this->actingAsAdmin(self::ALL);

        $response = $this->post(self::BASE, ['title' => '推送失败', 'content' => '正文', 'type' => 1], $admin->token)->assertOk();
        $id = (int) $response->data()['id'];
        $this->track('notifications', $id);

        $this->assertSame(1, $failing->attempts, '确实尝试过推送');
        $this->assertSame(1, (int) Db::table('notifications')->where('id', $id)->value('status'), '通知已落库');
    }
}
