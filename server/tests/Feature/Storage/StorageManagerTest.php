<?php

declare(strict_types=1);

namespace tests\Feature\Storage;

use core\exception\BusinessException;
use core\storage\driver\AliyunOssDriver;
use core\storage\driver\LocalDriver;
use core\storage\StorageInterface;
use core\storage\StorageManager;
use support\Container;
use support\Db;
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

        // 只切驱动、不填凭据：必须立刻报「配置不全」，而不是继续返回 LocalDriver
        $this->setConfig('storage_driver', 'qiniu');

        try {
            $disk = $this->manager()->disk();
            $this->fail('切到 qiniu 后必须走云分支，却拿到了 ' . get_class($disk) . '（说明驱动名被缓存了）');
        } catch (BusinessException $e) {
            $this->assertSame(lang('business.storage_config_incomplete'), $e->getMessage());
        }
    }

    public function test_cloud_driver_names_never_fall_back_to_local_when_credentials_missing(): void
    {
        foreach (['aliyun', 'tencent', 'qiniu'] as $driver) {
            $this->setConfig('storage_driver', $driver);
            try {
                $disk = $this->manager()->disk();
                $this->fail("{$driver} 凭据为空时必须抛异常，不能静默返回 " . get_class($disk) . ' 冒充切换成功');
            } catch (BusinessException $e) {
                $this->assertSame(lang('business.storage_config_incomplete'), $e->getMessage(), $driver);
            }
        }
    }

    public function test_disk_builds_the_real_cloud_driver_once_credentials_are_filled(): void
    {
        $this->setConfig('storage_driver', 'aliyun');
        $this->setConfig('storage_oss_access_key', 'dummy-ak');
        $this->setConfig('storage_oss_access_secret', 'dummy-sk');
        $this->setConfig('storage_oss_bucket', 'demo-bucket');
        // 故意不配 storage_oss_region：这条同时验证「region 留空时从 endpoint 推导」这条兜底链路
        // 在 manager → 驱动的真实装配下也是通的（优先级本身由 CloudDriverConfigTest 单测覆盖）
        $this->setConfig('storage_oss_endpoint', 'oss-cn-hangzhou.aliyuncs.com');

        $disk = $this->manager()->disk();

        $this->assertInstanceOf(AliyunOssDriver::class, $disk);
        $this->assertSame('aliyun', $this->manager()->driverName(), 'files.storage 与上传响应用的就是这个名字');
        $this->assertSame('https://demo-bucket.oss-cn-hangzhou.aliyuncs.com/a.png', $disk->getUrl('a.png'));
        $this->assertNotSame($disk, $this->manager()->disk(), '云驱动同样每次现 new');
    }

    /**
     * 驱动名闭环：`driverName()` 可能返回的值 == `diskFor()` 认识的值 == 种子里
     * `storage_driver` 的 config_options 键集（local|aliyun|tencent|qiniu，契约 §2.9.3）。
     *
     * 这条守的是 Task 5 与 Task 6 之间的接缝：Task 5 把 `driverName()` 写进 `files.storage`，
     * Task 6 再把它交回 `diskFor()` 去删物理文件。任何一边改了拼写（比如照契约 §2.9.1 笔误的
     * oss/cos 写），历史文件就再也删不掉了——那种失败还很隐蔽：接口照样返回成功。
     */
    public function test_driver_name_set_is_closed_over_disk_for(): void
    {
        $options = json_decode(
            (string) Db::table('system_configs')->where('config_key', 'storage_driver')->value('config_options'),
            true
        );
        // assertEqualsCanonicalizing 而非 assertSame：MySQL 的 json 列在落盘时按键长再按字典序
        // 重排对象成员（本例会变成 local/qiniu/aliyun/tencent），与写入 SQL 字面量的顺序无关；
        // 这里要守的是「驱动名闭集」这条不变量，不是 MySQL 内部存储顺序这个无关细节。
        $this->assertEqualsCanonicalizing(['local', 'aliyun', 'tencent', 'qiniu'], array_keys((array) $options), '契约 §2.9.3 的驱动名');

        foreach (array_keys((array) $options) as $name) {
            try {
                $this->manager()->diskFor((string) $name);
            } catch (BusinessException $e) {
                // 凭据没填时抛「配置不全」是允许的；抛「不支持的驱动」说明这个名字 diskFor() 根本不认
                $this->assertSame(
                    lang('business.storage_config_incomplete'),
                    $e->getMessage(),
                    "diskFor('{$name}') 不认识这个驱动名，但 storage_driver 的选项里有它"
                );
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
