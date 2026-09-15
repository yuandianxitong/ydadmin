<?php

declare(strict_types=1);

namespace tests\Unit\Cron;

use core\cron\CronCommandRunner;
use tests\fixtures\Cron\FixtureCommand;
use tests\Support\ConfigOverride;
use tests\TestCase;

final class CronCommandRunnerTest extends TestCase
{
    use ConfigOverride;

    private CronCommandRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->overrideConfig('cron.commands', ['fixture:cron' => FixtureCommand::class]);
        $this->runner = new CronCommandRunner();
    }

    protected function tearDown(): void
    {
        $this->restoreConfig();
        parent::tearDown();
    }

    public function test_allows_only_checks_the_first_word_against_the_whitelist(): void
    {
        $this->assertTrue($this->runner->allows('fixture:cron'));
        $this->assertTrue($this->runner->allows('  fixture:cron "hello world" --fail  '));
        $this->assertFalse($this->runner->allows('fixture:cronx'));
        $this->assertFalse($this->runner->allows('db:reset --force'));
        $this->assertFalse($this->runner->allows('fixture'));
        $this->assertFalse($this->runner->allows(''));
        $this->assertFalse($this->runner->allows('   '));
    }

    public function test_the_shipped_whitelist_contains_only_log_archive(): void
    {
        $this->restoreConfig();

        $this->assertSame(['log:archive'], array_keys((array) config('cron.commands')));
        $this->assertTrue((new CronCommandRunner())->allows('log:archive --days=90'));
        $this->assertFalse((new CronCommandRunner())->allows('db:reset'), 'db:reset 永远不能进白名单');
    }

    public function test_a_whitelisted_entry_that_is_not_a_console_command_is_not_allowed(): void
    {
        $this->overrideConfig('cron.commands', ['fixture:cron' => \stdClass::class]);

        $this->assertFalse($this->runner->allows('fixture:cron'));
    }

    public function test_successful_run_captures_output_and_passes_arguments_through(): void
    {
        $result = $this->runner->run('fixture:cron "hello world"');

        $this->assertTrue($result->success);
        $this->assertSame(0, $result->exitCode);
        $this->assertSame("message=hello world\n", $result->output);
        $this->assertSame('', $result->error);
        $this->assertGreaterThanOrEqual(0, $result->durationMs);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result->startedAt);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $result->finishedAt);
        $this->assertLessThanOrEqual($result->finishedAt, $result->startedAt);
    }

    public function test_non_zero_exit_code_is_a_failure_with_output_kept(): void
    {
        $result = $this->runner->run('fixture:cron x --fail');

        $this->assertFalse($result->success);
        $this->assertSame(1, $result->exitCode);
        $this->assertStringContainsString('message=x', $result->output);
        $this->assertStringContainsString('夹具命令按要求失败', $result->output);
        $this->assertSame('', $result->error);
    }

    public function test_an_exception_is_captured_as_a_failure_with_partial_output(): void
    {
        $result = $this->runner->run('fixture:cron --throw');

        $this->assertFalse($result->success);
        $this->assertSame(1, $result->exitCode);
        $this->assertSame("message=hi\n", $result->output, '抛出前写入的输出要保留');
        $this->assertSame('RuntimeException: 夹具命令按要求抛出异常', $result->error);
    }

    public function test_a_command_outside_the_whitelist_is_refused_without_running(): void
    {
        // 用一个根本不存在的命令名：白名单判定若有 bug，也绝不能让测试真的去跑 db:reset 这类破坏性命令
        $result = $this->runner->run('not:whitelisted --force');

        $this->assertFalse($result->success);
        $this->assertSame(1, $result->exitCode);
        $this->assertSame('', $result->output);
        $this->assertSame(lang('business.cron_command_not_allowed'), $result->error);
        $this->assertSame(0, $result->durationMs);
        $this->assertSame($result->startedAt, $result->finishedAt);
    }

    public function test_run_restores_the_async_signal_flag(): void
    {
        // new Application() 会把 pcntl_async_signals 翻成 true；queue 进程里这会改掉 Workerman 的信号投递方式
        $before = pcntl_async_signals(false);
        try {
            $this->runner->run('fixture:cron');
            $this->assertFalse(pcntl_async_signals(), 'run() 之后必须恢复成调用前的值');
            $this->runner->run('fixture:cron --throw');
            $this->assertFalse(pcntl_async_signals(), '命令抛异常时同样要恢复');
        } finally {
            pcntl_async_signals($before);
        }
    }

    public function test_repeated_runs_reuse_nothing_from_the_previous_run(): void
    {
        $this->runner->run('fixture:cron --throw');
        $this->runner->run('fixture:cron x --fail');
        $result = $this->runner->run('fixture:cron again');

        $this->assertTrue($result->success);
        $this->assertSame("message=again\n", $result->output);
    }

    public function test_output_is_truncated_to_the_limit(): void
    {
        // 参数必须加引号：Symfony StringInput 对一个几万字符、不含引号的裸参数做 tokenize 是 O(n^2)
        // 的回溯（实测 8000 字符已要 20s+），加双引号后走 REGEX_QUOTED_STRING 分支是线性的。
        $result = $this->runner->run('fixture:cron "' . str_repeat('长', CronCommandRunner::OUTPUT_LIMIT + 100) . '"');

        $this->assertSame(CronCommandRunner::OUTPUT_LIMIT, mb_strlen($result->output));
    }
}
