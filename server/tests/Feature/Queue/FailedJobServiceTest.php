<?php

declare(strict_types=1);

namespace tests\Feature\Queue;

use app\service\system\FailedJobService;
use support\Container;
use support\Db;
use tests\fixtures\Queue\RecordingConsumer;
use tests\Support\ConfigOverride;
use tests\TestCase;

final class FailedJobServiceTest extends TestCase
{
    use ConfigOverride;

    protected function setUp(): void
    {
        parent::setUp();
        // 测试库专用：本类用例假定表里只有自己写入的行
        Db::table('failed_jobs')->delete();
        RecordingConsumer::$handled = [];
        $this->overrideConfig('queue.driver', 'sync');
    }

    protected function tearDown(): void
    {
        Db::table('failed_jobs')->delete();
        $this->restoreConfig();
        parent::tearDown();
    }

    private function service(): FailedJobService
    {
        return Container::get(FailedJobService::class);
    }

    public function test_record_masks_the_payload_and_truncates_the_exception(): void
    {
        $this->service()->record('fixture-record', ['name' => '甲', 'password' => 'Secret#1', 'nested' => ['token' => 't']], new \RuntimeException(str_repeat('x', 8000)), 4);

        $row = Db::table('failed_jobs')->first();
        $this->assertNotNull($row);
        $this->assertSame('fixture-record', $row->queue);
        $this->assertSame(4, (int) $row->attempts);
        $payload = json_decode((string) $row->payload, true);
        ksort($payload);
        $this->assertSame(['name' => '甲', 'nested' => ['token' => '***'], 'password' => '***'], $payload);
        $this->assertStringStartsWith('RuntimeException: xxx', (string) $row->exception);
        $this->assertSame(5000, mb_strlen((string) $row->exception));
        $this->assertNotNull($row->failed_at);
    }

    public function test_latest_returns_newest_first_and_honours_the_limit(): void
    {
        foreach (['fixture-a', 'fixture-b', 'fixture-c'] as $queue) {
            $this->service()->record($queue, ['q' => $queue], new \RuntimeException('boom'), 1);
        }

        $latest = $this->service()->latest(2);

        $this->assertSame(['fixture-c', 'fixture-b'], array_column($latest, 'queue'));
    }

    public function test_retry_redispatches_and_deletes_the_row(): void
    {
        $this->overrideConfig('queue.queues.fixture-record', ['consumer' => RecordingConsumer::class, 'max_attempts' => 0]);
        $this->service()->record('fixture-record', ['id' => 3], new \RuntimeException('boom'), 1);
        $id = (int) Db::table('failed_jobs')->value('id');

        $count = $this->service()->retry($id);

        $this->assertSame(1, $count);
        $this->assertSame([['id' => 3]], RecordingConsumer::$handled);
        $this->assertSame(0, Db::table('failed_jobs')->count());
    }

    public function test_retry_keeps_the_row_when_it_cannot_be_dispatched(): void
    {
        // 队列未登记 → dispatch() 抛 InvalidArgumentException → 记录保留，不计数
        $this->service()->record('fixture-not-registered', ['id' => 1], new \RuntimeException('boom'), 1);
        $id = (int) Db::table('failed_jobs')->value('id');

        $this->assertSame(0, $this->service()->retry($id));
        $this->assertSame(1, Db::table('failed_jobs')->where('id', $id)->count());
        $this->assertSame(0, $this->service()->retry(999999999), '不存在的 id 返回 0');
    }

    public function test_retry_all_processes_every_row(): void
    {
        $this->overrideConfig('queue.queues.fixture-record', ['consumer' => RecordingConsumer::class, 'max_attempts' => 0]);
        $this->service()->record('fixture-record', ['id' => 1], new \RuntimeException('boom'), 1);
        $this->service()->record('fixture-record', ['id' => 2], new \RuntimeException('boom'), 1);
        $this->service()->record('fixture-not-registered', ['id' => 3], new \RuntimeException('boom'), 1);

        $count = $this->service()->retry(null);

        $this->assertSame(2, $count);
        $this->assertSame([['id' => 1], ['id' => 2]], RecordingConsumer::$handled);
        $this->assertSame(['fixture-not-registered'], Db::table('failed_jobs')->pluck('queue')->all());
    }

    public function test_flush_by_days_only_deletes_older_rows_and_flush_all_deletes_everything(): void
    {
        $this->service()->record('fixture-old', ['id' => 1], new \RuntimeException('boom'), 1);
        $this->service()->record('fixture-new', ['id' => 2], new \RuntimeException('boom'), 1);
        Db::table('failed_jobs')->where('queue', 'fixture-old')->update(['failed_at' => date('Y-m-d H:i:s', time() - 10 * 86400)]);

        $this->assertSame(1, $this->service()->flush(5));
        $this->assertSame(['fixture-new'], Db::table('failed_jobs')->pluck('queue')->all());

        $this->assertSame(1, $this->service()->flush(null));
        $this->assertSame(0, Db::table('failed_jobs')->count());
    }
}
