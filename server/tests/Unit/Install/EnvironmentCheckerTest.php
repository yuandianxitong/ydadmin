<?php

declare(strict_types=1);

namespace tests\Unit\Install;

use app\service\system\AdminService;
use core\contract\SuperAdminInitializer;
use core\install\EnvironmentChecker;
use support\Container;
use tests\TestCase;

final class EnvironmentCheckerTest extends TestCase
{
    public function test_check_includes_required_keys_and_php_passes(): void
    {
        $rows = (new EnvironmentChecker())->check();
        $byKey = [];
        foreach ($rows as $row) {
            $byKey[$row['key']] = $row;
        }

        foreach (['php', 'pdo_mysql', 'redis', 'pcntl', 'posix', 'runtime', 'env'] as $key) {
            $this->assertArrayHasKey($key, $byKey);
        }
        $this->assertTrue($byKey['php']['ok']);
    }

    public function test_check_rows_have_shape_and_remaining_spec_extensions(): void
    {
        $rows = (new EnvironmentChecker())->check();
        foreach ($rows as $row) {
            $this->assertSame(['key', 'name', 'required', 'current', 'ok', 'critical'], array_keys($row));
            $this->assertTrue($row['critical']);
            $this->assertNotSame('', $row['name']);
        }
        $keys = array_column($rows, 'key');
        foreach (['mbstring', 'json', 'openssl', 'curl'] as $key) {
            $this->assertContains($key, $keys);
        }
    }

    public function test_install_lang_keys_exist_in_both_locales(): void
    {
        $keys = [
            'already_installed', 'database_not_empty', 'sql_failed', 'baseline_required',
            'invalid_update_dir', 'restart_hint', 'env_php', 'env_extension', 'env_writable',
        ];
        foreach ($keys as $key) {
            $full = 'install.' . $key;
            $this->assertNotSame($full, lang($full, ['name' => 'redis', 'path' => '.env'], 'zh_CN'), $full);
            $this->assertNotSame($full, lang($full, ['name' => 'redis', 'path' => '.env'], 'en'), $full);
        }
    }

    public function test_container_binds_super_admin_initializer_to_admin_service(): void
    {
        $this->assertInstanceOf(AdminService::class, Container::get(SuperAdminInitializer::class));
    }
}
