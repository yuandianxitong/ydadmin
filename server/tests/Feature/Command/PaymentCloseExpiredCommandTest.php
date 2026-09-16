<?php

declare(strict_types=1);

namespace tests\Feature\Command;

use app\command\PaymentCloseExpiredCommand;
use app\service\payment\PaymentService;
use core\cron\CronCommandRunner;
use core\payment\dto\TradeQueryResult;
use core\payment\GatewayResolver;
use core\payment\PaymentManager;
use support\Container;
use support\Db;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use tests\Support\ApiTestCase;
use tests\Support\Payment\FakeGateway;
use tests\Support\Payment\FakeGatewayResolver;

final class PaymentCloseExpiredCommandTest extends ApiTestCase
{
    private FakeGateway $wechat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wechat = new FakeGateway();
        Container::set(GatewayResolver::class, new FakeGatewayResolver(['wechat' => $this->wechat, 'alipay' => new FakeGateway()]));
        Container::set(PaymentService::class, Container::make(PaymentService::class, []));
    }

    protected function tearDown(): void
    {
        Container::set(GatewayResolver::class, Container::get(PaymentManager::class));
        Container::set(PaymentService::class, Container::make(PaymentService::class, []));
        parent::tearDown();
    }

    private function createExpiredOrder(): int
    {
        $user = $this->actingAsUser();
        $past = date('Y-m-d H:i:s', time() - 3600);
        $id = (int) Db::table('payment_orders')->insertGetId([
            'user_id'        => $user->id,
            'biz_type'       => 'recharge',
            'client_type'    => 'pc',
            'order_no'       => 'RK' . bin2hex(random_bytes(8)),
            'channel'        => 'wechat',
            'trade_type'     => 'native',
            'subject'        => '余额充值',
            'amount_cents'   => 100,
            'refunded_cents' => 0,
            'status'         => 'pending',
            'expires_at'     => $past,
            'created_at'     => $past,
            'updated_at'     => $past,
        ]);
        $this->track('payment_orders', $id);

        return $id;
    }

    public function test_prints_counts_and_succeeds(): void
    {
        $id = $this->createExpiredOrder();
        $this->wechat->queue('query', new TradeQueryResult(TradeQueryResult::CLOSED));

        $tester = new CommandTester(new PaymentCloseExpiredCommand());
        $exit = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exit);
        $this->assertStringContainsString('扫描 1 单：补记已支付 0 单，关闭 1 单，跳过 0 单。', $tester->getDisplay());
        $this->assertSame('closed', (string) Db::table('payment_orders')->where('id', $id)->value('status'));
    }

    public function test_whole_round_failure_exits_non_zero(): void
    {
        Container::set(PaymentService::class, new class () extends PaymentService {
            public function closeExpired(\DateTimeImmutable $now): array
            {
                throw new \RuntimeException('数据库不可用');
            }
        });

        $tester = new CommandTester(new PaymentCloseExpiredCommand());
        $exit = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString('关单任务失败：RuntimeException: 数据库不可用', $tester->getDisplay());
    }

    public function test_runs_through_the_cron_runner_whitelist(): void
    {
        $result = (new CronCommandRunner())->run('payment:close-expired');

        $this->assertTrue($result->success, $result->error);
        $this->assertStringContainsString('补记已支付', $result->output);
    }
}
