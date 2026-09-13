<?php

declare(strict_types=1);

namespace app\service\system;

use core\base\Service;
use core\context\RequestContext;
use core\exception\BusinessException;
use core\helper\UploadExtensionGuard;
use core\storage\StorageManager;
use DI\Attribute\Inject;
use support\Log;
use Webman\Http\UploadFile;

/**
 * 上传（契约 §2.9.2、spec §6.5）。控制器只负责把 $request->file('file') 递进来，
 * 校验失败一律抛 BusinessException（HTTP 200 + code 400，与 TP8 的 $this->error() 同形）。
 *
 * 流程：
 *   1. 文件存在且有效；
 *   2. 图片端点：MIME 白名单（jpeg/png/gif/webp，契约 §2.9.2）；
 *   3. 扩展名：危险扩展名黑名单（压过配置）→ 图片端点再过图片白名单 → 过 storage_upload_allowed_ext；
 *   4. 大小：图片读 storage_image_max_size，文件读 storage_upload_max_size（spec §1.1 第 8 条，
 *      不再是 TP8 写死的 2MB / 10MB）；
 *   5. 落盘 uploads/{images|files}/{Ymd}/{32位随机名}.{ext}；
 *   6. 写 files 行，失败只 Log::warning；
 *   7. 返回 {url, path, filename, size, storage}。
 *
 * 3、4 都在落盘之前——红线 Test15 要求被拒绝的上传不留任何文件、不留任何行。
 *
 * 容器单例：实例属性只有注入的无状态依赖，不存请求态。
 */
class UploadService extends Service
{
    /** files.group 的取值，同时也是存储子目录名 */
    public const GROUP_IMAGES = 'images';

    public const GROUP_FILES = 'files';

    /** upload/image 的 MIME 白名单（契约 §2.9.2 逐字） */
    private const ALLOWED_IMAGE_MIME = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /** 配置读不出有效值时的回落，与 Task 3 的种子默认值一致 */
    private const DEFAULT_IMAGE_MAX_MB = 5;

    private const DEFAULT_FILE_MAX_MB = 10;

    private const DEFAULT_ALLOWED_EXT = 'jpg,jpeg,png,gif,svg,webp,bmp,doc,docx,xls,xlsx,ppt,pptx,pdf,zip,rar,txt,csv';

    #[Inject]
    protected SystemConfigService $systemConfigService;

    #[Inject]
    protected FileService $fileService;

    #[Inject]
    protected StorageManager $storageManager;

    /**
     * 图片上传。
     *
     * @param mixed $file Webman\Http\Request::file('file') 的返回值（UploadFile|array|null）
     * @return array{url: string, path: string, filename: string, size: int, storage: string}
     */
    public function uploadImage(mixed $file): array
    {
        $file = $this->assertUploadable($file);

        if (!in_array((string) $file->getUploadMimeType(), self::ALLOWED_IMAGE_MIME, true)) {
            throw new BusinessException(lang('business.upload_image_only'));
        }
        $this->assertExtensionAllowed($file->getUploadExtension(), true);
        $this->assertSizeWithin($file, 'storage_image_max_size', self::DEFAULT_IMAGE_MAX_MB, 'business.image_size_exceeded');

        return $this->store($file, self::GROUP_IMAGES);
    }

    /**
     * 普通文件上传：不限 MIME 类型，但扩展名同样过黑名单与配置白名单。
     *
     * @param mixed $file Webman\Http\Request::file('file') 的返回值（UploadFile|array|null）
     * @return array{url: string, path: string, filename: string, size: int, storage: string}
     */
    public function uploadFile(mixed $file): array
    {
        $file = $this->assertUploadable($file);

        $this->assertExtensionAllowed($file->getUploadExtension(), false);
        $this->assertSizeWithin($file, 'storage_upload_max_size', self::DEFAULT_FILE_MAX_MB, 'business.file_size_exceeded');

        return $this->store($file, self::GROUP_FILES);
    }

    /** 请求里确实带了一个上传成功的文件。 */
    private function assertUploadable(mixed $file): UploadFile
    {
        if (!$file instanceof UploadFile || !$file->isValid()) {
            throw new BusinessException(lang('business.please_select_upload'));
        }

        return $file;
    }

    /**
     * 扩展名三道闸。顺序固定：先黑名单（它压过配置，spec §1.1 第 8 条），
     * 再图片白名单，最后才是 storage_upload_allowed_ext 配置白名单。
     * 空扩展名直接拒绝：落盘成无扩展名文件对同源静态直出目录不安全，也无从判定类型。
     */
    private function assertExtensionAllowed(string $extension, bool $imageOnly): void
    {
        $extension = strtolower($extension);
        if ($extension === '' || UploadExtensionGuard::isDangerousExtension($extension)) {
            throw new BusinessException(lang('business.file_type_not_allowed'));
        }
        if ($imageOnly && !UploadExtensionGuard::isAllowedImageExtension($extension)) {
            throw new BusinessException(lang('business.file_type_not_allowed'));
        }
        if (!in_array($extension, $this->allowedExtensions(), true)) {
            throw new BusinessException(lang('business.file_type_not_allowed'));
        }
    }

