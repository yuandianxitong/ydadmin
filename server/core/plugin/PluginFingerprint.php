<?php

declare(strict_types=1);

namespace core\plugin;

use core\plugin\contracts\PluginStore;

/**
 * 插件生效指纹：worker 按实际装载成功的插件算，saga 按 DB 目标态算，两者相等即 reload 已生效。
 *
 * 两边必须调同一个 of()，算法分叉会让生效判定恒真或恒假。
 */
final class PluginFingerprint
{
    /**
     * 指纹 = sha1(宿主版本 | 排序后的 code:version 串)。
     *
     * 先 ksort()：装载顺序取决于文件系统遍历顺序，不排序会让同一批插件在不同 worker 间算出不同指纹。
     *
     * @param array<string, string> $codeToVersion
     */
    public static function of(array $codeToVersion): string
    {
        ksort($codeToVersion);
        $parts = [];
        foreach ($codeToVersion as $code => $version) {
            $parts[] = $code . ':' . $version;
        }

        return sha1((string) config('version.version') . '|' . implode(',', $parts));
    }

    /**
     * saga 侧的期望值：按 DB 目标态（`status=ENABLED`）算。
     *
     * 必须与 worker 侧的 PluginManager::loadedVersions() 是不同的数据源：一个是“应该生效什么”，
     * 一个是“实际生效了什么”，两者的差异就是装载失败的半截插件。
     */
    public static function fromDatabase(PluginStore $store): string
    {
        $map = [];
        foreach ($store->enabledRows() as $row) {
            $map[(string) $row['code']] = (string) ($row['version'] ?? '0.0.0');
        }

        return self::of($map);
    }

    /**
     * 只用于 /health 的 HTTP 响应。/health 公开且无需登录，插件 code 和版本号可枚举，
     * 原始指纹能被离线穷举出“装了哪些插件、什么版本”。HMAC 是确定性的，所有 worker
     * 共享 app_key，等值比较照常成立。内部存储和生效判定一律用原始指纹，不经过这里。
     */
    public static function forHttpResponse(string $rawFingerprint): string
    {
        return hash_hmac('sha256', $rawFingerprint, (string) config('app.app_key'));
    }
}
