<?php

declare(strict_types=1);

namespace tests\Feature\Command;

use app\command\AdminInitCommand;
use support\Db;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use tests\Support\ApiTestCase;

final class AdminInitCommandTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // 测试库里 id=1 只可能是上一次中断的本测试留下的
        Db::table('admin_roles')->where('admin_id', 1)->delete();
        Db::table('admins')->where('id', 1)->delete();
        $this->trackAdmin(1);
    }

    /** @param array<string, string> $options */
    private function runInit(array $options): CommandTester
    {
        $tester = new CommandTester(new AdminInitCommand());
        $tester->execute($options);

        return $tester;
    }

    public function test_creates_a_super_admin_that_can_log_in(): void
    {
        $tester = $this->runInit(['--username' => 'root_admin', '--password' => 'Init#2026', '--email' => 'root@test.local']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $this->assertStringContainsString('已创建', $tester->getDisplay());
        $this->assertSame([1], array_map('intval', Db::table('admin_roles')->where('admin_id', 1)->pluck('role_id')->all()));
        $this->assertSame('root_admin', Db::table('admins')->where('id', 1)->value('nickname'), '未给昵称时默认同用户名');

        $token = $this->login('root_admin', 'Init#2026')->assertOk()->data()['token'];
        $this->assertSame('*', $this->get('/adminapi/auth/info', [], $token)->assertOk()->data()['permissions'][0]);
    }

    public function test_rerun_resets_password_and_revokes_old_tokens(): void
    {
        $this->runInit(['--username' => 'root_admin', '--password' => 'Init#2026']);
        $oldToken = $this->login('root_admin', 'Init#2026')->assertOk()->data()['token'];

        $tester = $this->runInit(['--username' => 'root_admin', '--password' => 'Reset#2026', '--nickname' => '站长']);

        $this->assertStringContainsString('已重置', $tester->getDisplay());
        $this->get('/adminapi/auth/info', [], $oldToken)->assertCode(401);
        $this->login('root_admin', 'Init#2026')->assertCode(400);
        $this->login('root_admin', 'Reset#2026')->assertOk();
        $this->assertSame('站长', Db::table('admins')->where('id', 1)->value('nickname'));
    }

    public function test_rejects_short_password_and_taken_username(): void
    {
        $this->setConfig('password_min_length', '10');
        $tester = $this->runInit(['--username' => 'root_admin', '--password' => 'short12']);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('password', $tester->getDisplay());

        $other = $this->actingAsAdmin();
        $tester = $this->runInit(['--username' => $other->username, '--password' => 'LongEnough#1']);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString(lang('auth.username_exists'), $tester->getDisplay());
    }

    public function test_requires_username_and_password(): void
    {
        $this->assertSame(Command::FAILURE, $this->runInit(['--username' => 'root_admin'])->getStatusCode());
    }
}
