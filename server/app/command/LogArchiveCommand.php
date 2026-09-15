<?php

declare(strict_types=1);

namespace app\command;

use app\service\system\LogService;
use support\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * 归档清理管理员日志（移植 TP8 log:archive）。定时任务白名单里的第一条命令（config/cron.php），
 * 也可以直接在命令行执行。命令只做参数解析与输出，逻辑在 LogService::archive()。
 */
#[AsCommand('log:archive', '清理早于指定天数的管理员操作日志与登录日志')]
final class LogArchiveCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('days', null, InputOption::VALUE_REQUIRED, '保留最近多少天（≥1 的整数）', '90');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $raw = (string) $input->getOption('days');
        if (preg_match('/^[1-9]\d*$/', $raw) !== 1) {
            $output->writeln('<error>--days 必须是 ≥1 的整数</error>');

            return self::FAILURE;
        }
        $days = (int) $raw;

        $cutoff = LogService::archiveCutoff($days);
        $result = Container::get(LogService::class)->archive($days);

        $output->writeln(sprintf(
            '<info>已清理 %s 之前的操作日志 %d 条、登录日志 %d 条。</info>',
            $cutoff,
            $result['operation'],
            $result['login']
        ));

        return self::SUCCESS;
    }
}
