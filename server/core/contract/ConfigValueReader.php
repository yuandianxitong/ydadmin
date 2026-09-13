<?php

declare(strict_types=1);

namespace core\contract;

/**
 * 取单个系统配置项的值。
 *
 * `core/` 需要读系统配置时依赖这个接口，而不是 `use app\repository\system\SystemConfigRepository`：
 * 核心依赖应用层仓储，会把仓库其他地方机械强制的分层（Controller → Service → Repository）倒过来。
 * 实现由 `config/container.php` 绑定，`composer check:context` 规则六守着这条方向。
 *
 * 缓存语义属于实现：当前的实现（`app\repository\system\SystemConfigRepository`）自带 1 小时缓存，
 * 写配置的路径会主动失效它。所以调用方每次现读即可，**不要**在自己这边再缓存一层——常驻内存下
 * 缓存了就看不到管理员刚改的配置。
 */
interface ConfigValueReader
{
    public function getConfigValue(string $key, mixed $default = null): mixed;
}
