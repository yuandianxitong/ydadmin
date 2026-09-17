<?php

declare(strict_types=1);

namespace tests\Feature\Message;

use app\repository\message\MessageTemplateRepository;
use app\service\message\MessageService;
use Monolog\Handler\TestHandler;
use support\Container;
use support\Log;
use tests\fixtures\Queue\RecordingConsumer;
use tests\Support\ApiTestCase;
use tests\Support\ConfigOverride;
use tests\Support\Message\MessageFixtures;

/**
 * M6b spec §4.3：站内信同步写、外发通道写待发日志并投递、receiver 遮蔽、异常隔离。
 * 投递的消费者换成记录夹具：MessageSendConsumer 在 Task 6 才实现，这里只断言投递了什么。
 */
final class MessageServiceTest extends ApiTestCase
{
    use ConfigOverride;
    use MessageFixtures;

    private TestHandler $logs;

    protected function setUp(): void
    {
        parent::setUp();
        RecordingConsumer::reset();
        $this->overrideConfig('queue.queues.message-send', ['consumer' => RecordingConsumer::class, 'max_attempts' => 3]);
        $this->logs = new TestHandler();
        Log::channel()->pushHandler($this->logs);
    }

    protected function tearDown(): void
    {
        try {
            Log::channel()->popHandler();
            $this->restoreConfig();
            RecordingConsumer::reset();
        } finally {
            $this->cleanupMessageFixtures();
            parent::tearDown();
        }
    }

    private function service(): MessageService
    {
        return Container::get(MessageService::class);
    }

    private static function openid(): string
    {
        return 'o' . bin2hex(random_bytes(13));
    }

    public function test_message_send_queue_is_registered_with_three_attempts(): void
    {
        $this->restoreConfig();

        $this->assertSame(['consumer' => 'app\queue\redis_slow\MessageSendConsumer', 'max_attempts' => 3], config('queue.queues.message-send'));
    }

    // ---------------------------------------------------------------- site

    public function test_site_channel_writes_notification_and_success_log(): void
    {
        $user = $this->messageUser();
        $template = $this->insertMessageTemplate(['site_enabled' => 1, 'site_title' => 'Hi ${nickname}', 'site_content' => '欢迎 ${nickname} 加入']);

        $this->service()->sendToUser($user->id, $template['code'], ['nickname' => '张三'], 'biz-1');

        $notifications = $this->notificationsFor($user->id);
        $this->assertCount(1, $notifications);
        $this->assertSame('Hi 张三', $notifications[0]['title']);
        $this->assertSame('欢迎 张三 加入', $notifications[0]['content']);
        $this->assertSame('system', $notifications[0]['type']);
        $this->assertSame('biz-1', $notifications[0]['biz_id']);
        $this->assertEquals(['template_code' => $template['code']], json_decode((string) $notifications[0]['extra'], true));

        $logs = $this->messageLogsFor($user->id);
        $this->assertCount(1, $logs);
        $this->assertSame('site', $logs[0]['channel']);
        $this->assertSame(1, (int) $logs[0]['status']);
        $this->assertSame("user#{$user->id}", $logs[0]['receiver']);
        $this->assertSame($template['id'], (int) $logs[0]['template_id']);
        $this->assertSame($template['code'], $logs[0]['template_code']);
        $this->assertEquals(['title' => 'Hi 张三', 'content' => '欢迎 张三 加入'], json_decode((string) $logs[0]['content'], true));
        $this->assertEquals(['nickname' => '张三'], json_decode((string) $logs[0]['variables'], true));
        $this->assertSame('', $logs[0]['error_msg']);
        $this->assertNotNull($logs[0]['sent_at']);
        $this->assertSame([], RecordingConsumer::$handled, '站内信不经队列');
    }

    public function test_builtin_templates_render_and_payment_success_maps_to_payment_type(): void
    {
        $user = $this->messageUser();

        $this->service()->sendToUser($user->id, 'user_register', ['nickname' => '用户5678']);
        $this->service()->sendToUser($user->id, 'payment_success', ['order_no' => 'R001', 'amount' => '50.50', 'paid_at' => '2026-09-17 12:00:00'], 'R001');

        $notifications = $this->notificationsFor($user->id);
        $this->assertSame(['注册成功', '充值成功'], array_column($notifications, 'title'));
        $this->assertSame(['欢迎加入，用户5678', '订单 R001 已到账 50.50 元'], array_column($notifications, 'content'));
        $this->assertSame(['system', 'payment'], array_column($notifications, 'type'));
        $this->assertSame(['', 'R001'], array_column($notifications, 'biz_id'));
        $this->assertSame(MessageService::SITE_TYPES, ['payment_success' => 'payment']);
    }

