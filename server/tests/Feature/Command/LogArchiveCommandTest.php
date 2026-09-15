<?php

declare(strict_types=1);

namespace tests\Feature\Command;

use app\command\LogArchiveCommand;
use app\service\system\LogService;
use support\Db;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use tests\Support\ApiTestCase;

final class LogArchiveCommandTest extends ApiTestCase
{
    private function insertOperationLog(int $adminId, string $operationTime): void
    {
        Db::table('admin_operation_logs')->insert([
            'admin_id'       => $adminId,
            'username'       => 'archive_probe',
            'method'         => 'POST',
            'path'           => '/adminapi/demo',
            'operation_time' => $operationTime,
            'created_at'     => $operationTime,
        ]);
    }

    private function insertLoginLog(int $adminId, string $loginTime): void
    {
        Db::table('admin_login_logs')->insert([
            'admin_id'     => $adminId,
            'username'     => 'archive_probe',
            'login_time'   => $loginTime,
            'login_result' => 1,
            'created_at'   => $loginTime,
        ]);
    }

    /** @param array<string, string> $options */
    private function runArchive(array $options = []): CommandTester
    {
        $tester = new CommandTester(new LogArchiveCommand());
        $tester->execute($options);

        return $tester;
    }

    public function test_deletes_only_logs_older_than_the_cutoff(): void
    {
        $admin = $this->actingAsAdmin();
        $old = (new \DateTimeImmutable('-40 days'))->format('Y-m-d H:i:s');
        $recent = (new \DateTimeImmutable('-5 days'))->format('Y-m-d H:i:s');
        $this->insertOperationLog($admin->id, $old);
        $this->insertOperationLog($admin->id, $old);
        $this->insertOperationLog($admin->id, $recent);
        $this->insertLoginLog($admin->id, $old);
        $this->insertLoginLog($admin->id, $recent);

        // 测试库是共享的：输出里的条数是全表早于截止时间的行数，先按同一个截止时间数出期望值
        $cutoff = LogService::archiveCutoff(30);
        $expectedOperation = Db::table('admin_operation_logs')->where('operation_time', '<', $cutoff)->count();
        $expectedLogin = Db::table('admin_login_logs')->where('login_time', '<', $cutoff)->count();

        $tester = $this->runArchive(['--days' => '30']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame([$recent], Db::table('admin_operation_logs')->where('admin_id', $admin->id)->pluck('operation_time')->map(static fn ($t): string => (string) $t)->all());
        $this->assertSame([$recent], Db::table('admin_login_logs')->where('admin_id', $admin->id)->pluck('login_time')->map(static fn ($t): string => (string) $t)->all());
        $this->assertStringContainsString("操作日志 {$expectedOperation} 条", $tester->getDisplay());
        $this->assertStringContainsString("登录日志 {$expectedLogin} 条", $tester->getDisplay());
        $this->assertGreaterThanOrEqual(2, $expectedOperation);
        $this->assertGreaterThanOrEqual(1, $expectedLogin);
    }

    public function test_defaults_to_ninety_days(): void
    {
        $admin = $this->actingAsAdmin();
        $this->insertOperationLog($admin->id, (new \DateTimeImmutable('-100 days'))->format('Y-m-d H:i:s'));
        $kept = (new \DateTimeImmutable('-80 days'))->format('Y-m-d H:i:s');
        $this->insertOperationLog($admin->id, $kept);

        $tester = $this->runArchive();

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertSame([$kept], Db::table('admin_operation_logs')->where('admin_id', $admin->id)->pluck('operation_time')->map(static fn ($t): string => (string) $t)->all());
    }

    public function test_rejects_days_below_one_or_non_integer(): void
    {
        $admin = $this->actingAsAdmin();
        $old = (new \DateTimeImmutable('-400 days'))->format('Y-m-d H:i:s');
        $this->insertOperationLog($admin->id, $old);

        foreach (['0', '-3', 'abc', '1.5'] as $days) {
            $tester = $this->runArchive(['--days' => $days]);
            $this->assertSame(Command::FAILURE, $tester->getStatusCode(), "--days={$days} 必须拒绝");
            $this->assertStringContainsString('--days', $tester->getDisplay());
        }
        $this->assertSame(1, Db::table('admin_operation_logs')->where('admin_id', $admin->id)->count(), '参数非法时不得删除任何行');
    }

    public function test_service_rejects_days_below_one(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new LogService())->archive(0);
    }
}
