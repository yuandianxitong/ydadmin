<?php

declare(strict_types=1);

namespace tests\Feature\Api;

use support\Db;
use tests\Support\ApiTestCase;
use tests\Support\TestUser;

/** C 端站内信（M6b spec §4.7）：只含本人、is_read 为 bool、未读数、按 id 或全部标记已读（幂等，他人 id 静默忽略）。 */
final class MessageApiTest extends ApiTestCase
{
    /** @var list<int> 本用例创建的会员 id：tearDown 先删他们的已读行，再交给父类删通知与会员 */
    private array $userIds = [];

    protected function tearDown(): void
    {
        try {
            if ($this->userIds !== []) {
                Db::table('user_notification_reads')->whereIn('user_id', $this->userIds)->delete();
            }
        } finally {
            $this->userIds = [];
            parent::tearDown();
        }
    }

    private function member(): TestUser
    {
        $user = $this->actingAsUser();
        $this->userIds[] = $user->id;

        return $user;
    }

    /** @param array<string, mixed> $attributes */
    private function notify(int $userId, array $attributes = []): int
    {
        $now = date('Y-m-d H:i:s');
        $id = (int) Db::table('user_notifications')->insertGetId(array_merge([
            'user_id'    => $userId,
            'title'      => '标题' . bin2hex(random_bytes(3)),
            'content'    => '正文' . bin2hex(random_bytes(3)),
            'type'       => 'system',
            'biz_id'     => '',
            'extra'      => json_encode(['template_code' => 'user_register']),
            'created_at' => $now,
            'updated_at' => $now,
        ], $attributes));
        $this->track('user_notifications', $id);

        return $id;
    }

    private function markReadInDb(int $notificationId, int $userId, string $readAt = '2020-01-01 00:00:00'): void
    {
        Db::table('user_notification_reads')->insert(['notification_id' => $notificationId, 'user_id' => $userId, 'read_at' => $readAt]);
    }

    /** @return list<int> */
    private function readIdsOf(int $userId): array
    {
        return array_map('intval', Db::table('user_notification_reads')->where('user_id', $userId)->orderBy('notification_id')->pluck('notification_id')->all());
    }

    private function unreadCount(TestUser $user): int
    {
        $data = $this->get('/api/message/unread-count', [], $user->token)->assertOk()->data();
        $this->assertSame(['count'], array_keys($data));
        $this->assertIsInt($data['count']);

        return $data['count'];
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->get('/api/message/list')->assertCode(401);
        $this->get('/api/message/unread-count')->assertCode(401);
        $this->post('/api/message/read', ['ids' => [1]])->assertCode(401);

        // 管理端 token 不能冒充会员
        $admin = $this->actingAsAdmin('super');
        $this->get('/api/message/list', [], $admin->token)->assertCode(401);
    }

    public function test_list_returns_only_own_notifications_newest_first_with_is_read(): void
    {
        $me = $this->member();
        $other = $this->member();
        $older = $this->notify($me->id, ['type' => 'payment', 'biz_id' => 'R202609170001', 'extra' => json_encode(['template_code' => 'payment_success'])]);
        $read = $this->notify($me->id);
        $newest = $this->notify($me->id);
        $theirs = $this->notify($other->id);
        $this->markReadInDb($read, $me->id);
        $this->markReadInDb($newest, $other->id); // 别人对我的通知留下的已读行（接口造不出来）不算我已读

        $data = $this->get('/api/message/list', ['page_no' => 1, 'page_size' => 50], $me->token)->assertOk()->data();

        $this->assertSame(['list', 'pagination'], array_keys($data));
        $this->assertSame([$newest, $read, $older], array_column($data['list'], 'id'));
        $this->assertNotContains($theirs, array_column($data['list'], 'id'));
        $this->assertSame([false, true, false], array_column($data['list'], 'is_read'), 'is_read 必须是 bool');

        $expectedKeys = ['biz_id', 'content', 'created_at', 'extra', 'id', 'is_read', 'title', 'type'];
        foreach ($data['list'] as $row) {
            $keys = array_keys($row);
            sort($keys);
            $this->assertSame($expectedKeys, $keys, 'spec §4.7 的行字段，不多不少（不暴露 user_id）');
        }
        $last = $data['list'][2];
        $this->assertSame('payment', $last['type']);
        $this->assertSame('R202609170001', $last['biz_id']);
        $this->assertSame(['template_code' => 'payment_success'], $last['extra']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $last['created_at']);

        $theirList = $this->get('/api/message/list', [], $other->token)->assertOk()->data()['list'];
        $this->assertSame([$theirs], array_column($theirList, 'id'));
        $this->assertSame([false], array_column($theirList, 'is_read'));
    }

    public function test_list_accepts_c_end_and_admin_pagination_params(): void
    {
        $me = $this->member();
        $first = $this->notify($me->id);
        $this->notify($me->id);
        $this->notify($me->id);

        $cEnd = $this->get('/api/message/list', ['page_no' => 2, 'page_size' => 2], $me->token)->assertOk()->data();
        $this->assertSame(['current_page' => 2, 'per_page' => 2, 'total' => 3, 'last_page' => 2], $cEnd['pagination']);
        $this->assertSame([$first], array_column($cEnd['list'], 'id'));

        $adminStyle = $this->get('/api/message/list', ['page' => 2, 'limit' => 2], $me->token)->assertOk()->data();
        $this->assertSame($cEnd, $adminStyle);

        $empty = $this->get('/api/message/list', ['page_no' => 1, 'page_size' => 10], $this->member()->token)->assertOk()->data();
        $this->assertSame([], $empty['list']);
        $this->assertSame(['current_page' => 1, 'per_page' => 10, 'total' => 0, 'last_page' => 1], $empty['pagination']);
    }

