<?php

declare(strict_types=1);

namespace app\command;

use app\service\payment\RefundService;
use support\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * 退款结果对账（M5b spec §5.7）。定时任务白名单里的命令（config/cron.php），出厂每 10 分钟跑一次；
 * 也可以在命令行直接执行。单轮至多 payment.reconcile_batch 条，每条的网关调用都有超时，
 * 单条出错不中断整轮。逻辑在 RefundService::reconcile()。
 */
#[AsCommand('payment:reconcile-refunds', '查询处理中的退款并结算结果，失败的退款把余额加回')]
final class PaymentReconcileRefundsCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $counts = Container::get(RefundService::class)->reconcile(new \DateTimeImmutable());

        $output->writeln(sprintf(
            '<info>退款对账：扫描 %d 条：成功 %d、失败 %d、跳过 %d</info>',
            $counts['scanned'],
            $counts['success'],
            $counts['failed'],
            $counts['skipped'],
        ));

        return self::SUCCESS;
    }
}
