<?php

declare(strict_types=1);

namespace core\storage\driver;

use core\storage\StorageInterface;
use RuntimeException;

/**
 * 本地磁盘驱动。写入 `config('filesystem.disks.public.root')`（默认 `public/storage`），
 * URL 固定拼 `/storage/{relativePath}`——`config/static.php` 已开启 public 目录静态直出
 * （经 `app\middleware\StaticFile`，会加 `X-Content-Type-Options: nosniff`），不需要额外路由。
 *
 * 无状态：所有方法只读参数与 config，不往实例属性里存请求态（常驻内存纪律）。
 */
final class LocalDriver implements StorageInterface
{
    /** 对应 config/filesystem.php 里 disks 的键 */
    private const DISK = 'public';

    public function put(string $localTmpPath, string $targetRelativePath): void
    {
        if (!is_file($localTmpPath)) {
            throw new RuntimeException("源文件不存在: {$localTmpPath}");
        }

        $targetPath = $this->absolutePath($targetRelativePath);
        $dir = dirname($targetPath);
        if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new RuntimeException("目录创建失败: {$dir}");
        }

        // 优先 rename（同分区下原子，且不留残留），跨分区或权限不允许时退化成 copy + 删源文件
        if (@rename($localTmpPath, $targetPath)) {
            return;
        }
        if (!copy($localTmpPath, $targetPath)) {
            throw new RuntimeException("文件写入失败: {$targetRelativePath}");
        }
        @unlink($localTmpPath);
    }

    public function getUrl(string $relativePath): string
    {
        return '/storage/' . ltrim(str_replace('\\', '/', $relativePath), '/');
    }

    public function delete(string $relativePath): bool
    {
        $path = $this->absolutePath($relativePath);
        if (!is_file($path)) {
            return false;
        }

        return unlink($path);
    }

    public function exists(string $relativePath): bool
    {
        return is_file($this->absolutePath($relativePath));
    }

    private function absolutePath(string $relativePath): string
    {
        $root = (string) config('filesystem.disks.' . self::DISK . '.root', public_path('storage'));
        $normalized = ltrim(str_replace('\\', '/', $relativePath), '/');

        return rtrim($root, '/\\') . DIRECTORY_SEPARATOR . $normalized;
    }
}
