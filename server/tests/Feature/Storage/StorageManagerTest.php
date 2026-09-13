<?php

declare(strict_types=1);

namespace tests\Feature\Storage;

use core\exception\BusinessException;
use core\storage\driver\LocalDriver;
use core\storage\StorageInterface;
use core\storage\StorageManager;
use support\Container;
use tests\Support\ApiTestCase;

/**
 * StorageManager：每次调用现读 `storage_driver`、现 new 驱动，不缓存实例（spec §6.5）。
 *
 * 为什么这条要用真配置、真断言钉住：manager 经 php-di 以单例注入进 UploadController /
 * FileService，常驻内存下只要缓存了驱动实例，管理员在系统配置里切了存储方式也不会生效，
 * 文件继续落在旧驱动上——而接口一路返回成功，谁都看不出来。
 */
final class StorageManagerTest extends ApiTestCase
{
    private function manager(): StorageManager
    {
        return Container::get(StorageManager::class);
    }

    public function test_manager_is_a_container_singleton(): void
    {
        $this->assertSame($this->manager(), $this->manager(), 'manager 自身是单例（注入用）');
    }

    public function test_disk_returns_local_driver_by_seeded_default(): void
    {
        $this->assertSame('local', $this->manager()->driverName(), '种子里 storage_driver 默认 local');
        $this->assertInstanceOf(LocalDriver::class, $this->manager()->disk());
        $this->assertInstanceOf(StorageInterface::class, $this->manager()->disk());
    }

    public function test_disk_returns_a_fresh_driver_instance_every_call(): void
    {
        $first = $this->manager()->disk();
        $second = $this->manager()->disk();

        $this->assertNotSame($first, $second, '驱动实例不得被缓存：配置改了要立刻生效');
    }

    public function test_disk_follows_storage_driver_config_without_restart(): void
    {
        $this->assertInstanceOf(LocalDriver::class, $this->manager()->disk());

        $this->setConfig('storage_driver', 'qiniu');

        try {
            $disk = $this->manager()->disk();
            $this->fail('切到 qiniu 后必须走云分支，却拿到了 ' . get_class($disk) . '（说明驱动名被缓存了）');
        } catch (BusinessException $e) {
            $this->assertSame(lang('business.storage_sdk_not_integrated'), $e->getMessage());
            $this->assertSame(501, $e->getCode());
        }
    }

    public function test_cloud_driver_names_never_fall_back_to_local(): void
    {
        foreach (['aliyun', 'tencent', 'qiniu'] as $driver) {
            $this->setConfig('storage_driver', $driver);
            try {
                $disk = $this->manager()->disk();
                $this->fail("{$driver} 必须明确抛异常，不能静默返回 " . get_class($disk) . ' 冒充切换成功');
            } catch (BusinessException $e) {
                $this->assertSame(lang('business.storage_sdk_not_integrated'), $e->getMessage(), $driver);
            }
        }
    }

    public function test_unknown_driver_name_throws_and_does_not_fall_back(): void
    {
        $this->setConfig('storage_driver', 'not-a-real-driver');

        try {
            $disk = $this->manager()->disk();
            $this->fail('未知驱动名必须抛异常，不能悄悄回退 ' . get_class($disk));
        } catch (BusinessException $e) {
            $this->assertSame(lang('business.storage_driver_unsupported', ['driver' => 'not-a-real-driver']), $e->getMessage());
        }
    }

    public function test_disk_for_resolves_an_explicit_driver_name(): void
    {
        // Task 6 删文件时按 files.storage 回删：配置是 local，也要能按名字拿到别的驱动
        $this->assertInstanceOf(LocalDriver::class, $this->manager()->diskFor('local'));

        $this->expectException(BusinessException::class);
        $this->manager()->diskFor('qiniu');
    }

    public function test_filesystem_config_points_public_disk_at_public_storage(): void
    {
        $this->assertSame(public_path('storage'), config('filesystem.disks.public.root'));
        $this->assertSame('/storage', config('filesystem.disks.public.url'));
    }

    public function test_uploads_directory_is_git_ignored(): void
    {
        $gitignore = (string) file_get_contents(base_path() . '/.gitignore');
        $this->assertStringContainsString('/public/storage/', $gitignore, '用户上传的文件绝不能进 git');
    }
}
