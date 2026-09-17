<?php

declare(strict_types=1);

namespace tests\Feature\Message;

use app\queue\redis_slow\MessageSendConsumer;
use core\message\exception\MessageTransientFailure;
use support\Container;
use support\Db;
use tests\Support\ApiTestCase;
use tests\Support\Message\FakeMessageChannels;
use tests\Support\Message\MessageFixtures;

/** M6b 设计决定 9：handle 委托 deliver；onConsumeFailure 在最后一次失败时置「重试耗尽」，且永不抛出。 */
final class MessageSendConsumerTest extends ApiTestCase
{
    use FakeMessageChannels;
    use MessageFixtures;

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

    private function consumer(): MessageSendConsumer
    {
        return Container::get(MessageSendConsumer::class);
    }

    private function pendingOfficialLog(): int
    {
        $user = $this->messageUser(['oa_openid' => 'o' . bin2hex(random_bytes(13))]);
        $template = $this->insertMessageTemplate([
            'wechat_official_enabled'     => 1,
            'wechat_official_template_id' => 'OA-TPL',
            'wechat_official_data'        => ['thing1' => '${nickname}'],
        ]);

        return $this->insertPendingLog($template, 'wechat_official', $user->id, ['nickname' => '张三']);
    }

    public function test_handle_delivers_the_log(): void
    {
        $logId = $this->pendingOfficialLog();

        $this->consumer()->handle(['log_id' => $logId]);

        $this->assertCount(1, $this->sentMessages());
        $this->assertSame(1, (int) $this->messageLog($logId)['status']);
    }

    public function test_failure_before_the_last_attempt_leaves_the_log_pending(): void
    {
        $logId = $this->pendingOfficialLog();

        $package = $this->consumer()->onConsumeFailure(
            new MessageTransientFailure('wechat unavailable'),
            ['queue' => 'message-send', 'data' => ['log_id' => $logId], 'attempts' => 2]
        );

        $this->assertSame(3, $package['max_attempts']);
        $this->assertSame(0, (int) $this->messageLog($logId)['status']);
        $this->assertSame(0, Db::table('failed_jobs')->where('queue', 'message-send')->count());
    }

    public function test_last_failure_marks_the_log_exhausted_and_records_failed_job(): void
    {
        $logId = $this->pendingOfficialLog();

        $this->consumer()->onConsumeFailure(
            new MessageTransientFailure('wechat errcode 45009'),
            ['queue' => 'message-send', 'data' => ['log_id' => $logId], 'attempts' => 3]
        );

        $log = $this->messageLog($logId);
        $this->assertSame(2, (int) $log['status']);
        $this->assertSame('重试耗尽：wechat errcode 45009', $log['error_msg']);
        $this->assertSame(1, Db::table('failed_jobs')->where('queue', 'message-send')->count());
    }

    public function test_exhaustion_never_overwrites_a_finished_log_and_bad_packages_do_not_throw(): void
    {
        $logId = $this->pendingOfficialLog();
        Db::table('message_logs')->where('id', $logId)->update(['status' => 1]);

        $this->consumer()->onConsumeFailure(new MessageTransientFailure('x'), ['queue' => 'message-send', 'data' => ['log_id' => $logId], 'attempts' => 3]);
        $package = $this->consumer()->onConsumeFailure(new \RuntimeException('x'), ['queue' => 'message-send', 'data' => 'not-an-array', 'attempts' => 9]);
        $this->consumer()->onConsumeFailure(new \RuntimeException('x'), ['queue' => 'message-send', 'data' => ['log_id' => PHP_INT_MAX], 'attempts' => 9]);

        $this->assertSame(1, (int) $this->messageLog($logId)['status']);
        $this->assertSame('', $this->messageLog($logId)['error_msg']);
        $this->assertSame('not-an-array', $package['data']);
    }
}