    /** 大小上限（MB）读配置，超限抛对应文案。 */
    private function assertSizeWithin(UploadFile $file, string $configKey, int $defaultMb, string $langKey): void
    {
        $maxMb = $this->maxMegabytes($configKey, $defaultMb);
        if ((int) $file->getSize() > $maxMb * 1024 * 1024) {
            throw new BusinessException(lang($langKey, ['size' => $maxMb]));
        }
    }

    /**
     * 落盘 + 入库 + 组装响应。
     *
     * @return array{url: string, path: string, filename: string, size: int, storage: string}
     */
    private function store(UploadFile $file, string $group): array
    {
        // 🔴 $size 必须在 put() 之前捕获：LocalDriver::put() 优先 rename() 把临时文件整体移走，
        // 而 UploadFile 继承 SplFileInfo，getSize() 每次都重新 stat 底层文件（不缓存），
        // 源文件没了就抛 RuntimeException。put() 之后绝不能再碰 $file 的任何文件系统方法。
        $size = (int) $file->getSize();
        $extension = strtolower($file->getUploadExtension());
        $originalName = (string) $file->getUploadName();
        $mimeType = (string) $file->getUploadMimeType();
        $localTmpPath = (string) $file->getPathname();

        // 契约写的是 {uniqid}，即「一个唯一名」。这里用密码学随机名而不是 uniqid()：
        // uniqid() 基于时间戳、可预测，而 /storage 是同源无鉴权静态直出目录，
        // 文件名可预测就等于别人的上传可枚举。路径形状不变。
        $filename = date('Ymd') . '/' . bin2hex(random_bytes(16)) . '.' . $extension;
        $relativePath = 'uploads/' . $group . '/' . $filename;

        // diskFor($driver) 而不是 disk()：disk() 会自己再读一次 storage_driver，
        // 常驻内存下配置随时可能被另一个请求改掉，两次读之间不保证一致（Task 7 ruling 2）。
        // 落库的 storage 列与实际存字节的驱动必须来自同一次读取，否则 FileService::deleteFile()
        // 按这一列回删物理文件时就可能找错驱动。
        $driver = $this->currentDriver();
        $disk = $this->storageManager->diskFor($driver);
        $disk->put($localTmpPath, $relativePath);
        $url = $disk->getUrl($relativePath);

        $this->recordUpload([
            'name'      => $originalName,
            'path'      => $relativePath,
            'url'       => $url,
            'mime_type' => $mimeType,
            'extension' => $extension,
            'size'      => $size,
            'group'     => $group,
            'upload_by' => RequestContext::actingUser(),
            'storage'   => $driver,
        ]);

        return [
            'url'  => $url,
            'path' => $relativePath,
            // 刻意的不对称（契约 §2.9.2）：files 组返回原始文件名，images 组返回生成的文件名
            'filename' => $group === self::GROUP_FILES ? $originalName : $filename,
            'size'     => $size,
            'storage'  => $driver,
        ];
    }

    /**
     * 写 files 行。失败只记日志，绝不影响上传成功响应（契约 §2.9.2）——
     * 文件已经真的落盘了，为了一条索引记录把成功翻成失败，只会让前端以为要重传。
     *
     * @param array<string, mixed> $data
     */
    private function recordUpload(array $data): void
    {
        try {
            $this->fileService->recordFile($data);
        } catch (\Throwable $e) {
            Log::warning('上传文件入库失败', [
                'path'  => $data['path'] ?? '',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 当前存储驱动名（local|aliyun|tencent|qiniu），落库与响应共用同一次读取。
     *
     * 取自 StorageManager::driverName()，不自己再读一遍 storage_driver 配置：
     * Task 3 提供这个方法就是为了写 files.storage，而且「记进 files 行的驱动名」必须
     * 与「disk() 这次实际构造的驱动」是同一个——这一列日后是 FileService::deleteFile()
     * 解析磁盘的唯一依据（契约 §2.9.1），记错就等于删不掉。
     */
    private function currentDriver(): string
    {
        $driver = trim($this->storageManager->driverName());

        return $driver !== '' ? $driver : 'local';
    }

    /** 大小上限（MB）。配置缺失、非数字或 <= 0 时回落到种子默认值，不让一个被清空的配置项关掉上传。 */
    private function maxMegabytes(string $configKey, int $default): int
    {
        $configured = (int) $this->systemConfigService->getConfigValue($configKey, $default);

        return $configured > 0 ? $configured : $default;
    }

    /**
     * storage_upload_allowed_ext 解析成小写扩展名列表。解析后为空时回落到种子默认值。
     * 注意这只是「配置白名单」，危险扩展名黑名单在它之前已经拦过一道，不受本方法影响。
     *
     * @return list<string>
     */
    private function allowedExtensions(): array
    {
        $raw = trim((string) $this->systemConfigService->getConfigValue('storage_upload_allowed_ext', ''));
        if ($raw === '') {
            $raw = self::DEFAULT_ALLOWED_EXT;
        }
        $extensions = array_values(array_filter(
            array_map(static fn (string $item): string => strtolower(trim($item)), explode(',', $raw)),
            static fn (string $item): bool => $item !== ''
        ));

        return $extensions !== [] ? $extensions : explode(',', self::DEFAULT_ALLOWED_EXT);
    }
}
