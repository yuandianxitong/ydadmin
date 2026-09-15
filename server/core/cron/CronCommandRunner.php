<?php

declare(strict_types=1);

namespace core\cron;

use support\Container;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * 定时任务命令执行器（spec §5、决策 2）：只执行 config/cron.php 白名单里的 webman 控制台命令，进程内调用，不经 shell。
 *
 * - 白名单只看首词（trim 后按空白切分），参数原样交给 Symfony 的 StringInput 解析（支持引号）。
 * - run() 执行前再查一遍白名单：接口校验之外的第二道闸，库里被直接塞进白名单外的命令也跑不起来。
 * - 每次新建一个只装这一条命令的 Application（setAutoExit(false)、setCatchExceptions(false)），
 *   命令实例从容器取出后 clone 一份再 addCommand()——addCommand() 会改写实例的 application/helperSet，
 *   不让一次性的 Application 挂在容器单例上。Symfony 7.4 的 add() 已弃用，只能用 addCommand()。
 * - new Application() 会把 pcntl_async_signals 翻成 true（SignalRegistry 构造函数），queue 进程里这会改掉
 *   Workerman 自己的信号投递方式，所以在 finally 里恢复成调用前的值。
 * - 退出码 0 为成功；非 0 或抛出任何 Throwable 为失败，异常记为「类名: 消息」，抛出前写入的输出保留。
 *
 * 容器单例，无实例属性。
 */
class CronCommandRunner
{
    /** 输出与错误写进 cron_job_logs 之前的截断上限（字符数）。 */
    public const OUTPUT_LIMIT = 60000;

    public function allows(string $command): bool
    {
        return $this->resolve($command) !== null;
    }

    public function run(string $command): CronRunResult
    {
        $startedAt = date('Y-m-d H:i:s');
        $class = $this->resolve($command);
        if ($class === null) {
            return new CronRunResult(false, 1, '', lang('business.cron_command_not_allowed'), 0, $startedAt, $startedAt);
        }

        $output = new BufferedOutput();
        $asyncSignals = pcntl_async_signals();
        $start = hrtime(true);
        $exitCode = 1;
        $error = '';
        try {
            $instance = Container::get($class);
            if (!$instance instanceof Command) {
                throw new \LogicException("{$class} 不是控制台命令");
            }
            $application = new Application('cron');
            $application->setAutoExit(false);
            $application->setCatchExceptions(false);
            $application->addCommand(clone $instance);
            $exitCode = $application->run(new StringInput(trim($command)), $output);
        } catch (\Throwable $e) {
            $exitCode = 1;
            $error = $e::class . ': ' . $e->getMessage();
        } finally {
            pcntl_async_signals($asyncSignals);
        }
        $durationMs = intdiv(hrtime(true) - $start, 1_000_000);

        return new CronRunResult(
            $exitCode === 0 && $error === '',
            $exitCode,
            mb_substr($output->fetch(), 0, self::OUTPUT_LIMIT),
            mb_substr($error, 0, self::OUTPUT_LIMIT),
            $durationMs,
            $startedAt,
            date('Y-m-d H:i:s'),
        );
    }

    /** @return class-string<Command>|null 首词对应的白名单命令类；不在白名单或不是控制台命令时为 null */
    private function resolve(string $command): ?string
    {
        $parts = preg_split('/\s+/', trim($command), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $name = (string) ($parts[0] ?? '');
        if ($name === '') {
            return null;
        }
        $class = ((array) config('cron.commands', []))[$name] ?? null;

        return is_string($class) && is_a($class, Command::class, true) ? $class : null;
    }
}
