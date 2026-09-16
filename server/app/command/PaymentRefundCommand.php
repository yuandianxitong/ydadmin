<?php

declare(strict_types=1);

namespace app\command;

use app\service\payment\RefundService;
use core\exception\BusinessException;
use core\payment\dto\RefundResult;
use core\payment\Money;
use support\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * 对已支付订单发起（部分）退款（M5b spec §5.6）。只能人工在命令行执行：不进 config/cron.php 白名单
 * （红线 Test25 钉住），避免在后台定时任务界面里被随手点出去。逻辑在 RefundService::refund()。
 *
 * 退出码：0 退款成功；1 失败（含参数与前置校验失败）；2 处理中（结果由 payment:reconcile-refunds 确认）。
 */
#[AsCommand('payment:refund', '对已支付订单发起（部分）退款：充值单先扣余额，渠道明确失败时加回')]
final class PaymentRefundCommand extends Command
{
    public const EXIT_PROCESSING = 2;

    protected function configure(): void
    {
        $this->addArgument('order_no', InputArgument::REQUIRED, '商户订单号');
        $this->addArgument('amount', InputArgument::REQUIRED, '退款金额（元，大于 0，最多两位小数）');
        $this->addOption('reason', null, InputOption::VALUE_REQUIRED, '退款原因（超过 80 字节截断）', '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $orderNo = trim((string) $input->getArgument('order_no'));
        $amount = trim((string) $input->getArgument('amount'));
        // 整数部分至多 13 位：保证 Money::toCents() 不会因超长抛 InvalidArgumentException
        if (preg_match('/^(0|[1-9]\d{0,12})(\.\d{1,2})?$/D', $amount) !== 1 || Money::toCents($amount) <= 0) {
            $output->writeln('<error>退款金额必须是大于 0、最多两位小数的元金额</error>');

            return self::FAILURE;
        }

        try {
            $result = Container::get(RefundService::class)->refund($orderNo, $amount, (string) $input->getOption('reason'), self::operator());
        } catch (BusinessException $e) {
            $output->writeln('<error>退款未发起：' . $e->getMessage() . '</error>');

            return self::FAILURE;
        } catch (\Throwable $e) {
            // 命令边界兜底：只输出异常类名。数据库异常的消息带 SQL 与绑定值，不能打到终端或定时任务日志
            $output->writeln(sprintf(
                '<error>退款命令异常中止（%s）。退款单可能已创建，请核对 refund_orders，处理中的单由 payment:reconcile-refunds 对账</error>',
                $e::class
            ));

            return self::FAILURE;
        }

        $summary = sprintf('退款单 %s，金额 %s 元', $result['refund_no'], $result['amount']);
        if ($result['status'] === RefundResult::SUCCESS) {
            $output->writeln('<info>退款成功：' . $summary . '</info>');

            return self::SUCCESS;
        }
        if ($result['status'] === RefundResult::FAILED) {
            $output->writeln('<error>退款失败：' . $summary . '（渠道明确拒绝；充值单已把余额加回）</error>');

            return self::FAILURE;
        }
        $output->writeln('<comment>退款处理中：' . $summary . '，结果由 payment:reconcile-refunds 对账确认</comment>');

        return self::EXIT_PROCESSING;
    }

    /** 执行者记为 cli:{系统用户名}，取不到时 cli:unknown。 */
    private static function operator(): string
    {
        $info = function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid()) : false;
        $name = is_array($info) ? $info['name'] : '';

        return 'cli:' . ($name !== '' ? $name : 'unknown');
    }
}
