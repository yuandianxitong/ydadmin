<?php

declare(strict_types=1);

namespace tests\Feature\Command;

use app\command\PaymentRefundCommand;
use core\cron\CronCommandRunner;
use core\payment\dto\RefundResult;
use core\payment\exception\GatewayResultUnknownException;
use core\payment\GatewayResolver;
use core\payment\PaymentGatewayInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use tests\Support\ApiTestCase;
use tests\Support\Payment\RefundFixtures;

/** spec §5.6：payment:refund 只能手动执行；退出码 0 成功、1 失败（含前置校验失败）、2 处理中。 */
final class PaymentRefundCommandTest extends ApiTestCase
{
    use RefundFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installFakeGateways();
    }

    protected function tearDown(): void
    {
        $this->restoreGatewaysAndCleanup();
        parent::tearDown();
    }

    /** @param array<string, string> $input */
    private function runRefund(array $input): CommandTester
    {
        $tester = new CommandTester(new PaymentRefundCommand());
        $tester->execute($input);

        return $tester;
    }

    public function test_success_exits_zero_and_records_the_cli_operator(): void
    {
        $userId = $this->createMember('100.00');
        $order = $this->createPaidOrder($userId, 5000);
        $this->wechatGateway->queue('refund', new RefundResult(RefundResult::SUCCESS, 'WXR-CLI'));

        $tester = $this->runRefund(['order_no' => $order['order_no'], 'amount' => '12.50', '--reason' => '客服处理']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString('退款成功', $tester->getDisplay());
        $this->assertStringContainsString('12.50 元', $tester->getDisplay());
        $refund = \support\Db::table('refund_orders')->where('payment_order_id', $order['id'])->first();
        $this->assertNotNull($refund);
        $this->assertStringContainsString((string) $refund->refund_no, $tester->getDisplay());
        $this->assertMatchesRegularExpression('/^cli:.+$/', (string) $refund->operator);
        $this->assertSame('客服处理', $refund->reason);
        $this->assertSame('87.50', $this->balanceOf($userId));
    }

    public function test_definite_failure_exits_one(): void
    {
        $userId = $this->createMember('100.00');
        $order = $this->createPaidOrder($userId, 5000);
        $this->wechatGateway->queue('refund', new RefundResult(RefundResult::FAILED, null, 'NOT_ENOUGH'));

        $tester = $this->runRefund(['order_no' => $order['order_no'], 'amount' => '10']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('退款失败', $tester->getDisplay());
        $this->assertSame('100.00', $this->balanceOf($userId));
    }

    public function test_unknown_result_exits_two(): void
    {
        $userId = $this->createMember('100.00');
        $order = $this->createPaidOrder($userId, 5000);
        $this->wechatGateway->queue('refund', new GatewayResultUnknownException('timeout'));

        $tester = $this->runRefund(['order_no' => $order['order_no'], 'amount' => '10']);

        $this->assertSame(PaymentRefundCommand::EXIT_PROCESSING, $tester->getStatusCode());
        $this->assertSame(2, PaymentRefundCommand::EXIT_PROCESSING);
        $this->assertStringContainsString('处理中', $tester->getDisplay());
        $this->assertStringContainsString('payment:reconcile-refunds', $tester->getDisplay());
    }

    public function test_precondition_failure_exits_one_with_the_business_message(): void
    {
        $userId = $this->createMember('100.00');
        $order = $this->createPaidOrder($userId, 5000);

        $tester = $this->runRefund(['order_no' => $order['order_no'], 'amount' => '50.01']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString(lang('payment.refund_exceeds'), $tester->getDisplay());
        $this->assertSame([], $this->wechatGateway->calls());
    }

    public function test_invalid_amounts_exit_one_without_calling_the_service(): void
    {
        $userId = $this->createMember('100.00');
        $order = $this->createPaidOrder($userId, 5000);

        foreach (['0', '0.00', '-1', '1.234', 'abc', '01', '', '12345678901234', '1234567890123456'] as $amount) {
            $tester = $this->runRefund(['order_no' => $order['order_no'], 'amount' => $amount]);
            $this->assertSame(Command::FAILURE, $tester->getStatusCode(), "金额 {$amount}");
            $this->assertStringContainsString('退款金额', $tester->getDisplay(), "金额 {$amount}");
        }

        $this->assertSame(0, $this->refundCountOf($order['id']));
        $this->assertSame([], $this->wechatGateway->calls());
    }

    public function test_unexpected_exception_exits_one_with_the_class_name_only(): void
    {
        $userId = $this->createMember('100.00');
        $order = $this->createPaidOrder($userId, 5000);
        $this->installFakeGateways(new class () implements GatewayResolver {
            public function isEnabled(string $channel): bool
            {
                return true;
            }

            public function gateway(string $channel): PaymentGatewayInterface
            {
                throw new \RuntimeException('SQLSTATE[HY000] ... bindings: o-secret-openid');
            }
        });

        $tester = $this->runRefund(['order_no' => $order['order_no'], 'amount' => '10']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('RuntimeException', $tester->getDisplay());
        $this->assertStringNotContainsString('o-secret-openid', $tester->getDisplay(), '异常消息可能带 SQL 绑定值，不得输出');
        $this->assertSame('100.00', $this->balanceOf($userId));
    }

    public function test_refund_command_is_not_in_the_cron_whitelist(): void
    {
        $this->assertArrayNotHasKey('payment:refund', (array) config('cron.commands'));
        $this->assertFalse((new CronCommandRunner())->allows('payment:refund R1 1.00'), '退款只能人工在命令行执行');
    }
}
