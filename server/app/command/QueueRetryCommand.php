<?php

declare(strict_types=1);

namespace app\command;

use app\service\system\FailedJobService;
use support\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * 命令只做参数解析与输出，逻辑在 FailedJobService::retry()。
 *
 * 参数是一个位置参数 {id|all}（spec §8.5），不是 --all 选项：`php webman queue:retry 12` 或
 * `php webman queue:retry all`。参数本身声明为可选（OPTIONAL）——若声明必填，Symfony 会在
 * execute() 之前就为缺参抛出异常，而不是让本命令按「未给参数」返回 FAILURE 并打印提示。
 */
#[AsCommand('queue:retry', '重新投递队列失败任务（投递成功后删除该记录），用法：queue:retry {id|all}')]
final class QueueRetryCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::OPTIONAL, '失败任务 ID（见 queue:failed），或字面量 all 表示全部');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getArgument('id');
        if ($id === null) {
            $output->writeln('<error>请给出一个失败任务 ID，或者传入 all（用法：queue:retry {id|all}）</error>');

            return self::FAILURE;
        }

        $all = $id === 'all';
        if (!$all && (!ctype_digit((string) $id) || (int) $id < 1)) {
            $output->writeln('<error>失败任务 ID 必须是正整数，或者传入 all</error>');

            return self::FAILURE;
        }

        $count = Container::get(FailedJobService::class)->retry($all ? null : (int) $id);
        if (!$all && $count === 0) {
            $output->writeln("<error>失败任务 {$id} 不存在，或重新投递失败（原记录已保留，详见日志）</error>");

            return self::FAILURE;
        }
        $output->writeln("<info>已重新投递 {$count} 条失败任务。</info>");

        return self::SUCCESS;
    }
}
