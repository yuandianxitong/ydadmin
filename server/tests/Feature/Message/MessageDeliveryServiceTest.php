<?php

declare(strict_types=1);

namespace tests\Feature\Message;

use app\service\message\MessageDeliveryService;
use app\service\message\MessageService;
use app\service\message\ReceiverMask;
use core\message\channel\WechatOfficialChannel;
use core\message\ChannelInterface;
use core\message\ChannelMessage;
use core\message\exception\MessageDefiniteFailure;
use core\message\exception\MessageTransientFailure;
use support\Container;
use support\Db;
use tests\Support\ApiTestCase;
use tests\Support\Message\FakeMessageChannels;
use tests\Support\Message\MessageFixtures;

/** M6b spec §4.4 / 设计决定 10：幂等、重读模板与接收人、渲染、失败分类、条件写回。 */
final class MessageDeliveryServiceTest extends ApiTestCase
{
    use FakeMessageChannels;
    use MessageFixtures;

    private const MAPPING = ['thing1' => '${nickname}'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeMessageChannels();
    }

    protected function tearDown(): void
    {
        try {
            $this->restoreMessageChannels();
        } finally {
            $this->cleanupMessageFixtures();
            parent::tearDown();
        }
    }

    private function delivery(): MessageDeliveryService
    {
        return Container::get(MessageDeliveryService::class);
    }

