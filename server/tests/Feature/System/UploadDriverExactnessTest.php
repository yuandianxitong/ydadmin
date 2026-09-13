<?php

declare(strict_types=1);

namespace tests\Feature\System;

use app\repository\system\SystemConfigRepository;
use app\service\system\FileService;
use app\service\system\SystemConfigService;
use app\service\system\UploadService;
use core\storage\StorageManager;
use ReflectionProperty;
use support\Container;
use support\Db;
use tests\Support\ApiTestCase;
use Webman\Http\UploadFile;

/**
 * Task 7 ruling 2：files 行落库的 storage 列必须与实际存字节的驱动是同一次读取的结果。
 *
 * UploadService::store() 曾经先用 currentDriver() 读一次 storage_driver 用来落库，又调
 * StorageManager::disk()（内部再读一次 storage_driver）取磁盘实例——常驻内存下配置随时可能
 * 被另一个请求改掉，两次读之间不保证一致，「记进 files 行的驱动」与「实际存字节的驱动」就可能
 * 对不上；FileService::deleteFile() 恰恰是按这一列回删物理文件的（诊断依据见
 * app/service/system/UploadService.php::currentDriver() 的注释）。
 *
 * 修复后只读一次：currentDriver() 读到的值直接传给 StorageManager::diskFor()，disk() 不再被调用。
 * 用一个记录调用次数的 SystemConfigRepository 代理验证这一点——LocalDriver::put() 本身不读任何
 * config()，所以「读了几次 storage_driver」就是这条不变量的直接证据：修复前 2 次
 * （currentDriver() 一次、disk() 内部 driverName() 一次），修复后 1 次。
 */
final class UploadDriverExactnessTest extends ApiTestCase
{
    public function test_upload_reads_storage_driver_config_exactly_once(): void
    {
        $countingRepository = new class () extends SystemConfigRepository {
            public int $storageDriverReads = 0;

            public function getConfigValue(string $key, mixed $default = null): mixed
            {
                if ($key === 'storage_driver') {
                    $this->storageDriverReads++;
                }

                return parent::getConfigValue($key, $default);
            }
        };

        // 本测试故意绕开容器，反射注入一个能计数的假仓储；其余依赖仍取真实的容器单例。
        $uploadService = new UploadService();
        $this->setInjected($uploadService, 'storageManager', new StorageManager($countingRepository));
        $this->setInjected($uploadService, 'systemConfigService', Container::get(SystemConfigService::class));
        $this->setInjected($uploadService, 'fileService', Container::get(FileService::class));

        $tmpPath = (string) tempnam(sys_get_temp_dir(), 'ydadmin_upload_');
        file_put_contents($tmpPath, "\x89PNG\r\n\x1a\n" . str_repeat("\x00\xff", 32));
        $file = new UploadFile($tmpPath, 'driver_exactness.png', 'image/png', UPLOAD_ERR_OK);

        try {
            $data = $uploadService->uploadImage($file);
        } finally {
            if (is_file($tmpPath)) {
                unlink($tmpPath);
            }
        }

        $this->trackStorageFile((string) $data['path']);
        $id = Db::table('files')->where('path', $data['path'])->value('id');
        if ($id !== null) {
            $this->track('files', (int) $id);
        }

        $this->assertSame('local', $data['storage']);
        $this->assertSame(
            1,
            $countingRepository->storageDriverReads,
            'storage_driver 应当只读一次：记进 files 行的驱动与实际存储字节的驱动必须来自同一次读取'
        );
    }

    /** 反射写 #[Inject] 属性。 */
    private function setInjected(object $object, string $property, mixed $value): void
    {
        $ref = new ReflectionProperty($object, $property);
        $ref->setAccessible(true);
        $ref->setValue($object, $value);
    }
}
