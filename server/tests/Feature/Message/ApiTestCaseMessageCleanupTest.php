<?php

declare(strict_types=1);

namespace tests\Feature\Message;

use support\Db;
use tests\Support\ApiTestCase;

/**
 * 设计决定 26：注册、充值到账会在测试进程里同步写站内信与消息日志，被测代码自己注册的会员用例拿不到 id。
 * ApiTestCase 的清理要按「会员已不存在」兜底删掉这些孤儿行，且不误删仍存在会员的行。
 */
final class ApiTestCaseMessageCleanupTest extends ApiTestCase
{
    private int $survivorId = 0;

    protected function tearDown(): void
    {
        try {
            if ($this->survivorId > 0) {
                Db::table('user_notification_reads')->where('user_id', $this->survivorId)->delete();
                Db::table('user_notifications')->where('user_id', $this->survivorId)->delete();
                Db::table('message_logs')->where('user_id', $this->survivorId)->delete();
                Db::table('users')->where('id', $this->survivorId)->delete();
            }
        } finally {
            parent::tearDown();
        }
    }

    /** @return array{notification: int, log: int} */
    private function insertMessageRows(int $userId): array
    {
        $now = date('Y-m-d H:i:s');
        $notificationId = (int) Db::table('user_notifications')->insertGetId([
            'user_id' => $userId, 'title' => 't', 'content' => 'c', 'type' => 'system', 'biz_id' => '', 'created_at' => $now, 'updated_at' => $now,
        ]);
        Db::table('user_notification_reads')->insert(['notification_id' => $notificationId, 'user_id' => $userId, 'read_at' => $now]);
        $logId = (int) Db::table('message_logs')->insertGetId([
            'template_code' => 'user_register', 'channel' => 'site', 'user_id' => $userId, 'receiver' => "user#{$userId}",
            'status' => 1, 'error_msg' => '', 'attempts' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);

        return ['notification' => $notificationId, 'log' => $logId];
    }

    public function test_cleanup_removes_message_rows_of_deleted_members_only(): void
    {
        $tracked = $this->actingAsUser();
        $orphan = $this->insertMessageRows($tracked->id);

        $now = date('Y-m-d H:i:s');
        $this->survivorId = (int) Db::table('users')->insertGetId([
            'nickname' => 'survivor', 'mobile' => '13' . random_int(100_000_000, 999_999_999), 'status' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $kept = $this->insertMessageRows($this->survivorId);

        (new \ReflectionMethod(ApiTestCase::class, 'cleanupFixtures'))->invoke($this);

        $this->assertSame(0, Db::table('users')->where('id', $tracked->id)->count());
        $this->assertSame(0, Db::table('user_notifications')->where('id', $orphan['notification'])->count());
        $this->assertSame(0, Db::table('user_notification_reads')->where('notification_id', $orphan['notification'])->count());
        $this->assertSame(0, Db::table('message_logs')->where('id', $orphan['log'])->count());

        $this->assertSame(1, Db::table('user_notifications')->where('id', $kept['notification'])->count());
        $this->assertSame(1, Db::table('user_notification_reads')->where('notification_id', $kept['notification'])->count());
        $this->assertSame(1, Db::table('message_logs')->where('id', $kept['log'])->count());
    }
}