    private static function openid(): string
    {
        return 'o' . bin2hex(random_bytes(13));
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{id: int, code: string}
     */
    private function officialTemplate(array $overrides = []): array
    {
        return $this->insertMessageTemplate(array_merge([
            'wechat_official_enabled'     => 1,
            'wechat_official_template_id' => 'OA-TPL',
            'wechat_official_url'         => 'https://example.com/u/${nickname}',
            'wechat_official_data'        => self::MAPPING,
        ], $overrides));
    }

    // ---------------------------------------------------------------- 经队列（sync）走完整条链路

    public function test_sms_is_sent_with_real_mobile_and_params_in_variable_order(): void
    {
        $user = $this->messageUser();
        $template = $this->insertMessageTemplate([
            'sms_enabled'     => 1,
            'sms_template_id' => 'SMS_001',
            'variables'       => [['key' => 'code', 'name' => '单号', 'example' => '1'], ['key' => 'nickname', 'name' => '昵称', 'example' => '张三']],
        ]);

        Container::get(MessageService::class)->sendToUser($user->id, $template['code'], ['nickname' => '张三', 'code' => 'A1']);

        $sent = $this->sentMessages();
        $this->assertCount(1, $sent);
        $this->assertSame('sms', $sent[0]['channel']);
        $this->assertEquals(new ChannelMessage($user->mobile, 'SMS_001', ['code' => 'A1', 'nickname' => '张三']), $sent[0]['message']);
        $this->assertSame(['code', 'nickname'], array_keys($sent[0]['message']->data), '腾讯云驱动按参数顺序取值');

        $log = $this->messageLogsFor($user->id)[0];
        $this->assertSame(1, (int) $log['status']);
        $this->assertSame(1, (int) $log['attempts']);
        $this->assertNotNull($log['sent_at']);
        $this->assertSame('', $log['error_msg']);
        $this->assertEquals(['code' => 'A1', 'nickname' => '张三'], json_decode((string) $log['content'], true));
    }

    public function test_official_account_message_carries_rendered_data_and_url(): void
    {
        $oa = self::openid();
        $user = $this->messageUser(['oa_openid' => $oa]);
        $template = $this->officialTemplate();

        Container::get(MessageService::class)->sendToUser($user->id, $template['code'], ['nickname' => '张三']);

        $sent = $this->sentMessages();
        $this->assertCount(1, $sent);
        $this->assertSame('wechat_official', $sent[0]['channel']);
        $this->assertSame($oa, $sent[0]['message']->receiver);
        $this->assertSame('OA-TPL', $sent[0]['message']->templateId);
        $this->assertSame(['thing1' => ['value' => '张三']], $sent[0]['message']->data);
        $this->assertSame('https://example.com/u/张三', $sent[0]['message']->link);

        $log = $this->messageLogsFor($user->id)[0];
        $this->assertSame(1, (int) $log['status']);
        $this->assertEquals(['data' => ['thing1' => ['value' => '张三']], 'url' => 'https://example.com/u/张三'], json_decode((string) $log['content'], true));
        $this->assertStringNotContainsString($oa, (string) $log['content']);
    }

    public function test_mini_program_message_carries_rendered_page(): void
    {
        $mini = self::openid();
        $user = $this->messageUser(['mini_openid' => $mini]);
        $template = $this->insertMessageTemplate([
            'wechat_mini_enabled'     => 1,
            'wechat_mini_template_id' => 'MINI-TPL',
            'wechat_mini_page'        => 'pages/index?n=${nickname}',
            'wechat_mini_data'        => self::MAPPING,
        ]);

        Container::get(MessageService::class)->sendToUser($user->id, $template['code'], ['nickname' => '张三']);

        $sent = $this->sentMessages();
        $this->assertCount(1, $sent);
        $this->assertSame('wechat_mini', $sent[0]['channel']);
        $this->assertSame($mini, $sent[0]['message']->receiver);
        $this->assertSame('pages/index?n=张三', $sent[0]['message']->link);
        $this->assertEquals(['data' => ['thing1' => ['value' => '张三']], 'page' => 'pages/index?n=张三'], json_decode((string) $this->messageLogsFor($user->id)[0]['content'], true));
    }

    public function test_definite_failure_marks_failed_without_retry(): void
    {
        $user = $this->messageUser(['oa_openid' => self::openid()]);
        $template = $this->officialTemplate();
        $this->failNextSend('wechat_official', new MessageDefiniteFailure('wechat errcode 43004'));

        Container::get(MessageService::class)->sendToUser($user->id, $template['code'], ['nickname' => '张三']);

        $this->assertCount(1, $this->sentMessages());
        $log = $this->messageLogsFor($user->id)[0];
        $this->assertSame(2, (int) $log['status']);
        $this->assertSame('wechat errcode 43004', $log['error_msg']);
        $this->assertSame(1, (int) $log['attempts']);
        $this->assertNull($log['sent_at']);
        $this->assertNotNull($log['content'], '确定失败也写下渲染结果');
        $this->assertSame(0, Db::table('failed_jobs')->where('queue', 'message-send')->count(), '确定失败不交给 M3');
    }

    public function test_transient_failure_on_the_last_attempt_is_marked_exhausted(): void
    {
        // sync 驱动把这次当作最后一次失败（attempts = max_attempts）
        $user = $this->messageUser(['oa_openid' => self::openid()]);
        $template = $this->officialTemplate();
        $this->failNextSend('wechat_official', new MessageTransientFailure('wechat unavailable'));

        Container::get(MessageService::class)->sendToUser($user->id, $template['code'], ['nickname' => '张三']);

        $log = $this->messageLogsFor($user->id)[0];
        $this->assertSame(2, (int) $log['status']);
        $this->assertSame('重试耗尽：wechat unavailable', $log['error_msg']);
        $this->assertSame(1, (int) $log['attempts']);
        $this->assertEquals(['data' => ['thing1' => ['value' => '张三']], 'url' => 'https://example.com/u/张三'], json_decode((string) $log['content'], true));
        $this->assertSame(1, Db::table('failed_jobs')->where('queue', 'message-send')->count());
    }

    // ---------------------------------------------------------------- 直接驱动 deliver()

    public function test_transient_failure_keeps_the_log_pending_and_the_next_attempt_can_succeed(): void
    {
        $user = $this->messageUser(['oa_openid' => self::openid()]);
        $template = $this->officialTemplate();
        $logId = $this->insertPendingLog($template, 'wechat_official', $user->id, ['nickname' => '张三']);
        $this->failNextSend('wechat_official', new MessageTransientFailure('wechat errcode -1'));

        try {
            $this->delivery()->deliver($logId);
            $this->fail('暂时失败必须重抛，交给 M3 重试');
        } catch (MessageTransientFailure $e) {
            $this->assertSame('wechat errcode -1', $e->getMessage());
        }
        $this->assertSame(0, (int) $this->messageLog($logId)['status']);
        $this->assertSame(1, (int) $this->messageLog($logId)['attempts']);

        $this->delivery()->deliver($logId);

        $this->assertSame(1, (int) $this->messageLog($logId)['status']);
        $this->assertSame(2, (int) $this->messageLog($logId)['attempts']);
        $this->assertCount(2, $this->sentMessages());
    }

    public function test_unexpected_exception_is_retried_as_transient_with_class_name_only(): void
    {
        $user = $this->messageUser(['oa_openid' => self::openid()]);
        $template = $this->officialTemplate();
        $logId = $this->insertPendingLog($template, 'wechat_official', $user->id, ['nickname' => '张三']);
        $this->failNextSend('wechat_official', new \RuntimeException('update message_logs ... 13800138000'));

        try {
            $this->delivery()->deliver($logId);
            $this->fail('意外异常应当按暂时失败重抛');
        } catch (MessageTransientFailure $e) {
            $this->assertSame('unexpected RuntimeException', $e->getMessage());
        }
        $this->assertSame(0, (int) $this->messageLog($logId)['status']);
    }

    public function test_real_receiver_in_channel_failure_message_is_masked(): void
    {
        $oa = self::openid();
        $user = $this->messageUser(['oa_openid' => $oa]);
        $template = $this->officialTemplate();
        $definite = $this->insertPendingLog($template, 'wechat_official', $user->id, ['nickname' => '张三']);
        $transient = $this->insertPendingLog($template, 'wechat_official', $user->id, ['nickname' => '张三']);
        $masked = ReceiverMask::mask('wechat_official', $oa);

        $this->failNextSend('wechat_official', new MessageDefiniteFailure("wechat errcode 43004 invalid openid {$oa}"));
        $this->delivery()->deliver($definite);
        $this->assertSame("wechat errcode 43004 invalid openid {$masked}", $this->messageLog($definite)['error_msg']);

        $this->failNextSend('wechat_official', new MessageTransientFailure("wechat errcode -1 {$oa}"));
        try {
            $this->delivery()->deliver($transient);
            $this->fail('暂时失败必须重抛');
        } catch (MessageTransientFailure $e) {
            $this->assertSame("wechat errcode -1 {$masked}", $e->getMessage());
            $this->assertNull($e->getPrevious(), '原消息不得经异常链带出');
        }
    }

    public function test_finished_or_missing_log_is_not_sent_again(): void
    {
        $user = $this->messageUser(['oa_openid' => self::openid()]);
        $template = $this->officialTemplate();
        $done = $this->insertPendingLog($template, 'wechat_official', $user->id, ['nickname' => '张三'], ['status' => 1, 'attempts' => 1]);

        $this->delivery()->deliver($done);
        $this->delivery()->deliver(PHP_INT_MAX);

        $this->assertSame([], $this->sentMessages());
        $this->assertSame(1, (int) $this->messageLog($done)['attempts']);
    }

    public function test_disabled_deleted_or_switched_off_template_fails_without_sending(): void
    {
        $user = $this->messageUser(['oa_openid' => self::openid()]);
        $cases = [
            'status=0'   => $this->officialTemplate(['status' => 0]),
            '软删'        => $this->officialTemplate(['deleted_at' => date('Y-m-d H:i:s')]),
            '通道关闭'    => $this->officialTemplate(['wechat_official_enabled' => 0]),
            '模板 id 清空' => $this->officialTemplate(['wechat_official_template_id' => '']),
            '映射清空'    => $this->officialTemplate(['wechat_official_data' => []]),
        ];
        foreach ($cases as $case => $template) {
            $logId = $this->insertPendingLog($template, 'wechat_official', $user->id, ['nickname' => '张三']);

            $this->delivery()->deliver($logId);

            $log = $this->messageLog($logId);
            $this->assertSame(2, (int) $log['status'], $case);
            $this->assertSame(MessageDeliveryService::ERROR_TEMPLATE_UNAVAILABLE, $log['error_msg'], $case);
        }
        $this->assertSame('模板已停用或通道已关闭', MessageDeliveryService::ERROR_TEMPLATE_UNAVAILABLE);
        $this->assertSame([], $this->sentMessages());
    }

    public function test_receiver_is_reread_and_missing_receiver_fails(): void
    {
        $user = $this->messageUser(['oa_openid' => self::openid()]);
        $template = $this->officialTemplate();
        $logId = $this->insertPendingLog($template, 'wechat_official', $user->id, ['nickname' => '张三']);
        Db::table('users')->where('id', $user->id)->update(['oa_openid' => null]);

        $this->delivery()->deliver($logId);

        $this->assertSame('接收人不存在', MessageDeliveryService::ERROR_RECEIVER_MISSING);
        $this->assertSame(MessageDeliveryService::ERROR_RECEIVER_MISSING, $this->messageLog($logId)['error_msg']);
        $this->assertSame(2, (int) $this->messageLog($logId)['status']);

        $rebound = self::openid();
        Db::table('users')->where('id', $user->id)->update(['oa_openid' => $rebound]);
        $second = $this->insertPendingLog($template, 'wechat_official', $user->id, ['nickname' => '张三']);
        $this->delivery()->deliver($second);
        $this->assertSame($rebound, $this->sentMessages()[0]['message']->receiver, '发送时按 user_id 重读，不用下单时的值');
    }

    public function test_missing_variable_is_a_definite_failure(): void
    {
        $user = $this->messageUser(['oa_openid' => self::openid()]);
        $template = $this->officialTemplate();
        $logId = $this->insertPendingLog($template, 'wechat_official', $user->id, []);

        $this->delivery()->deliver($logId);

        $this->assertSame(2, (int) $this->messageLog($logId)['status']);
        $this->assertSame('missing variable nickname', $this->messageLog($logId)['error_msg']);
        $this->assertSame([], $this->sentMessages());
    }

    public function test_result_is_not_written_over_a_log_finished_by_another_worker(): void
    {
        $user = $this->messageUser(['oa_openid' => self::openid()]);
        $template = $this->officialTemplate();
        $logId = $this->insertPendingLog($template, 'wechat_official', $user->id, ['nickname' => '张三']);
        // 模拟并发重复投递：本次调用通道期间，另一个 worker 已把这一行写成成功
        Container::set(WechatOfficialChannel::class, new class ($logId) implements ChannelInterface {
            public function __construct(private readonly int $logId)
            {
            }

            public function send(ChannelMessage $message): void
            {
                Db::table('message_logs')->where('id', $this->logId)->update(['status' => 1, 'content' => 'other-worker', 'sent_at' => date('Y-m-d H:i:s')]);

                throw new MessageDefiniteFailure('wechat errcode 43101');
            }
        });

        $this->delivery()->deliver($logId);

        $log = $this->messageLog($logId);
        $this->assertSame(1, (int) $log['status']);
        $this->assertSame('other-worker', $log['content']);
        $this->assertSame('', $log['error_msg']);
    }

    public function test_email_is_rendered_and_sent_to_the_member_address(): void
    {
        $email = 'member' . bin2hex(random_bytes(3)) . '@example.com';
        $user = $this->messageUser(['email' => $email]);
        $template = $this->insertMessageTemplate([
            'email_enabled' => 1,
            'email_subject' => '欢迎 ${nickname}',
            'email_content' => '你好 ${nickname}',
        ]);

        Container::get(MessageService::class)->sendToUser($user->id, $template['code'], ['nickname' => '张三']);

        $sent = $this->sentMessages();
        $this->assertCount(1, $sent);
        $this->assertSame('email', $sent[0]['channel']);
        $this->assertSame($email, $sent[0]['message']->receiver);
        $this->assertSame('欢迎 张三', $sent[0]['message']->templateId);
        $this->assertSame('你好 张三', $sent[0]['message']->link);

        $logs = $this->messageLogsFor($user->id);
        $this->assertCount(1, $logs);
        $this->assertSame(1, (int) $logs[0]['status']);
        $this->assertSame('m***@example.com', $logs[0]['receiver']);
    }

    public function test_email_is_skipped_when_the_member_has_no_address(): void
    {
        $user = $this->messageUser(['email' => null]);
        $template = $this->insertMessageTemplate([
            'email_enabled' => 1,
            'email_subject' => '欢迎 ${nickname}',
            'email_content' => '你好 ${nickname}',
        ]);

        Container::get(MessageService::class)->sendToUser($user->id, $template['code'], ['nickname' => '张三']);

        $this->assertSame([], $this->sentMessages());
        $this->assertSame([], $this->messageLogsFor($user->id));
    }
}
