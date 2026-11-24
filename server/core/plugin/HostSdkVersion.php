<?php

declare(strict_types=1);

namespace core\plugin;

use core\exception\BusinessException;

/**
 * Host SDK 版本副本（server/config/version.php 与宿主画像列出的各前端宿主的 host-sdk/version.ts）的一致性校验。
 *
 * 后端那份供安装期校验 manifest.hostSdk 区间，前端各份由构建器烙进产物；漂移的症状是本机编出的插件装不上本机，
 * 报错却说版本不兼容。PluginPacker::pack() 只在有前端产物时于编译前调用本类。
 */
final class HostSdkVersion
{
    /** 非宿主的那一份副本，相对仓库根 */
    private const PHP_SOURCE = 'server/config/version.php';

    public static function assertConsistent(string $root): void
    {
        $root = rtrim($root, '/\\');
        $found = [];

        foreach (self::sources() as $rel => $kind) {
            $found[$rel] = self::read($root, $rel, $kind);
        }

        if (count(array_unique($found)) === 1) {
            return;
        }

        $detail = [];
        foreach ($found as $rel => $version) {
            $detail[] = "{$rel} = {$version}";
        }

        throw new BusinessException(
            'Host SDK 版本号四处不一致，改其中一处后必须同步另外三处：' . implode('；', $detail),
            422
        );
    }

    /**
     * 各副本的位置，相对仓库根：config 那份加上宿主画像里每个前端宿主的 version.ts。
     *
     * @return array<string, 'php'|'ts'>
     */
    private static function sources(): array
    {
        $sources = [self::PHP_SOURCE => 'php'];
        foreach (array_keys(PluginHostProfiles::get()->publishableFrontends()) as $host) {
            $sources["{$host}/src/host-sdk/version.ts"] = 'ts';
        }

        return $sources;
    }

    private static function read(string $root, string $rel, string $kind): string
    {
        $path = rtrim($root, '/\\') . '/' . $rel;
        if (!is_file($path)) {
            throw new BusinessException("Host SDK 版本文件缺失：{$rel}", 500);
        }

        if ($kind === 'php') {
            $config = include $path;
            $version = is_array($config) ? ($config['host_sdk'] ?? null) : null;
            if (!is_string($version) || $version === '') {
                throw new BusinessException("{$rel} 未定义 host_sdk", 500);
            }

            return $version;
        }

        $raw = (string) file_get_contents($path);
        if (!preg_match('/HOST_SDK_VERSION\s*=\s*[\'"]([^\'"]+)[\'"]/', $raw, $m)) {
            throw new BusinessException("{$rel} 未定义 HOST_SDK_VERSION", 500);
        }

        return $m[1];
    }
}