    public function test_unread_count_counts_only_own_unread(): void
    {
        $me = $this->member();
        $other = $this->member();
        $a = $this->notify($me->id);
        $this->notify($me->id);
        $this->notify($me->id);
        $theirs = $this->notify($other->id);
        $this->markReadInDb($a, $me->id);
        $this->markReadInDb($theirs, $me->id); // 我对别人通知的已读行（接口造不出来）不能把我的未读数减下去

        $this->assertSame(2, $this->unreadCount($me));
        $this->assertSame(1, $this->unreadCount($other));
        $this->assertSame(0, $this->unreadCount($this->member()));
    }

    public function test_read_specific_ids_ignores_other_users_ids(): void
    {
        $me = $this->member();
        $other = $this->member();
        $mine = $this->notify($me->id);
        $mineToo = $this->notify($me->id);
        $theirs = $this->notify($other->id);

        $response = $this->post('/api/message/read', ['ids' => [$mine, $theirs, 999999999]], $me->token);
        $response->assertOk();
        $this->assertSame([], $response->data());

        $this->assertSame([$mine], $this->readIdsOf($me->id), '只标记属于本人的 id');
        $this->assertSame([], $this->readIdsOf($other->id), '他人的 id 静默忽略，不替他标记');
        $this->assertSame(0, Db::table('user_notification_reads')->where('notification_id', $theirs)->count());
        $this->assertSame(1, $this->unreadCount($me));
        $this->assertSame(1, $this->unreadCount($other));

        $list = array_column($this->get('/api/message/list', [], $me->token)->assertOk()->data()['list'], 'is_read', 'id');
        $this->assertSame([$mineToo => false, $mine => true], $list);

        // 只给他人的 id：成功返回，什么都不写
        $this->post('/api/message/read', ['ids' => [$theirs]], $me->token)->assertOk();
        $this->assertSame([$mine], $this->readIdsOf($me->id));
        $this->assertSame([], $this->readIdsOf($other->id));
    }

    public function test_read_without_ids_marks_all_own_unread_only(): void
    {
        // uniapp markAsRead() 不传 ids 时请求体是 {}；另两种等价写法 ids=null、ids=[]
        foreach (['missing' => [], 'null' => ['ids' => null], 'empty' => ['ids' => []]] as $case => $body) {
            $me = $this->member();
            $other = $this->member();
            $a = $this->notify($me->id);
            $b = $this->notify($me->id);
            $alreadyRead = $this->notify($me->id);
            $theirs = $this->notify($other->id);
            $this->markReadInDb($alreadyRead, $me->id, '2020-01-01 00:00:00');

            $response = $this->post('/api/message/read', $body, $me->token);
            $response->assertOk();
            $this->assertSame([], $response->data(), $case);

            $expected = [$a, $b, $alreadyRead];
            sort($expected);
            $this->assertSame($expected, $this->readIdsOf($me->id), $case);
            $this->assertSame(
                '2020-01-01 00:00:00',
                (string) Db::table('user_notification_reads')->where('notification_id', $alreadyRead)->where('user_id', $me->id)->value('read_at'),
                "{$case}：已读行不重写（INSERT IGNORE）"
            );
            $this->assertSame(0, $this->unreadCount($me), $case);
            $this->assertSame(1, $this->unreadCount($other), "{$case}：别人的未读不受影响");
            $this->assertSame(0, Db::table('user_notification_reads')->where('notification_id', $theirs)->count(), $case);
        }
    }

    public function test_read_is_idempotent(): void
    {
        $me = $this->member();
        $target = $this->notify($me->id);
        $this->notify($me->id);

        $this->post('/api/message/read', ['ids' => [$target]], $me->token)->assertOk();
        $this->post('/api/message/read', ['ids' => [$target, $target]], $me->token)->assertOk();
        $this->post('/api/message/read', [], $me->token)->assertOk();
        $this->post('/api/message/read', [], $me->token)->assertOk();

        $this->assertSame(1, Db::table('user_notification_reads')->where('notification_id', $target)->where('user_id', $me->id)->count(), '唯一键防重，重复标记不报错也不多写');
        $this->assertCount(2, $this->readIdsOf($me->id));
        $this->assertSame(0, $this->unreadCount($me));
    }

    public function test_read_rejects_malformed_ids(): void
    {
        $me = $this->member();
        $this->notify($me->id);

        $this->assertArrayHasKey('ids', $this->post('/api/message/read', ['ids' => 'all'], $me->token)->assertCode(422)->data()['errors']);
        $this->assertArrayHasKey('ids.0', $this->post('/api/message/read', ['ids' => ['abc']], $me->token)->assertCode(422)->data()['errors']);
        $this->assertArrayHasKey('ids.0', $this->post('/api/message/read', ['ids' => [0]], $me->token)->assertCode(422)->data()['errors']);

        $this->assertSame([], $this->readIdsOf($me->id), '校验失败不能退化成「全部已读」');
        $this->assertSame(1, $this->unreadCount($me));
    }
}
