<?php

declare(strict_types=1);

namespace tests\RedLine;

use PHPUnit\Framework\Attributes\DataProvider;
use support\Db;
use tests\Support\ApiTestCase;
use tests\Support\TestUser;

/**
 * 红线（M6b spec §4.7、§7.3）：C 端会员不能读取或标记他人的站内信。
 *
 * 经真实路由（ApiAuthMiddleware + 控制器 + 服务 + 仓储）：列表与未读数只统计本人；read 传他人 id、本人与他人混传、
 * 伪造 user_id 字段、字符串 id、不传 ids（全部已读），都不能在 user_notification_reads 里给他人的通知留下已读行，
 * 他人的未读数与 is_read 不变。只传他人 id 时也不能退化成「本人全部已读」（spec：不属于本人的 id 静默忽略）。
 * 正向对照：受害者自己标记自己的通知照常生效。
 */
final class Test32_UserNotificationCrossUserTest extends ApiTestCase
{
    /** @var list<int> */
    private array $userIds = [];

    protected function tearDown(): void
    {
        try {
            if ($this->userIds !== []) {
                Db::table('user_notification_reads')->whereIn('user_id', $this->userIds)->delete();
                Db::table('user_notifications')->whereIn('user_id', $this->userIds)->delete();
            }
        } finally {
            $this->userIds = [];
            parent::tearDown();
        }
    }

    public function test_list_and_unread_count_only_cover_own_notifications(): void
    {
        [$me, $victim, $mine, $victims] = $this->scenario();

        $list = $this->get('/api/message/list', ['page_no' => 1, 'page_size' => 100], $me->token)->assertOk()->data();
        $this->assertSame($mine, $this->ids($list['list']), '列表只能含本人的通知');
        $this->assertSame(1, (int) $list['pagination']['total']);

        // 查询串里塞 user_id 也不能切换到别人
        $forged = $this->get('/api/message/list', ['page_no' => 1, 'page_size' => 100, 'user_id' => $victim->id], $me->token)->assertOk()->data();
        $this->assertSame($mine, $this->ids($forged['list']));
        foreach ($victims as $id) {
            $this->assertNotContains($id, $this->ids($forged['list']));
        }

        $this->assertSame(1, $this->unreadCount($me));
        $this->assertSame(2, $this->unreadCount($victim));
    }

    /** @return array<string, array{string}> */
    public static function hostileReadPayloads(): array
    {
        return [
            '只传他人的 id'        => ['victim_ids'],
            '本人与他人 id 混传'    => ['mixed_ids'],
            '伪造 user_id 字段'    => ['forged_user_id'],
            '字符串形式的他人 id'  => ['victim_ids_as_strings'],
            '不传 ids（全部已读）' => ['all'],
        ];
    }

    #[DataProvider('hostileReadPayloads')]
    public function test_read_never_marks_other_users_notifications(string $case): void
    {
        [$me, $victim, $mine, $victims] = $this->scenario();

        $payload = match ($case) {
            'victim_ids'            => ['ids' => $victims],
            'mixed_ids'             => ['ids' => [...$victims, ...$mine]],
            'forged_user_id'        => ['user_id' => $victim->id],
            'victim_ids_as_strings' => ['ids' => array_map('strval', $victims)],
            'all'                   => [],
            default                 => throw new \LogicException($case),
        };

        $response = $this->post('/api/message/read', $payload, $me->token);
        $this->assertContains($response->json()['code'] ?? null, [200, 422], $response->body());

        $this->assertSame(0, Db::table('user_notification_reads')->whereIn('notification_id', $victims)->count(), '他人的通知不得出现已读行');
        $this->assertSame(0, Db::table('user_notification_reads')->where('user_id', $me->id)->whereIn('notification_id', $victims)->count());
        $this->assertSame(2, $this->unreadCount($victim), '他人的未读数不变');
        $victimList = $this->get('/api/message/list', ['page_no' => 1, 'page_size' => 100], $victim->token)->assertOk()->data();
        foreach ($victimList['list'] as $row) {
            $this->assertFalse($row['is_read'], '他人的 is_read 不变');
        }

        $mineMarked = Db::table('user_notification_reads')->where('user_id', $me->id)->whereIn('notification_id', $mine)->count();
        if (in_array($case, ['victim_ids', 'victim_ids_as_strings'], true)) {
            $this->assertSame(0, $mineMarked, '只传他人 id 时不能退化成「本人全部已读」');
        } else {
            $this->assertSame(1, $mineMarked, '本人的通知照常标记（混传、伪造字段被忽略、全部已读）');
        }
    }

    public function test_positive_control_victim_marks_own_notification(): void
    {
        [$me, $victim, , $victims] = $this->scenario();

        $this->post('/api/message/read', ['ids' => [$victims[0]]], $victim->token)->assertOk();

        $this->assertSame(1, $this->unreadCount($victim));
        $rows = $this->get('/api/message/list', ['page_no' => 1, 'page_size' => 100], $victim->token)->assertOk()->data()['list'];
        $readFlags = array_column($rows, 'is_read', 'id');
        $this->assertTrue($readFlags[$victims[0]]);
        $this->assertFalse($readFlags[$victims[1]]);
        $this->assertSame(1, $this->unreadCount($me), '受害者自己标记不影响别人');
    }

    /** @return array{TestUser, TestUser, list<int>, list<int>} [本人, 受害者, 本人通知 id, 受害者通知 id] */
    private function scenario(): array
    {
        $me = $this->actingAsUser();
        $victim = $this->actingAsUser();
        $this->userIds = [$me->id, $victim->id];

        $mine = [$this->notification($me->id, '本人通知')];
        $victims = [$this->notification($victim->id, '他人通知一'), $this->notification($victim->id, '他人通知二')];

        return [$me, $victim, $mine, $victims];
    }

    private function notification(int $userId, string $title): int
    {
        $now = date('Y-m-d H:i:s');

        return (int) Db::table('user_notifications')->insertGetId([
            'user_id'    => $userId,
            'title'      => $title . bin2hex(random_bytes(3)),
            'content'    => '红线 32 夹具',
            'type'       => 'system',
            'biz_id'     => '',
            'extra'      => json_encode(['template_code' => 't32'], JSON_THROW_ON_ERROR),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function unreadCount(TestUser $user): int
    {
        return (int) $this->get('/api/message/unread-count', [], $user->token)->assertOk()->data()['count'];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<int>
     */
    private function ids(array $rows): array
    {
        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }
}
