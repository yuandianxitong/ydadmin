<?php

declare(strict_types=1);

namespace tests\Feature\Command;

use app\command\QueueFailedCommand;
use app\command\QueueFlushCommand;
use app\command\QueueRetryCommand;
use app\service\system\FailedJobService;
use support\Container;
use support\Db;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use tests\fixtures\Queue\RecordingConsumer;
use tests\Support\ConfigOverride;
use tests\TestCase;

final class QueueCommandsTest extends TestCase
{
    use ConfigOverride;

    protected function setUp(): void
    {
        parent::setUp();
        Db::table('failed_jobs')->delete();
        RecordingConsumer::$handled = [];
        $this->overrideConfig('queue.driver', 'sync');
        $this->overrideConfig('queue.queues.fixture-record', ['consumer' => RecordingConsumer::class, 'max_attempts' => 0]);
    }

    protected function tearDown(): void
    {
        Db::table('failed_jobs')->delete();
        $this->restoreConfig();
        parent::tearDown();
    }

    private function recordFailure(string $queue, array $data): int
    {
        Container::get(FailedJobService::class)->record($queue, $data, new \RuntimeException("boom in {$queue}"), 1);

        return (int) Db::table('failed_jobs')->where('queue', $queue)->orderByDesc('id')->value('id');
    }

    /**
     * 不能叫 run()：PHPUnit\Framework\TestCase 自己有 run()，同名会致命错误。
     *
     * @param array<string, mixed> $input
     */
    private function runCommand(Command $command, array $input = []): CommandTester
    {
        $tester = new CommandTester($command);
        $tester->execute($input);

        return $tester;
    }

    public function test_failed_lists_rows_and_says_so_when_empty(): void
    {
        $tester = $this->runCommand(new QueueFailedCommand());
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('没有失败任务', $tester->getDisplay());

        $this->recordFailure('fixture-record', ['id' => 1]);
        $tester = $this->runCommand(new QueueFailedCommand(), ['--limit' => '5']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('fixture-record', $tester->getDisplay());
        $this->assertStringContainsString('RuntimeException: boom in fixture-record', $tester->getDisplay());
        $this->assertStringNotContainsString('#0 ', $tester->getDisplay(), '表格只显示异常首行，不显示调用栈');
    }

    public function test_failed_rejects_a_non_positive_limit(): void
    {
        $this->assertSame(Command::FAILURE, $this->runCommand(new QueueFailedCommand(), ['--limit' => '0'])->getStatusCode());
    }

    /**
     * 控制器裁决：queue:retry 只有一个位置参数 {id|all}，没有 --all 选项
     * （spec §8.5 `queue:retry {id|all}`；README 用例是 `queue:retry all`）。
     */
    public function test_retry_requires_a_valid_id_or_the_literal_all(): void
    {
        $this->assertSame(Command::FAILURE, $this->runCommand(new QueueRetryCommand())->getStatusCode(), '缺少参数');
        $this->assertSame(Command::FAILURE, $this->runCommand(new QueueRetryCommand(), ['id' => 'abc'])->getStatusCode(), '既不是数字也不是 all');
        $this->assertSame(Command::FAILURE, $this->runCommand(new QueueRetryCommand(), ['id' => '0'])->getStatusCode(), '非正整数');
    }

    public function test_retry_by_id_redispatches_and_deletes_the_row(): void
    {
        $id = $this->recordFailure('fixture-record', ['id' => 4]);

        $tester = $this->runCommand(new QueueRetryCommand(), ['id' => (string) $id]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString('已重新投递 1 条', $tester->getDisplay());
        $this->assertSame([['id' => 4]], RecordingConsumer::$handled);
        $this->assertSame(0, Db::table('failed_jobs')->count());
    }

    public function test_retry_of_a_missing_id_fails(): void
    {
        $tester = $this->runCommand(new QueueRetryCommand(), ['id' => '999999999']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('999999999', $tester->getDisplay());
    }

    public function test_retry_all(): void
    {
        $this->recordFailure('fixture-record', ['id' => 1]);
        $this->recordFailure('fixture-record', ['id' => 2]);

        $tester = $this->runCommand(new QueueRetryCommand(), ['id' => 'all']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('已重新投递 2 条', $tester->getDisplay());
        $this->assertSame(0, Db::table('failed_jobs')->count());
    }

    public function test_flush_by_days_and_all(): void
    {
        $oldId = $this->recordFailure('fixture-old', ['id' => 1]);
        $this->recordFailure('fixture-new', ['id' => 2]);
        Db::table('failed_jobs')->where('id', $oldId)->update(['failed_at' => date('Y-m-d H:i:s', time() - 10 * 86400)]);

        $this->assertSame(Command::FAILURE, $this->runCommand(new QueueFlushCommand(), ['--days' => 'abc'])->getStatusCode());

        $tester = $this->runCommand(new QueueFlushCommand(), ['--days' => '5']);
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('已删除 1 条', $tester->getDisplay());
        $this->assertSame(['fixture-new'], Db::table('failed_jobs')->pluck('queue')->all());

        $tester = $this->runCommand(new QueueFlushCommand());
        $this->assertStringContainsString('已删除 1 条', $tester->getDisplay());
        $this->assertSame(0, Db::table('failed_jobs')->count());
    }
}
