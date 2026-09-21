<?php

declare(strict_types=1);

namespace tests\Unit\Install;

use core\install\EnvFile;
use tests\TestCase;

final class EnvFileTest extends TestCase
{
    public function test_merge_creates_from_example_and_preserves_unknown_keys(): void
    {
        $dir = sys_get_temp_dir() . '/yd-env-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir . '/.env.example', "APP_DEBUG = false\nDB_HOST = 127.0.0.1\nEXTRA_KEEP = yes\n# comment\n");
        $target = $dir . '/.env';
        copy($dir . '/.env.example', $target);

        (new EnvFile())->merge($target, $dir . '/.env.example', [
            'APP_DEBUG' => 'false',
            'DB_HOST' => '10.0.0.1',
            'DB_PORT' => '3306',
            'DB_NAME' => 'yd',
            'DB_USER' => 'root',
            'DB_PASSWORD' => 's3cret',
            'DB_PREFIX' => '',
            'REDIS_HOST' => '127.0.0.1',
            'REDIS_PORT' => '6379',
            'REDIS_PASSWORD' => '',
            'REDIS_DB' => '0',
            'JWT_ADMIN_SECRET' => str_repeat('a', 64),
            'JWT_USER_SECRET' => str_repeat('b', 64),
        ]);

        $text = file_get_contents($target);
        $this->assertStringContainsString('DB_HOST = 10.0.0.1', $text);
        $this->assertStringContainsString('EXTRA_KEEP = yes', $text);
        $this->assertStringContainsString('# comment', $text);
        $this->assertStringContainsString('JWT_ADMIN_SECRET = ' . str_repeat('a', 64), $text);
        $this->assertMatchesRegularExpression('/DB_PASSWORD\s*=\s*s3cret/', $text);
    }

    public function test_merge_copies_example_when_target_missing(): void
    {
        $dir = sys_get_temp_dir() . '/yd-env-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir . '/.env.example', "APP_DEBUG = true\nKEEP_ME = 1\n");
        $target = $dir . '/.env';

        (new EnvFile())->merge($target, $dir . '/.env.example', [
            'APP_DEBUG' => 'false',
        ]);

        $text = (string) file_get_contents($target);
        $this->assertFileExists($target);
        $this->assertStringContainsString('APP_DEBUG = false', $text);
        $this->assertStringContainsString('KEEP_ME = 1', $text);
    }

    public function test_managed_keys_are_the_thirteen_install_fields(): void
    {
        $this->assertSame([
            'APP_DEBUG', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_PREFIX',
            'REDIS_HOST', 'REDIS_PORT', 'REDIS_PASSWORD', 'REDIS_DB',
            'JWT_ADMIN_SECRET', 'JWT_USER_SECRET',
        ], EnvFile::MANAGED_KEYS);
    }
}
