<?php

declare(strict_types=1);

namespace tests\Feature\Message;

use app\repository\message\UserNotificationReadRepository;
use app\repository\message\UserNotificationRepository;
use support\Db;
use tests\TestCase;

/**
 * spec §2.3 / §2.4 / §4.7：站内信按 user_id 显式隔离；已读按 (notification_id, user_id) 幂等写入。
 * 用户 id 取随机大数，避开真实会员（user_notifications 没有外键）。
 */
final class UserNotificationRepositoryTest extends TestCase
{
    private int $userId;

    private int $otherUserId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = random_int(900_000_000, 949_999_999);
        $this->otherUserId = $this->userId + 50_000_000;
    }

    protected function tearDown(): void
    {
        try {
            $users = [$this->userId, $this->otherUserId];
            Db::table('user_notification_reads')->whereIn('user_id', $users)->delete();
            Db::table('user_notifications')->whereIn('user_id', $users)->delete();
        } finally {
            parent::tearDown();
        }
    }

    private function notification(int $userId, string $title): int
    {
        $now = date('Y-m-d H:i:s');

        return (int) Db::table('user_notifications')->insertGetId([
            'user_id'    => $userId,
            'title'      => $title,
            'content'    => $title . '正文',
            'type'       => 'system',
            'extra'      => json_encode(['template_code' => 'user_register']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function test_paginate_for_user_only_lists_own_rows_with_is_read(): void
    {
        $first = $this->notification($this->userId, '一');
        $second = $this->notification($this->userId, '二');
        $third = $this->notification($this->userId, '三');
        $this->notification($this->otherUserId, '别人的');
        (new UserNotificationReadRepository())->markRead($this->userId, [$second]);
        $repo = new UserNotificationRepository();

        $page1 = $repo->paginateForUser($this->userId, 1, 2);
        $this->assertSame([$third, $second], array_map('intval', array_column($page1['list'], 'id')), 'id 倒序');
        $this->assertSame([false, true], array_column($page1['list'], 'is_read'));
        $this->assertSame(['template_code' => 'user_register'], $page1['list'][0]['extra'], 'extra 按 array cast 取回');
        $keys = array_keys($page1['list'][0]);
        sort($keys);
        $this->assertSame(['biz_id', 'content', 'created_at', 'extra', 'id', 'is_read', 'title', 'type'], $keys, '行键集合固定');
        $this->assertSame(['current_page' => 1, 'per_page' => 2, 'total' => 3, 'last_page' => 2], $page1['pagination']);

        $page2 = $repo->paginateForUser($this->userId, 2, 2);
        $this->assertSame([$first], array_map('intval', array_column($page2['list'], 'id')));
    }

    public function test_unread_count_and_ids(): void
    {
        $a = $this->notification($this->userId, 'a');
        $b = $this->notification($this->userId, 'b');
        $c = $this->notification($this->userId, 'c');
        $others = $this->notification($this->otherUserId, 'x');
        // 别人读了自己的通知，不影响本人未读
        (new UserNotificationReadRepository())->markRead($this->otherUserId, [$others, $a]);
        (new UserNotificationReadRepository())->markRead($this->userId, [$b]);
        $repo = new UserNotificationRepository();

        $this->assertSame(2, $repo->countUnreadForUser($this->userId));
        $this->assertSame([$a, $c], $repo->unreadIdsForUser($this->userId));
        $this->assertSame(0, $repo->countUnreadForUser($this->otherUserId), '他人读了自己那条；读本人的 id 不影响任何人的计数');
    }

    public function test_filter_owned_ids_drops_foreign_missing_and_invalid_ids(): void
    {
        $mine = $this->notification($this->userId, 'mine');
        $mine2 = $this->notification($this->userId, 'mine2');
        $foreign = $this->notification($this->otherUserId, 'foreign');
        $repo = new UserNotificationRepository();

        $this->assertSame([$mine, $mine2], $repo->filterOwnedIds($this->userId, [$mine2, $foreign, $mine, $mine, 0, -3, 999_999_999_999]));
        $this->assertSame([], $repo->filterOwnedIds($this->userId, []));
        $this->assertSame([], $repo->filterOwnedIds($this->userId, [$foreign]));
    }

    public function test_mark_read_is_idempotent_and_returns_inserted_rows(): void
    {
        $a = $this->notification($this->userId, 'a');
        $b = $this->notification($this->userId, 'b');
        $reads = new UserNotificationReadRepository();

        $this->assertSame(1, $reads->markRead($this->userId, [$a]));
        $firstReadAt = Db::table('user_notification_reads')->where('notification_id', $a)->where('user_id', $this->userId)->value('read_at');
        $this->assertSame(1, $reads->markRead($this->userId, [$a, $b, $b]), '已读的忽略，重复 id 去重');
        $this->assertSame(0, $reads->markRead($this->userId, [$a, $b]));
        $this->assertSame(0, $reads->markRead($this->userId, []));
        $this->assertSame(2, Db::table('user_notification_reads')->where('user_id', $this->userId)->count());
        $this->assertSame($firstReadAt, Db::table('user_notification_reads')->where('notification_id', $a)->where('user_id', $this->userId)->value('read_at'), '保留第一次 read_at');
    }
}
