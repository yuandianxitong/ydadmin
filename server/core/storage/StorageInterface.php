<?php

declare(strict_types=1);

namespace core\storage;

/**
 * 存储驱动接口。所有路径参数都是「相对存储根目录」的相对路径：正斜杠分隔、不含前导 `/`、
 * 不含 `storage/` 前缀（例如 `uploads/images/20260913/68c0b1f2a1b2c.png`）。
 *
 * 与 TP8 的 `upload()/getDriver()` 形状不同：落盘与取 URL 拆成两个方法（上传流程要先落盘、
 * 再算 URL、再写 files 记录），驱动自己的名字由 `StorageManager::driverName()` 给。
 */
interface StorageInterface
{
    /**
     * 把本地临时文件落到目标相对路径，目标目录不存在时自动创建。
     *
     * 🔴 成功之后源临时文件已被移走（本地驱动 rename）或删除（云驱动上传后清理），
     * 调用方必须在调用本方法**之前**取 `getSize()`，之后再取会因 stat 失败抛异常。
     *
     * @param string $localTmpPath       本地已存在的临时文件绝对路径（如上传后的 tmp_name）
     * @param string $targetRelativePath 目标相对路径
     * @throws \RuntimeException 源文件不存在，或本地写入失败
     * @throws \core\exception\BusinessException 云存储配置不全或上传失败
     */
    public function put(string $localTmpPath, string $targetRelativePath): void;

    /** 返回可直接访问的 URL：本地驱动是相对路径 `/storage/{relativePath}`，云驱动是绝对 URL。 */
    public function getUrl(string $relativePath): string;

    /**
     * 删除文件。文件本来就不存在时返回 `false`，不抛异常（幂等）；
     * 权限、IO、网络等真实失败照常抛异常，由调用方决定记日志还是中断。
     */
    public function delete(string $relativePath): bool;

    /** 文件是否存在。任何判断失败（网络、权限）一律返回 `false`，不抛异常。 */
    public function exists(string $relativePath): bool;
}
