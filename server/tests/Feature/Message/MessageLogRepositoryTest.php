<?php

declare(strict_types=1);

namespace tests\Feature\Message;

use app\repository\message\MessageLogRepository;
use support\Db;
use tests\TestCase;

/**
 * spec §2.2 / §4.4：attempts 自增、finishIfPending 只改待发行（并发重复投递只落一次结果）、管理端列表过滤。
 */
final class MessageLogRepositoryTest extends TestCase
{
    /** @var list<int> */
    private array $ids = [];

    private string $code;

    protected function setUp(): void
    {
        parent::setUp();
        $this->code = 'rl' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        try {
            if ($this->ids !== []) {
                Db::table('message_logs')->whereIn('id', $this->ids)->delete();
            }
        } finally {
            $this->ids = [];
            parent::tearDown();
        }
    }

    /** @param array<string, mixed> $attributes */
    private function log(array $attributes = []): int
    {
        $now = date('Y-m-d H:i:s');
        $id = (int) Db::table('message_logs')->insertGetId(array_merge([
            'template_code' => $this->code,
            'channel'       => 'sms',
            'receiver'      => '138****0000',
            'status'        => 0,
            'created_at'    => $now,
            'updated_at'    => $now,
        ], $attributes));
        $this->ids[] = $id;

        return $id;
    }

    public function test_constants(): void
    {
        $this->assertSame([0, 1, 2], [MessageLogRepository::STATUS_PENDING, MessageLogRepository::STATUS_SUCCESS, MessageLogRepository::STATUS_FAILED]);
        $this->assertSame(
            ['sms', 'wechat_official', 'wechat_mini', 'site'],
            [MessageLogRepository::CHANNEL_SMS, MessageLogRepository::CHANNEL_WECHAT_OFFICIAL, MessageLogRepository::CHANNEL_WECHAT_MINI, MessageLogRepository::CHANNEL_SITE]
        );
    }

    public function test_create_casts_variables_and_find_for_update_reads_row(): void
    {
        $repo = new MessageLogRepository();
        $row = $repo->create([
            'template_code' => $this->code,
            'channel'       => 'wechat_mini',
            'user_id'       => 12,
            'receiver'      => 'oAbCdE…',
            'variables'     => ['amount' => '9.90'],
        ]);
        $this->ids[] = (int) $row['id'];

        $locked = Db::transaction(static fn (): ?array => $repo->findForUpdate((int) $row['id']));
        $this->assertNotNull($locked);
        $this->assertSame(['amount' => '9.90'], $locked['variables']);
        $this->assertSame(0, $locked['status']);
        $this->assertSame(0, $locked['attempts']);
        $this->assertSame(12, $locked['user_id']);
        $this->assertNull(Db::transaction(static fn (): ?array => $repo->findForUpdate(999999999)));
    }

    public function test_increment_attempts(): void
    {
        $id = $this->log();
        $repo = new MessageLogRepository();

        $repo->incrementAttempts($id);
        $repo->incrementAttempts($id);

        $this->assertSame(2, (int) Db::table('message_logs')->where('id', $id)->value('attempts'));
        $this->assertSame(0, (int) Db::table('message_logs')->where('id', $id)->value('status'), '只加次数，不改状态');
    }

    public function test_finish_if_pending_writes_once_and_sets_sent_at_on_success(): void
    {
        $success = $this->log();
        $failed = $this->log();
        $repo = new MessageLogRepository();

        $this->assertTrue($repo->finishIfPending($success, MessageLogRepository::STATUS_SUCCESS, '{"code":"1"}', ''));
        $this->assertFalse($repo->finishIfPending($success, MessageLogRepository::STATUS_FAILED, 'late', 'late'), '已完成的行不再被覆盖');
        $row = Db::table('message_logs')->where('id', $success)->first();
        $this->assertSame(1, (int) $row->status);
        $this->assertSame('{"code":"1"}', $row->content);
        $this->assertNotNull($row->sent_at);

        $this->assertTrue($repo->finishIfPending($failed, MessageLogRepository::STATUS_FAILED, '', str_repeat('错', 300)));
        $row = Db::table('message_logs')->where('id', $failed)->first();
        $this->assertSame(2, (int) $row->status);
        $this->assertNull($row->sent_at, '失败不写 sent_at');
        $this->assertSame(255, mb_strlen($row->error_msg), 'error_msg 按字符截断到 255');
    }

    public function test_admin_list_filters(): void
    {
        $sms = $this->log(['channel' => 'sms', 'status' => 1, 'receiver' => '138****1234']);
        $mini = $this->log(['channel' => 'wechat_mini', 'status' => 2, 'receiver' => 'oAbCdE…']);
        $other = $this->log(['template_code' => $this->code . 'x', 'channel' => 'sms']);
        $repo = new MessageLogRepository();

        $all = $repo->getAdminList(['template_code' => $this->code], 1, 10);
        $this->assertSame([$mini, $sms], array_map('intval', array_column($all['list'], 'id')), 'template_code 精确匹配、id 倒序');

        $this->assertSame([$sms], array_map('intval', array_column($repo->getAdminList(['template_code' => $this->code, 'channel' => 'sms'], 1, 10)['list'], 'id')));
        $this->assertSame([$mini], array_map('intval', array_column($repo->getAdminList(['template_code' => $this->code, 'status' => '2'], 1, 10)['list'], 'id')));
        $this->assertSame([$sms], array_map('intval', array_column($repo->getAdminList(['template_code' => $this->code, 'receiver' => '1234'], 1, 10)['list'], 'id')));
        $this->assertSame(2, $repo->getAdminList(['template_code' => $this->code, 'status' => '', 'channel' => ''], 1, 10)['pagination']['total'], '空串不过滤');
        $this->assertSame(0, $repo->getAdminList(['template_code' => $this->code, 'receiver' => '%'], 1, 10)['pagination']['total'], '% 按字面匹配');
        unset($other);
    }
}
