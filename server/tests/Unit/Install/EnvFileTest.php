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
        $this->assertStringContainsString('DB_HOST = "10.0.0.1"', $text);
        $this->assertStringContainsString('EXTRA_KEEP = yes', $text);
        $this->assertStringContainsString('# comment', $text);
        $this->assertStringContainsString('JWT_ADMIN_SECRET = "' . str_repeat('a', 64) . '"', $text);
        $this->assertMatchesRegularExpression('/DB_PASSWORD\s*=\s*"s3cret"/', $text);
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
        $this->assertStringContainsString('APP_DEBUG = "false"', $text);
        $this->assertStringContainsString('KEEP_ME = 1', $text);
    }

    /**
     * 写进去的值必须能被 phpdotenv 原样读回来：带空格的密码曾让 .env 解析直接报错（装完再也起不来），
     * 带 # 的密码被截断（连不上库）。
     *
     * @param string $value
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('trickyValues')]
    public function test_written_values_round_trip_through_dotenv(string $value): void
    {
        $dir = sys_get_temp_dir() . '/yd-env-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir . '/.env.example', "APP_DEBUG = false\nDB_PASSWORD = \nKEEP_ME = 1\n");
        $target = $dir . '/.env';

        (new EnvFile())->merge($target, $dir . '/.env.example', ['DB_PASSWORD' => $value]);

        $loaded = \Dotenv\Dotenv::createArrayBacked($dir)->load();
        $this->assertSame($value, $loaded['DB_PASSWORD']);
        $this->assertStringContainsString('KEEP_ME = 1', (string) file_get_contents($target));
    }

    /** @return array<string, array{string}> */
    public static function trickyValues(): array
    {
        return [
            '空格'     => ['pa ss word'],
            '井号'     => ['se#cret'],
            '双引号'   => ['a"b'],
            '反斜杠'   => ['a\\b'],
            '美元符'   => ['a${HOME}b'],
            '混合'     => ['p@ss w"o#rd\\x$1'],
            '普通'     => ['s3cret'],
            '空值'     => [''],
        ];
    }

    /** .env 存着库密码与两个 JWT 密钥，同机其他用户不该读得到。 */
    public function test_written_env_is_not_world_readable(): void
    {
        $dir = sys_get_temp_dir() . '/yd-env-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir . '/.env.example', "APP_DEBUG = false\n");
        $target = $dir . '/.env';

        (new EnvFile())->merge($target, $dir . '/.env.example', ['APP_DEBUG' => 'false']);

        $this->assertSame('0600', substr(sprintf('%o', fileperms($target)), -4));
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
