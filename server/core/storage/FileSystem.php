<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\storage;

use think\facade\Filesystem;
use core\exception\BusinessException;

class FileSystem
{
    protected string $disk;
    protected array $allowedExtensions = [];
    protected int $maxSize = 0;

    public function __construct(string $disk = 'public')
    {
        $this->disk = $disk;
    }

    /**
     * 设置允许的文件扩展名
     */
    public function allowExtensions(array $extensions): self
    {
        $this->allowedExtensions = $extensions;
        return $this;
    }

    /**
     * 设置最大文件大小
     */
    public function maxSize(int $size): self
    {
        $this->maxSize = $size;
        return $this;
    }

    /**
     * 上传文件
     */
    public function upload(\think\File $file, string $path = ''): array
    {
        // 验证文件
        $this->validateFile($file);

        // 生成文件名
        $filename = $this->generateFilename($file);

        // 确定存储路径
        $storagePath = $this->buildPath($path, $filename);

        // 存储文件
        $result = Filesystem::disk($this->disk)->putFileAs($path, $file, $filename);

        if (!$result) {
            throw new BusinessException(lang('business.upload_failed'));
        }

        return [
            'filename' => $filename,
            'path' => $storagePath,
            'url' => $this->getUrl($storagePath),
            'size' => $file->getSize(),
            'mime_type' => $file->getMime(),
            'extension' => $file->extension()
        ];
    }

    /**
     * 批量上传文件
     */
    public function uploadMultiple(array $files, string $path = ''): array
    {
        $results = [];
        foreach ($files as $file) {
            if ($file instanceof \think\File) {
                $results[] = $this->upload($file, $path);
            }
        }
        return $results;
    }

    /**
     * 删除文件
     */
    public function delete(string $path): bool
    {
        return Filesystem::disk($this->disk)->delete($path);
    }

    /**
     * 批量删除文件
     */
    public function deleteMultiple(array $paths): bool
    {
        return Filesystem::disk($this->disk)->delete($paths);
    }

    /**
     * 检查文件是否存在
     */
    public function exists(string $path): bool
    {
        return Filesystem::disk($this->disk)->exists($path);
    }

    /**
     * 获取文件内容
     */
    public function get(string $path): string
    {
        return Filesystem::disk($this->disk)->get($path);
    }

    /**
     * 写入文件内容
     */
    public function put(string $path, string $contents): bool
    {
        return Filesystem::disk($this->disk)->put($path, $contents);
    }

    /**
     * 复制文件
     */
    public function copy(string $from, string $to): bool
    {
        return Filesystem::disk($this->disk)->copy($from, $to);
    }

    /**
     * 移动文件
     */
    public function move(string $from, string $to): bool
    {
        return Filesystem::disk($this->disk)->move($from, $to);
    }

    /**
     * 获取文件URL
     */
    public function getUrl(string $path): string
    {
        return Filesystem::disk($this->disk)->url($path);
    }

    /**
     * 获取文件大小
     */
    public function size(string $path): int
    {
        return Filesystem::disk($this->disk)->size($path);
    }

    /**
     * 获取文件最后修改时间
     */
    public function lastModified(string $path): int
    {
        return Filesystem::disk($this->disk)->lastModified($path);
    }

    /**
     * 验证文件
     */
    protected function validateFile(\think\File $file): void
    {
        // 检查文件是否有效
        if (!$file->isValid()) {
            throw new BusinessException(lang('business.invalid_file'));
        }

        // 检查文件扩展名
        if (!empty($this->allowedExtensions)) {
            $extension = strtolower($file->extension());
            if (!in_array($extension, $this->allowedExtensions)) {
                throw new BusinessException(lang('business.file_type_not_allowed') . ': ' . $extension);
            }
        }

        // 检查文件大小
        if ($this->maxSize > 0 && $file->getSize() > $this->maxSize) {
            throw new BusinessException(lang('business.file_size_exceeded'));
        }
    }

    /**
     * 生成文件名
     */
    protected function generateFilename(\think\File $file): string
    {
        $extension = $file->extension();
        $hash = md5(uniqid() . microtime());
        return $hash . '.' . $extension;
    }

    /**
     * 构建存储路径
     */
    protected function buildPath(string $path, string $filename): string
    {
        $datePath = date('Y/m/d');
        return trim($path . '/' . $datePath . '/' . $filename, '/');
    }
}
