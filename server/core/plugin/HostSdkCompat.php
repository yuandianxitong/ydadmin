<?php

declare(strict_types=1);

namespace core\plugin;

use core\exception\BusinessException;
use ZipArchive;

/**
 * 安装期与升级 dry-run 期校验预编译产物：manifest 头部（schema / host / code）、hostSdk 区间，
 * 以及产物 external 掉的 specifier 是否都在本宿主的 import map 里。
 * 判定顺序：header → hostSdk 区间语法 → 区间是否满足 → externals 差集，任一步不过就地拒绝。
 */
final class HostSdkCompat
{
    /**
     * 本类实际把关的宿主，是「把关集」的唯一权威；发布准入
     * {@see \core\plugin\PluginAssetPublisher::publishableHosts()} 取它与 hosts() 的交集。
     *
     * @return list<string>
     */
    public static function gatedHosts(): array
    {
        return array_keys(PluginHostProfiles::get()->publishableFrontends());
    }

    /** 当前支持的 manifest schema，与产物读取侧的既有约定同一个值 */
    private const SCHEMA = 1;

    /**
     * 磁盘上已就位的插件目录：逐个检查它带产物的每个 host。
     *
     * 不检查“有前端源码却缺产物”，那由安装入口的 assertTenantDistIfNeeded()/assertPcDistIfNeeded() 负责。
     */
    public static function assertPluginCompatible(string $pluginDir, string $code, string $hostSdkVersion): void
    {
        $root = rtrim($pluginDir, '/\\');

        foreach (self::gatedHosts() as $host) {
            if (!PluginAssetPublisher::hasDist($root, $host)) {
                continue;
            }

            $manifestPath = $root . '/dist/' . $host . '/manifest.json';
            $decoded      = json_decode((string) file_get_contents($manifestPath), true);

            self::assertCompatible(is_array($decoded) ? $decoded : [], $host, $code, $hostSdkVersion);
        }
    }

    /**
     * zip 里带产物的每个 host 都过一遍兼容闸，全程不落盘。
     *
     * 不解到临时目录：那会把闭源源码树以 0755 写进 /tmp，且 /tmp 的预建路径会成为递归清理的攻击面。
     *
     * 调用方需自行保证 zip 已经 open()。本方法不 close 它。
     */
    public static function assertZipCompatible(ZipArchive $zip, string $code, string $hostSdkVersion): void
    {
        foreach (self::gatedHosts() as $host) {
            $raw = $zip->getFromName('dist/' . $host . '/manifest.json');
            if ($raw === false) {
                // 该 host 没有产物 —— 与 hasDist() 为假同义，放行
                continue;
            }

            $decoded = json_decode($raw, true);

            self::assertCompatible(is_array($decoded) ? $decoded : [], $host, $code, $hostSdkVersion);
        }
    }

    /**
     * 直接校验一份已解出的产物 manifest。安装与升级共用这一份判据，分岔会让同一个包一边能装、一边装不上。
     *
     * @param array<string, mixed> $decoded
     */
    public static function assertCompatible(
        array $decoded,
        string $host,
        string $code,
        string $hostSdkVersion,
    ): void {
        self::assertHeaderMatches($decoded, $host, $code);

        $range = $decoded['hostSdk'] ?? '';

        if (!is_string($range) || $range === '') {
            throw new BusinessException(
                "插件 {$code} 的 {$host} 产物没有声明 hostSdk 区间，无法判断兼容性。"
                . '请用当前版本的 plugin:pack 重新打包。',
                422
            );
        }

        // 语法不认识与版本不匹配必须分开报。合并成一条的后果是把
        // 「~1.0.0 / >=1.0.0 / 裸 1.0.0」全报成「要求 Host SDK ~1.0.0，当前
        // 宿主为 1.0.0，请用与本框架版本匹配的插件包」——版本明明满足，
        // 装包的人会去翻框架版本，而真因是产物用了本闸不认的写法。
        if (!str_starts_with($range, '^')) {
            throw new BusinessException(
                "插件 {$code} 的 {$host} 产物声明的 hostSdk 区间 {$range} 不是受支持的写法，"
                . '目前只认 `^x.y.z`。请用当前版本的 plugin:pack 重新打包。',
                422
            );
        }

        if (!self::satisfies($hostSdkVersion, $range)) {
            throw new BusinessException(
                "插件 {$code} 的 {$host} 产物要求 Host SDK {$range}，"
                . "当前宿主为 {$hostSdkVersion}。请用与本框架版本匹配的插件包，"
                . '或升级框架后重试。',
                422
            );
        }

        self::assertExternalsKnown($decoded, $host, $code, $hostSdkVersion);
    }

