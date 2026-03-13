<?php
declare(strict_types=1);

namespace core\storage;

/**
 * 统一存储驱动接口
 */
interface StorageInterface
{
    /**
     * 上传文件
     * @param string $localPath 本地文件路径
     * @param string $remotePath 远程存储路径
     * @return string 文件访问URL
     */
    public function upload(string $localPath, string $remotePath): string;

    /**
     * 删除文件
     * @param string $path 文件路径
     * @return bool
     */
    public function delete(string $path): bool;

    /**
     * 检查文件是否存在
     * @param string $path 文件路径
     * @return bool
     */
    public function exists(string $path): bool;

    /**
     * 获取文件访问URL
     * @param string $path 文件路径
     * @return string
     */
    public function getUrl(string $path): string;

    /**
     * 获取驱动标识
     * @return string
     */
    public function getDriver(): string;
}
