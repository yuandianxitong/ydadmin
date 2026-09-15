<?php

declare(strict_types=1);

namespace app\command;

use app\service\system\FailedJobService;
use support\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** 命令只做参数解析与输出，逻辑在 FailedJobService。 */
#[AsCommand('queue:failed', '列出队列失败任务（新的在前）')]
final class QueueFailedCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, '最多列出多少条', '20');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (string) $input->getOption('limit');
        if (!ctype_digit($limit) || (int) $limit < 1) {
            $output->writeln('<error>--limit 必须是正整数</error>');

            return self::FAILURE;
        }

        $jobs = Container::get(FailedJobService::class)->latest((int) $limit);
        if ($jobs === []) {
            $output->writeln('<info>没有失败任务。</info>');

            return self::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['ID', '队列', '尝试次数', '失败时间', '异常']);
        foreach ($jobs as $job) {
            $firstLine = strtok((string) ($job['exception'] ?? ''), "\n");
            $table->addRow([
                (int) $job['id'],
                (string) $job['queue'],
                (int) $job['attempts'],
                (string) $job['failed_at'],
                mb_strimwidth($firstLine === false ? '' : $firstLine, 0, 100, '…'),
            ]);
        }
        $table->render();

        return self::SUCCESS;
    }
}
