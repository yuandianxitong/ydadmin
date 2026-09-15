<?php

declare(strict_types=1);

namespace app\command;

use app\service\system\FailedJobService;
use support\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** 命令只做参数解析与输出，逻辑在 FailedJobService::flush()。 */
#[AsCommand('queue:flush', '删除队列失败任务（默认全部）')]
final class QueueFlushCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('days', null, InputOption::VALUE_REQUIRED, '只删除早于 N 天的记录；不给则全部删除');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = $input->getOption('days');
        if ($days !== null && !ctype_digit((string) $days)) {
            $output->writeln('<error>--days 必须是非负整数</error>');

            return self::FAILURE;
        }

        $count = Container::get(FailedJobService::class)->flush($days === null ? null : (int) $days);
        $output->writeln("<info>已删除 {$count} 条失败任务。</info>");

        return self::SUCCESS;
    }
}
