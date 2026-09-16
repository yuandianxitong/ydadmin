<?php

declare(strict_types=1);

namespace app\command;

use app\service\payment\PaymentService;
use support\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * 支付订单超时关闭（M5b spec §5.5）。进定时任务白名单（config/cron.php），种子每 5 分钟一次。
 * 命令只做调用与输出，逻辑在 PaymentService::closeExpired()；单个订单的失败在服务里吞掉并计入「跳过」，
 * 这里只兜整轮失败（例如数据库不可用），返回非 0 让定时任务日志记为失败。
 */
#[AsCommand('payment:close-expired', '关闭超过支付时限的待支付订单；已支付的补记入账')]
final class PaymentCloseExpiredCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $counts = Container::get(PaymentService::class)->closeExpired(new \DateTimeImmutable());
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<error>关单任务失败：%s: %s</error>', $e::class, $e->getMessage()));

            return self::FAILURE;
        }

        $output->writeln(sprintf(
            '<info>扫描 %d 单：补记已支付 %d 单，关闭 %d 单，跳过 %d 单。</info>',
            $counts['scanned'],
            $counts['paid'],
            $counts['closed'],
            $counts['skipped']
        ));

        return self::SUCCESS;
    }
}
