<?php
declare(strict_types=1);

namespace core\storage\driver;

use core\storage\StorageInterface;
use core\exception\BusinessException;
use think\facade\Filesystem;

class LocalDriver implements StorageInterface
{
    protected string $disk = 'public';

    public function __construct(array $config = [])
    {
        // 本地存储无需额外配置
    }

    public function upload(string $localPath, string $remotePath): string
    {
        $content = file_get_contents($localPath);
        if ($content === false) {
            throw new BusinessException(lang('business.local_read_failed'));
        }

        $result = Filesystem::disk($this->disk)->put($remotePath, $content);
        if (!$result) {
            throw new BusinessException(lang('business.local_write_failed'));
        }

        return $this->getUrl($remotePath);
    }

    public function delete(string $path): bool
    {
        // 兼容传入 /storage/ 前缀的路径
        $realPath = $this->normalizePath($path);
        $fullPath = app()->getRootPath() . 'public/storage/' . $realPath;

        if (file_exists($fullPath)) {
            return @unlink($fullPath);
        }

        return true;
    }

    public function exists(string $path): bool
    {
        $realPath = $this->normalizePath($path);
        $fullPath = app()->getRootPath() . 'public/storage/' . $realPath;

        return file_exists($fullPath);
    }

    public function getUrl(string $path): string
    {
        $realPath = $this->normalizePath($path);
        return '/storage/' . $realPath;
    }

    public function getDriver(): string
    {
        return 'local';
    }

    /**
     * 标准化路径，去掉 /storage/ 前缀
     */
    protected function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }

        return $path;
    }
}
