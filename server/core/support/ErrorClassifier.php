<?php

declare(strict_types=1);

namespace core\support;

/**
 * 异常 → 可上报的错误标识（M4.9c Task 1，提取既有两份重复实现）。
 *
 * 🔴 数据边界（安全）：`classify()` 的返回值**只**含异常短类名与数值 code，
 * **不含** message/trace。这个值会被 `MarketplaceAuditClient` 原样发给上游
 * Site，而原始异常消息可能含文件路径、SQL 片段、租户数据——带上去就是一次
 * 信息泄露。判据来自既有调用点注释（见
 * `app\service\marketplace\MarketplaceInstallService::install()` 的 catch
 * 块，`MarketplaceInstallService.php:220`：「error_code 仅含异常短类名 + 数值
 * code，不含 message/trace」）。
 *
 * 提取原因：同一份实现原先在 `MarketplaceInstallService` 与
 * `MarketplaceUpgradeService` 里各写一份私有 `classifyError()`，逐字相同。
 * M4.9c 的 saga 终态事件发布点是第三个使用者——再复制一份就是三份，把判据
 * 分散到三处也意味着以后改一处安全规则要同步改三处、漏改一处就是一次
 * 潜在的数据泄露回归。提到这里统一维护。
 */
final class ErrorClassifier
{
    public static function classify(\Throwable $e): string
    {
        return (new \ReflectionClass($e))->getShortName() . '_' . (int) $e->getCode();
    }
}
