<?php

declare(strict_types=1);

namespace tests\Unit\Docker;

use tests\TestCase;

final class EnsureHostsTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/yd-hosts-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0o755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/{.,}*', GLOB_BRACE) ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($this->dir);
        parent::tearDown();
    }

    public function test_rewrites_only_db_and_redis_hosts(): void
    {
        $env = $this->dir . '/.env';
        file_put_contents($env, implode("\n", [
            'DB_HOST = 127.0.0.1',
            'DB_PASSWORD = keep-me',
            'REDIS_HOST = 127.0.0.1',
            'REDIS_PASSWORD = r-secret',
            'JWT_ADMIN_SECRET = aabbcc',
            '',
        ]));

        $this->runEnsure($env);

        $text = (string) file_get_contents($env);
        $this->assertMatchesRegularExpression('/^DB_HOST\s*=\s*mysql$/m', $text);
        $this->assertMatchesRegularExpression('/^REDIS_HOST\s*=\s*redis$/m', $text);
        $this->assertMatchesRegularExpression('/^DB_PASSWORD\s*=\s*keep-me$/m', $text);
        $this->assertMatchesRegularExpression('/^REDIS_PASSWORD\s*=\s*r-secret$/m', $text);
        $this->assertMatchesRegularExpression('/^JWT_ADMIN_SECRET\s*=\s*aabbcc$/m', $text);
    }

    public function test_copies_example_when_env_missing(): void
    {
        $example = $this->dir . '/.env.example';
        $env = $this->dir . '/.env';
        file_put_contents($example, "DB_HOST = 127.0.0.1\nREDIS_HOST = 127.0.0.1\nDB_PASSWORD =\n");

        $this->runEnsure($env, $example);

        $this->assertFileExists($env);
        $text = (string) file_get_contents($env);
        $this->assertMatchesRegularExpression('/^DB_HOST\s*=\s*mysql$/m', $text);
        $this->assertMatchesRegularExpression('/^REDIS_HOST\s*=\s*redis$/m', $text);
    }

    private function runEnsure(string $env, ?string $example = null): void
    {
        $script = dirname(__DIR__, 4) . '/docker/webman/ensure-hosts.sh';
        $this->assertFileExists($script, '缺少 docker/webman/ensure-hosts.sh');
        $cmd = 'sh ' . escapeshellarg($script) . ' ' . escapeshellarg($env);
        if ($example !== null) {
            $cmd .= ' ' . escapeshellarg($example);
        }
        exec($cmd, $out, $code);
        $this->assertSame(0, $code, implode("\n", $out));
    }
}