    /**
     * 产物 external 掉的每个 specifier，宿主的 import map 都得提供。
     *
     * 版本区间答不了这个问题：宿主某次重构把某个 specifier 从 SDK 拿掉、版本按
     * 「无破坏性 API 变更」走成 minor——`^1.0.0` 依旧满足，安装通过，运行时那个
     * 裸说明符解析失败，页面静默降级成 plugin-pending。而 tenant 与 platform
     * 的 SDK 集合本来就不同，同一个 host_sdk 版本号在两端代表两套契约，光看
     * 版本号连「这算 minor 吧」都拦不住。差集闸精确一个数量级，也不需要任何人
     * 正确判断 semver。
     *
     * 只查一个方向（产物要的宿主没有），不查反向：宿主多出 specifier 是纯增量，
     * 老产物用不到它完全正常，那才是版本区间该管的事。
     *
     * 缺字段一律拒，不做「缺了就跳过」的兼容：放行的代价是这道闸对所有历史
     * 产物永久失效，而它们恰恰是最可能引用已消失模块的那批。
     *
     * @param array<string, mixed> $decoded
     */
    private static function assertExternalsKnown(
        array $decoded,
        string $host,
        string $code,
        string $hostSdkVersion,
    ): void {
        $externals = $decoded['externals'] ?? null;

        if (!is_array($externals) || $externals === [] || $externals !== array_values($externals)) {
            throw new BusinessException(
                "插件 {$code} 的 {$host} 产物没有声明 externals（它 external 了哪些 "
                . 'specifier），无法判断宿主的 import map 是否都提供得出来。'
                . '请用当前版本的 plugin:pack 重新打包。',
                422
            );
        }

        foreach ($externals as $specifier) {
            if (!is_string($specifier)) {
                throw new BusinessException(
                    "插件 {$code} 的 {$host} 产物的 externals 里有非字符串项，产物已损坏。"
                    . '请用当前版本的 plugin:pack 重新打包。',
                    422
                );
            }
        }

        /** @var list<string> $externals */
        $unknown = array_values(array_diff($externals, HostSdkSpecifiers::for($host, $hostSdkVersion)));
        if ($unknown !== []) {
            throw new BusinessException(
                "插件 {$code} 的 {$host} 产物 external 了 " . count($unknown)
                . ' 个本宿主的 import map 并不提供的 specifier：' . implode('、', $unknown)
                . "。当前 Host SDK 为 {$hostSdkVersion}，装上去这些裸说明符会在运行时"
                . '解析失败，页面静默降级成 plugin-pending。请用与本框架版本匹配的插件包。',
                422
            );
        }
    }

    /**
     * manifest 头部三件套：schema / host / code。
     *
     * 手里已经有 $decoded，这三个比较是白捡的。不查的代价不是「少一道校验」：
     * 产物读取侧对头部不匹配的处置多半是 skip + warning，于是产物照样发布、
     * 清单里却没有这个插件，页面静默降级成 plugin-pending——正是本类要防的
     * 那种失败，只是从安装期挪到了读取期，而那时已经没人在看报错了。
     *
     * @param array<string, mixed> $decoded
     */
    private static function assertHeaderMatches(array $decoded, string $host, string $code): void
    {
        $actualHost   = $decoded['host']   ?? null;
        $actualCode   = $decoded['code']   ?? null;
        $actualSchema = $decoded['schema'] ?? null;

        if ($actualSchema !== self::SCHEMA) {
            throw new BusinessException(
                "插件 {$code} 的 {$host} 产物 manifest schema 为 "
                . var_export($actualSchema, true) . '，本框架只支持 ' . self::SCHEMA
                . '。请用与本框架版本匹配的插件包。',
                422
            );
        }

        if ($actualHost !== $host) {
            throw new BusinessException(
                "插件 {$code} 的 dist/{$host}/ 里放的是 "
                . var_export($actualHost, true) . ' 宿主的产物，目录与内容不符。',
                422
            );
        }

        if ($actualCode !== $code) {
            throw new BusinessException(
                "插件 {$code} 的 {$host} 产物是用 code "
                . var_export($actualCode, true) . ' 编出来的，与插件本身不符。'
                . '页面路径的键将全部对不上，装上去等于没装。',
                422
            );
        }
    }

    /**
     * 只支持 `^x.y.z` 这一种区间——构建器只会写出这一种。
     *
     * 刻意不引入完整的 semver 区间解析：多一种语法就多一条只在特定插件上
     * 才暴露的分支，而产物的区间是我们自己的构建器写的，不是第三方输入。
     */
    private static function satisfies(string $version, string $range): bool
    {
        // `^` 前缀由调用方保证（不认识的写法在那里就报了另一条消息）
        $required = substr($range, 1);
        $rv       = array_map('intval', explode('.', $required) + [0, 0, 0]);
        $cv       = array_map('intval', explode('.', $version) + [0, 0, 0]);

        if ($rv[0] !== $cv[0]) {
            return false;
        }

        // 逐段比而不是 version_compare($version, $required)：后者按字符串切片比较，
        // '1.2' 与 '1.2.0' 会判成前者小——宿主版本少写一段就被自己的闸拦住。
        for ($i = 1; $i < 3; $i++) {
            if ($cv[$i] !== $rv[$i]) {
                return $cv[$i] > $rv[$i];
            }
        }

        return true;
    }
}
