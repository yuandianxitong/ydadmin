<?php

declare(strict_types=1);

namespace tests\fixtures\Cron;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** CronCommandRunner 的测试夹具：原样回显参数；--fail 返回 1；--throw 抛异常（抛出前已有一行输出）。 */
#[AsCommand('fixture:cron', 'CronCommandRunner 测试夹具')]
final class FixtureCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('message', InputArgument::OPTIONAL, '回显内容', 'hi');
        $this->addOption('fail', null, InputOption::VALUE_NONE, '以退出码 1 结束');
        $this->addOption('throw', null, InputOption::VALUE_NONE, '抛出 RuntimeException');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('message=' . (string) $input->getArgument('message'));
        if ($input->getOption('throw')) {
            throw new \RuntimeException('夹具命令按要求抛出异常');
        }
        if ($input->getOption('fail')) {
            $output->writeln('夹具命令按要求失败');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
