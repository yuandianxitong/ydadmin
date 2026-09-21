<?php

declare(strict_types=1);

namespace tests\RedLine;

use app\controller\InstallController;
use core\contract\SuperAdminInitializer;
use core\install\Installer;
use support\Container;
use tests\Support\ApiTestCase;
use tests\Support\TestResponse;

/**
 * 红线 Test54：安装 JSON 接口的原文不得带回 JWT 前缀或请求里的数据库口令。
 *
 * 会让本用例失败的生产改动：error/success 回显 input、把 JWT_ADMIN_SECRET 放进 data、或 message 拼上 db_password。
 */
final class Test54_InstallResponseNoSecretTest extends ApiTestCase
{
    private string $workDir;

    private string $lockPath;

    private Installer $originalInstaller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalInstaller = Container::get(Installer::class);
        $this->workDir = sys_get_temp_dir() . '/yd-rl54-' . bin2hex(random_bytes(4));
        mkdir($this->workDir, 0o755, true);
        $this->lockPath = $this->workDir . '/install.lock';
        file_put_contents($this->lockPath, date('c'));
        Container::set(Installer::class, new Installer(
            Container::get(SuperAdminInitializer::class),
            base_path() . '/database/install',
            $this->workDir . '/.env',
            base_path() . '/.env.example',
            $this->lockPath,
        ));
        Container::set(InstallController::class, Container::make(InstallController::class));
    }

    protected function tearDown(): void
    {
        Container::set(Installer::class, $this->originalInstaller);
        Container::set(InstallController::class, Container::make(InstallController::class));
        $this->removeDir($this->workDir);
        parent::tearDown();
    }

    public function test_environment_test_connection_and_run_do_not_echo_jwt_or_db_password(): void
    {
        $password = 'uniq-pass-' . bin2hex(random_bytes(8));
        $payload = $this->payload($password);

        $responses = [
            $this->get('/install/environment'),
            $this->post('/install/test-connection', $payload),
            $this->post('/install/run', $payload),
        ];

        foreach ($responses as $response) {
            $this->assertSame(200, $response->status());
            $this->assertNoSecret($response, $password);
        }
    }

    private function assertNoSecret(TestResponse $response, string $password): void
    {
        $body = $response->body();
        $this->assertStringNotContainsString('JWT_ADMIN', $body);
        $this->assertStringNotContainsString($password, $body);
        $this->assertStringNotContainsString($password, $response->message());
    }

    /** @return array<string, mixed> */
    private function payload(string $dbPassword): array
    {
        $mysql = (array) config('database.connections.mysql');
        $redis = (array) config('redis.default');

        return [
            'db_host'        => (string) $mysql['host'],
            'db_port'        => $mysql['port'],
            'db_name'        => (string) $mysql['database'],
            'db_user'        => (string) $mysql['username'],
            'db_password'    => $dbPassword,
            'redis_host'     => (string) $redis['host'],
            'redis_port'     => $redis['port'],
            'redis_password' => (string) ($redis['password'] ?? ''),
            'redis_db'       => $redis['database'],
            'username'       => 'admin',
            'password'       => 'Secret123',
        ];
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }
}
