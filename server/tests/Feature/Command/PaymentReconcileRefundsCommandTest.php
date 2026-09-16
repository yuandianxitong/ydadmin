<?php

declare(strict_types=1);

namespace tests\Feature\Command;

use app\command\PaymentReconcileRefundsCommand;
use core\cron\CronCommandRunner;
use core\payment\dto\RefundResult;
use support\Db;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use tests\Support\ApiTestCase;
use tests\Support\Payment\RefundFixtures;

/** spec §5.7 / §9：payment:reconcile-refunds 进 cron 白名单，出厂带一条每 10 分钟的启用任务。 */
final class PaymentReconcileRefundsCommandTest extends ApiTestCase
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

    public function test_command_reconciles_and_prints_the_counts(): void
    {
        $userId = $this->createMember('80.00');
        $order = $this->createPaidOrder($userId, 5000);
        $refund = $this->createProcessingRefund($order['id'], 2000, (new \DateTimeImmutable('-5 minutes'))->format('Y-m-d H:i:s'));
        $this->wechatGateway->queue('queryRefund', new RefundResult(RefundResult::SUCCESS, 'WXR-CMD'));

        $tester = new CommandTester(new PaymentReconcileRefundsCommand());
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString('扫描 1 条：成功 1、失败 0、跳过 0', $tester->getDisplay());
        $this->assertSame('success', $this->refundRow($refund['refund_no'])->status);
    }

    public function test_command_is_whitelisted_for_cron(): void
    {
        $this->assertSame(PaymentReconcileRefundsCommand::class, config('cron.commands')['payment:reconcile-refunds'] ?? null);
        $this->assertTrue((new CronCommandRunner())->allows('payment:reconcile-refunds'));
    }

    public function test_install_seed_schedules_it_every_ten_minutes(): void
    {
        $seed = Db::table('cron_jobs')->where('command', 'payment:reconcile-refunds')->whereNull('deleted_at')->first();

        $this->assertNotNull($seed, 'init.sql 必须种入退款对账任务');
        $this->assertSame('退款结果对账', $seed->name);
        $this->assertSame('*/10 * * * *', $seed->expression);
        $this->assertSame(1, (int) $seed->status);
    }
}
