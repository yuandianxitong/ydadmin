<?php

declare(strict_types=1);

namespace tests\Feature\Install;

use app\controller\InstallController;
use core\contract\SuperAdminInitializer;
use core\install\Installer;
use support\Container;
use support\Db;
use tests\Support\ApiTestCase;
use tests\Support\TestResponse;

final class InstallWizardTest extends ApiTestCase
{
    private string $workDir;

    private string $lockPath;

    private Installer $originalInstaller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalInstaller = Container::get(Installer::class);
        $this->workDir = sys_get_temp_dir() . '/yd-wiz-' . bin2hex(random_bytes(4));
        mkdir($this->workDir, 0o755, true);
        $this->lockPath = $this->workDir . '/install.lock';
        Container::set(Installer::class, new Installer(
            Container::get(SuperAdminInitializer::class),
            base_path() . '/database/install',
            $this->workDir . '/.env',
            base_path() . '/.env.example',
            $this->lockPath,
        ));
        if (class_exists(InstallController::class)) {
            Container::set(InstallController::class, Container::make(InstallController::class));
        }
        Db::table('system_upgrades')->delete();
    }

    protected function tearDown(): void
    {
        Container::set(Installer::class, $this->originalInstaller);
        if (class_exists(InstallController::class)) {
            Container::set(InstallController::class, Container::make(InstallController::class));
        }
        if ((int) Db::table('system_upgrades')->count() === 0) {
            Db::table('system_upgrades')->insert([
                'version'    => '2.0.0',
                'applied_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $this->removeDir($this->workDir);
        parent::tearDown();
    }

    /** 向导页发的双提交令牌；写操作都要带上。 */
    private function tokenHeaders(): array
    {
        preg_match('/yd_install_token=([0-9a-f]{32})/', (string) $this->get('/install')->header('Set-Cookie'), $m);

        return ['Cookie' => 'yd_install_token=' . ($m[1] ?? ''), 'X-Install-Token' => $m[1] ?? ''];
    }

    public function test_wizard_page_is_html_with_license_when_not_installed(): void
    {
        $this->assertSame(0, Db::table('system_upgrades')->count());
        $this->assertFileDoesNotExist($this->lockPath);

        $response = $this->get('/install');

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('text/html', (string) $response->header('Content-Type'));
        $body = $response->body();
        $this->assertTrue(
            str_contains($body, 'license') || str_contains($body, '协议'),
            '未安装时应返回含 license 或「协议」的向导页'
        );

        $trailing = $this->get('/install/');
        $this->assertSame(200, $trailing->status());
        $this->assertStringContainsString('text/html', (string) $trailing->header('Content-Type'));
        $trailingBody = $trailing->body();
        $this->assertTrue(
            str_contains($trailingBody, 'license') || str_contains($trailingBody, '协议'),
            'GET /install/ 未安装时也应返回含 license 或「协议」的向导页'
        );
    }

    public function test_already_installed_keeps_html_and_rejects_json_without_secrets(): void
    {
        file_put_contents($this->lockPath, date('c'));

        $page = $this->get('/install');
        $this->assertSame(200, $page->status());
        $this->assertStringContainsString('text/html', (string) $page->header('Content-Type'));
        $this->assertStringContainsString(lang('install.already_installed'), $page->body());

        $trailing = $this->get('/install/');
        $this->assertSame(200, $trailing->status());
        $this->assertStringContainsString(lang('install.already_installed'), $trailing->body());

        $environment = $this->get('/install/environment');
        $this->assertSame(200, $environment->status());
        $this->assertSame(400, $environment->code());
        $this->assertSame(lang('install.already_installed'), $environment->message());
        $this->assertJsonHasNoSecrets($environment);

        $run = $this->post('/install/run', $this->payload());
        $this->assertSame(200, $run->status());
        $this->assertSame(400, $run->code());
        $this->assertSame(lang('install.already_installed'), $run->message());
        $this->assertJsonHasNoSecrets($run);
        $this->assertNotSame(401, $run->code());
    }

    /**
     * 安装向导在装完之前是未登录可达的：没有跨站防护时，受害者随便打开一个页面，
     * 那个页面就能 POST 过来把 .env 指向攻击者的库并在那边建超管。
     * 令牌走双提交：向导页发 cookie，请求头带同一个值，跨站页面读不到 cookie 也写不了这个头。
     */
    public function test_state_changing_endpoints_require_the_wizard_token(): void
    {
        $this->post('/install/test-connection', $this->payload())->assertCode(403);
        $this->post('/install/run', $this->payload())->assertCode(403);

        // 令牌对不上同样拒绝
        $this->post('/install/run', $this->payload(), null, [
            'Cookie'         => 'yd_install_token=aaaa',
            'X-Install-Token' => 'bbbb',
        ])->assertCode(403);

        // 带着向导页发的 cookie 与请求头就能过防护（连接失败是另一回事，不是 403）
        $page = $this->get('/install');
        $cookie = (string) $page->header('Set-Cookie');
        $this->assertMatchesRegularExpression('/yd_install_token=[0-9a-f]{32}/', $cookie, '向导页要下发令牌 cookie');
        preg_match('/yd_install_token=([0-9a-f]{32})/', $cookie, $m);
        $withToken = $this->post('/install/test-connection', $this->payload(), null, [
            'Cookie'          => 'yd_install_token=' . $m[1],
            'X-Install-Token' => $m[1],
        ]);
        $this->assertNotSame(403, $withToken->code());
    }

    /** 跨站来源即使拿到了令牌也要拒（令牌泄漏时的第二道）。 */
    public function test_cross_origin_requests_are_rejected(): void
    {
        $token = str_repeat('a', 32);
        $this->post('/install/run', $this->payload(), null, [
            'Cookie'          => 'yd_install_token=' . $token,
            'X-Install-Token' => $token,
            'Origin'          => 'http://evil.example.com',
        ])->assertCode(403);
    }

    public function test_environment_returns_nonempty_checks_when_not_installed(): void
    {
        $data = $this->get('/install/environment')->assertOk()->data();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('checks', $data);
        $this->assertNotEmpty($data['checks']);
        foreach ($data['checks'] as $item) {
            $this->assertIsArray($item);
            $this->assertArrayHasKey('key', $item);
            $this->assertArrayHasKey('ok', $item);
        }
        $this->assertArrayHasKey('defaults', $data);
        $defaults = $data['defaults'];
        $this->assertIsArray($defaults);
        foreach (['db_host', 'db_port', 'db_name', 'redis_host', 'redis_port', 'redis_db'] as $key) {
            $this->assertArrayHasKey($key, $defaults);
        }
        $this->assertArrayNotHasKey('db_password', $defaults);
        $this->assertArrayNotHasKey('db_user', $defaults, '未登录可达，库用户名不外送');
        $this->assertArrayNotHasKey('redis_password', $defaults);
        $encoded = json_encode($defaults);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('JWT_', $encoded);
    }

    public function test_test_connection_accepts_test_database_and_redis(): void
    {
        $this->post('/install/test-connection', $this->payload(), null, $this->tokenHeaders())->assertOk();
    }

    public function test_run_rejects_short_password_before_installer(): void
    {
        $response = $this->post('/install/run', $this->payload(['password' => 'admin']), null, $this->tokenHeaders());

        $this->assertSame(200, $response->status());
        $this->assertSame(422, $response->code());
        $this->assertArrayHasKey('password', $response->data()['errors']);
        $this->assertFileDoesNotExist($this->lockPath);
    }

    public function test_run_rejects_short_username_before_installer(): void
    {
        $response = $this->post('/install/run', $this->payload(['username' => 'ab']), null, $this->tokenHeaders());

        $this->assertSame(200, $response->status());
        $this->assertSame(422, $response->code());
        $this->assertArrayHasKey('username', $response->data()['errors']);
        $this->assertFileDoesNotExist($this->lockPath);
    }

    public function test_run_rejects_nonempty_test_database_without_changing_roles(): void
    {
        $rolesBefore = (int) Db::table('roles')->count();

        $response = $this->post('/install/run', $this->payload(), null, $this->tokenHeaders());

        $this->assertSame(200, $response->status());
        $this->assertSame(400, $response->code());
        $this->assertSame(lang('install.database_not_empty'), $response->message());
        $this->assertSame($rolesBefore, (int) Db::table('roles')->count());
        $this->assertFileDoesNotExist($this->lockPath);
    }

    public function test_json_data_does_not_leak_jwt_secrets(): void
    {
        $responses = [
            $this->get('/install/environment'),
            $this->post('/install/test-connection', $this->payload()),
            $this->post('/install/run', $this->payload()),
        ];
        file_put_contents($this->lockPath, date('c'));
        $responses[] = $this->get('/install/environment');
        $responses[] = $this->post('/install/run', $this->payload());

        foreach ($responses as $response) {
            $this->assertSame(200, $response->status());
            $this->assertContains($response->code(), [200, 400, 403], '无令牌的写操作现在是 403，同样不许带出密钥');
            $encoded = json_encode($response->data());
            $this->assertIsString($encoded);
            $this->assertStringNotContainsString('JWT_ADMIN_SECRET', $encoded);
            $this->assertDoesNotMatchRegularExpression('/[0-9a-f]{64}/', $encoded);
            $this->assertJsonHasNoSecrets($response);
        }
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        $mysql = (array) config('database.connections.mysql');
        $redis = (array) config('redis.default');

        return array_replace([
            'db_host'        => (string) $mysql['host'],
            'db_port'        => $mysql['port'],
            'db_name'        => (string) $mysql['database'],
            'db_user'        => (string) $mysql['username'],
            'db_password'    => (string) $mysql['password'],
            'redis_host'     => (string) $redis['host'],
            'redis_port'     => $redis['port'],
            'redis_password' => (string) ($redis['password'] ?? ''),
            'redis_db'       => $redis['database'],
            'username'       => 'admin',
            'password'       => 'Secret123',
        ], $overrides);
    }

    private function assertJsonHasNoSecrets(TestResponse $response): void
    {
        $raw = $response->body();
        $this->assertStringNotContainsString('DB_PASSWORD', $raw);
        $this->assertStringNotContainsString('JWT_', $raw);

        $mysqlPassword = (string) (((array) config('database.connections.mysql'))['password'] ?? '');
        $mysqlUser = (string) (((array) config('database.connections.mysql'))['username'] ?? '');
        if ($mysqlPassword !== '' && $mysqlPassword !== $mysqlUser && str_contains($raw, $mysqlPassword)) {
            $this->fail('响应泄漏了数据库口令');
        }
        $redisPassword = (string) (((array) config('redis.default'))['password'] ?? '');
        if ($redisPassword !== '' && str_contains($raw, $redisPassword)) {
            $this->fail('响应泄漏了 Redis 口令');
        }
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