    public function test_site_title_and_content_are_cut_to_column_length(): void
    {
        $user = $this->messageUser();
        $template = $this->insertMessageTemplate(['site_enabled' => 1, 'site_title' => '${nickname}', 'site_content' => '${nickname}${nickname}']);

        $this->service()->sendToUser($user->id, $template['code'], ['nickname' => str_repeat('长', 300)]);

        $notification = $this->notificationsFor($user->id)[0];
        $this->assertSame(100, mb_strlen((string) $notification['title']));
        $this->assertSame(500, mb_strlen((string) $notification['content']));
    }

    public function test_site_missing_variable_writes_failed_log_without_notification(): void
    {
        $user = $this->messageUser();
        $template = $this->insertMessageTemplate(['site_enabled' => 1, 'site_title' => 'Hi ${nickname}', 'site_content' => 'x']);

        $this->service()->sendToUser($user->id, $template['code'], []);

        $this->assertSame([], $this->notificationsFor($user->id));
        $logs = $this->messageLogsFor($user->id);
        $this->assertCount(1, $logs);
        $this->assertSame('site', $logs[0]['channel']);
        $this->assertSame(2, (int) $logs[0]['status']);
        $this->assertSame('missing variable nickname', $logs[0]['error_msg']);
        $this->assertNull($logs[0]['sent_at']);
    }

    // ---------------------------------------------------------------- 外发通道

