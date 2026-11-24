<?php

declare(strict_types=1);

namespace core\plugin;

use core\exception\BusinessException;

/**
 * 宿主 import map 提供的 specifier 集合，按 host 分别列出。
 *
 * 只比 hostSdk 版本区间不够：宿主删掉一个模块但版本号仍满足区间时，安装照过、运行时裸说明符解析失败。
 * 各端清单本来就不同（tenant、platform、pc 的模块与前缀各异），而 host_sdk 只有一个版本号，所以清单必须按 host 分开。
 * 与各端 build/host-sdk.ts 的一致性由 assertConsistent() 在 plugin:pack 时保证；安装机上可能没有前端源码，只能读宿主画像。
 */
final class HostSdkSpecifiers
{
    /**
     * 某个 host 的宿主已知 specifier 全集。
     *
     * $hostSdkVersion 传完整版本号，这里只取 major 段拼前缀（specifier 里只带 major）。
     *
     * @return list<string>
     */
    public static function for(string $host, string $hostSdkVersion): array
    {
        $frontends = PluginHostProfiles::get()->publishableFrontends();
        if (!isset($frontends[$host])) {
            throw new BusinessException("未知的宿主：{$host}", 400);
        }

        $major  = self::major($hostSdkVersion);
        $prefix = $frontends[$host]['prefix'];

        return [
            ...$frontends[$host]['vendors'],
            ...array_map(
                static fn (string $mod): string => "{$prefix}@{$major}/{$mod}",
                $frontends[$host]['modules']
            ),
        ];
    }

    /**
     * 本清单与构建器那几份 TS 清单一致，否则抛。
     *
     * 漂移的症状分两种，都很差：PHP 这边多了一项（TS 已删）→ 闸放行一份引用了
     * 已不存在模块的产物，运行时裸说明符解析失败；PHP 这边少了一项（TS 新增）→
     * 刚编出来的产物装不上自己这台机器，而报错说的是「插件用了未知 specifier」。
     *
     * major 从 $root 下的 `server/config/version.php` 取而不是当前进程的 config()：比的是这一棵树里的清单。
     */
    public static function assertConsistent(string $root): void
    {
        $root = rtrim($root, '/\\');
        $hostSdkVersion = self::versionAt($root);
        $drift = [];

        foreach (PluginHostProfiles::get()->publishableFrontends() as $host => $frontend) {
            $rel      = $frontend['sdkSource'];
            $expected = self::for($host, $hostSdkVersion);
            $actual   = self::readFromBuilder($root . '/' . $rel, $frontend['prefix'], $hostSdkVersion);

            $missing = array_values(array_diff($expected, $actual));
            $extra   = array_values(array_diff($actual, $expected));

            if ($missing !== [] || $extra !== []) {
                $drift[] = sprintf(
                    '%s：PHP 有而构建器没有 [%s]；构建器有而 PHP 没有 [%s]',
                    $rel,
                    implode(', ', $missing),
                    implode(', ', $extra)
                );
            }
        }

        if ($drift !== []) {
            throw new BusinessException(
                'Host SDK specifier 清单与构建器不一致，改其中一处后必须同步另一处；'
                . 'PHP 侧的清单在已绑定宿主画像的 publishableFrontends() 里：' . implode('；', $drift),
                422
            );
        }
    }

    /**
     * 从 `<host>/build/host-sdk.ts` 里读出它实际会 external 的 specifier 集合。
     *
     * 只认那两个常量块的字面量，不求值。写法变了会读到空集合，差集非空当场报错，方向是安全的。
     *
     * @return list<string>
     */
    private static function readFromBuilder(string $path, string $prefix, string $hostSdkVersion): array
    {
        if (!is_file($path)) {
            throw new BusinessException("Host SDK 构建器清单缺失：{$path}", 500);
        }

        $raw    = (string) file_get_contents($path);
        $major  = self::major($hostSdkVersion);

        $vendors = [];
        if (preg_match('/const\s+VENDORS[^=]*=\s*\[(.*?)\n\]/s', $raw, $m)) {
            // 每个元素形如 ['@vueuse/core', 'vueuse-core']，只取第一个字符串
            preg_match_all("/\[\s*'([^']+)'/", $m[1], $found);
            $vendors = $found[1];
        }

        $modules = [];
        if (preg_match('/const\s+HOST_MODULES[^=]*=\s*\[(.*?)\]/s', $raw, $m)) {
            preg_match_all("/'([^']+)'/", $m[1], $found);
            $modules = $found[1];
        }

        return [
            ...$vendors,
            ...array_map(static fn (string $mod): string => "{$prefix}@{$major}/{$mod}", $modules),
        ];
    }

    /** 这棵树声明的 Host SDK 版本，取 `server/config/version.php` 的 host_sdk */
    private static function versionAt(string $root): string
    {
        $path = $root . '/server/config/version.php';
        if (!is_file($path)) {
            throw new BusinessException('Host SDK 版本文件缺失：server/config/version.php', 500);
        }

        $config = include $path;
        $version = is_array($config) ? ($config['host_sdk'] ?? null) : null;

        return is_string($version) ? $version : '';
    }

    /** major 段。空串或写法奇怪时回退 '1'，与阶段一固定的 major 一致 */
    private static function major(string $hostSdkVersion): string
    {
        $head = explode('.', trim($hostSdkVersion))[0];

        return $head === '' ? '1' : $head;
    }
}