    public function test_external_channels_write_pending_logs_with_masked_receivers_and_dispatch_in_order(): void
    {
        $oa = self::openid();
        $mini = self::openid();
        $user = $this->messageUser(['oa_openid' => $oa, 'mini_openid' => $mini]);
        $mapping = ['thing1' => '${nickname}'];
        $template = $this->insertMessageTemplate([
            'sms_enabled' => 1, 'sms_template_id' => 'SMS_001',
            'wechat_official_enabled' => 1, 'wechat_official_template_id' => 'OA-TPL', 'wechat_official_data' => $mapping,
            'wechat_mini_enabled' => 1, 'wechat_mini_template_id' => 'MINI-TPL', 'wechat_mini_data' => $mapping,
        ]);

        $this->service()->sendToUser($user->id, $template['code'], ['nickname' => '张三']);

        $logs = $this->messageLogsFor($user->id);
        $this->assertSame(['sms', 'wechat_official', 'wechat_mini'], array_column($logs, 'channel'));
        $this->assertSame([0, 0, 0], array_map('intval', array_column($logs, 'status')));
        $this->assertSame([
            substr($user->mobile, 0, 3) . '****' . substr($user->mobile, -4),
            substr($oa, 0, 6) . '…',
            substr($mini, 0, 6) . '…',
        ], array_column($logs, 'receiver'));
        foreach ($logs as $log) {
            $this->assertEquals(['nickname' => '张三'], json_decode((string) $log['variables'], true));
            $encoded = json_encode($log, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            foreach ([$user->mobile, $oa, $mini] as $secret) {
                $this->assertStringNotContainsString($secret, $encoded, '日志行不得出现完整手机号或 openid');
            }
        }
        $this->assertSame(array_map(static fn (array $log): array => ['log_id' => (int) $log['id']], $logs), RecordingConsumer::$handled);
    }

    public function test_channel_is_skipped_without_template_id_receiver_or_switch(): void
    {
        $user = $this->messageUser(['oa_openid' => null, 'mini_openid' => self::openid()]);
        $template = $this->insertMessageTemplate([
            'sms_enabled' => 1, 'sms_template_id' => '',
            'wechat_official_enabled' => 1, 'wechat_official_template_id' => 'OA-TPL', 'wechat_official_data' => ['thing1' => '${nickname}'],
            'wechat_mini_enabled' => 0, 'wechat_mini_template_id' => 'MINI-TPL', 'wechat_mini_data' => ['thing1' => '${nickname}'],
        ]);

        $this->service()->sendToUser($user->id, $template['code'], ['nickname' => '张三']);

        $this->assertSame([], $this->messageLogsFor($user->id));
        $this->assertSame([], RecordingConsumer::$handled);
    }

    public function test_enabled_wechat_channel_without_mapping_logs_failure_without_dispatch(): void
    {
        $oa = self::openid();
        $user = $this->messageUser(['oa_openid' => $oa]);
        $template = $this->insertMessageTemplate(['wechat_official_enabled' => 1, 'wechat_official_template_id' => 'OA-TPL', 'wechat_official_data' => []]);

        $this->service()->sendToUser($user->id, $template['code'], ['nickname' => '张三']);

        $logs = $this->messageLogsFor($user->id);
        $this->assertCount(1, $logs);
        $this->assertSame('wechat_official', $logs[0]['channel']);
        $this->assertSame(2, (int) $logs[0]['status']);
        $this->assertSame(MessageService::ERROR_MISSING_MAPPING, $logs[0]['error_msg']);
        $this->assertSame('未配置字段映射', MessageService::ERROR_MISSING_MAPPING);
        $this->assertSame(substr($oa, 0, 6) . '…', $logs[0]['receiver']);
        $this->assertSame([], RecordingConsumer::$handled);
    }

    public function test_dispatch_failure_marks_only_that_log_failed(): void
    {
        $user = $this->messageUser();
        $template = $this->insertMessageTemplate(['site_enabled' => 1, 'site_title' => 't', 'site_content' => 'c', 'sms_enabled' => 1, 'sms_template_id' => 'SMS_001']);
        // 队列未登记：QueueDispatcher 抛 \InvalidArgumentException，与 redis 驱动投递失败走同一条兜底
        $this->overrideConfig('queue.queues.message-send', null);

        $this->service()->sendToUser($user->id, $template['code'], ['nickname' => '张三']);

        $logs = $this->messageLogsFor($user->id);
        $this->assertSame(['site', 'sms'], array_column($logs, 'channel'));
        $this->assertSame(1, (int) $logs[0]['status'], '站内信不受影响');
        $this->assertSame(2, (int) $logs[1]['status']);
        $this->assertSame('dispatch failed: InvalidArgumentException', $logs[1]['error_msg']);
        $this->assertCount(1, $this->notificationsFor($user->id));
    }

    // ---------------------------------------------------------------- 跳过与异常隔离

    public function test_missing_or_disabled_template_logs_warning_with_code_only(): void
    {
        $user = $this->messageUser();
        $disabled = $this->insertMessageTemplate(['status' => 0, 'site_enabled' => 1, 'site_title' => 't', 'site_content' => 'c']);

        $this->service()->sendToUser($user->id, 'no_such_code', ['nickname' => '张三']);
        $this->service()->sendToUser($user->id, $disabled['code'], ['nickname' => '张三']);

        $this->assertSame([], $this->messageLogsFor($user->id));
        $warnings = array_values(array_filter($this->logs->getRecords(), static fn (array $r): bool => $r['level_name'] === 'WARNING'));
        $this->assertCount(2, $warnings);
        $this->assertSame([['code' => 'no_such_code'], ['code' => $disabled['code']]], array_column($warnings, 'context'));
    }

    public function test_missing_user_writes_nothing(): void
    {
        $template = $this->insertMessageTemplate(['site_enabled' => 1, 'site_title' => 't', 'site_content' => 'c']);

        $this->service()->sendToUser(PHP_INT_MAX, $template['code'], ['nickname' => '张三']);

        $this->assertSame(0, \support\Db::table('message_logs')->where('template_code', $template['code'])->count());
    }

    public function test_unexpected_exception_is_swallowed_and_logged_with_code_and_class_only(): void
    {
        $original = Container::get(MessageTemplateRepository::class);
        Container::set(MessageTemplateRepository::class, new class () extends MessageTemplateRepository {
            public function findActiveByCode(string $code): ?array
            {
                throw new \RuntimeException('select * from users where mobile = 13800138000');
            }
        });
        Container::set(MessageService::class, Container::make(MessageService::class));
        try {
            $this->service()->sendToUser(1, 'user_register', ['nickname' => '张三']);
        } finally {
            Container::set(MessageTemplateRepository::class, $original);
            Container::set(MessageService::class, Container::make(MessageService::class));
        }

        $this->assertTrue($this->logs->hasErrorRecords());
        foreach ($this->logs->getRecords() as $record) {
            $this->assertStringNotContainsString('13800138000', json_encode([$record['message'], $record['context']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        }
        $errors = array_values(array_filter($this->logs->getRecords(), static fn (array $r): bool => $r['level_name'] === 'ERROR'));
        $this->assertSame(['code' => 'user_register', 'exception' => \RuntimeException::class], $errors[0]['context']);
    }
}
