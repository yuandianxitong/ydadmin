<?php

declare(strict_types=1);

namespace core\plugin;

use core\plugin\contracts\PluginStore;
use support\Container;
use support\Response;

/**
 * 从 plugins/<code>/<file> 读插件图标并返回 Response。
 *
 * - $code 必须是合法 plugin code，$file 必须是单段文件名且扩展名在白名单，防路径穿越
 * - 只服务 manifest 声明过的文件：先查已启用的 DB 行，查不到回退磁盘 manifest（未安装的本地插件也能显示图标）
 * - 文件必须 is_file 且不超过 1MB
 *
 * 任何校验失败一律 404，不区分原因，避免信息泄露。
 */
class PluginIconResolver
{
    /** 装修图标相对插件根。PHP 原样服务，不进 Vite hash。 */
    public const DIY_ICON_DIR = 'src/tenant/assets/diy';

    /** 旧包 / 未迁移磁盘副本。inspect 与读取都回退认它。 */
    public const DIY_ICON_DIR_LEGACY = 'assets/diy';

    private const CODE_REGEX = PluginCodePattern::MANIFEST;
    private const FILE_REGEX = PluginCodePattern::ICON_FILE;
    private const MAX_BYTES = 1024 * 1024;

    private const MIME = [
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'svg'  => 'image/svg+xml',
        'webp' => 'image/webp',
        'ico'  => 'image/x-icon',
        'gif'  => 'image/gif',
    ];

    private string $pluginsDir;

    private PluginStore $store;

    /**
     * @param string|null $pluginsDir 缺省用生产插件根目录，仅测试传入临时目录
     */
    public function __construct(private readonly PluginScanner $scanner, ?string $pluginsDir = null, ?PluginStore $store = null)
    {
        $this->store = $store ?? Container::get(PluginStore::class);
        $this->pluginsDir = rtrim($pluginsDir ?? PluginPaths::root(), '/\\');
    }

    public function resolve(string $code, string $file): Response
    {
        if (!preg_match(self::CODE_REGEX, $code) || !preg_match(self::FILE_REGEX, $file)) {
            return $this->notFound();
        }

        // 已申明的 icon 文件名：优先取已启用的 DB 行，没有则回退到磁盘 manifest
        // （让 git 一起带的本地插件在"未安装"虚拟态也能显示图标）。
        // 两路都只认 manifest 声明过的文件 → 防止枚举插件目录里其它文件。
        $declared = $this->declaredIcon($code);
        if ($declared === '' || strcasecmp($declared, $file) !== 0) {
            return $this->notFound();
        }

        return $this->serveFile($this->pluginsDir . '/' . $code . '/' . $file, $file);
    }

    /**
     * DIY widget 图标服务：文件在 src/tenant/assets/diy/（旧包回退 assets/diy/），
     * 只认 manifest diy_widgets[].icon 声明过的文件名（防枚举，同 resolve 策略）。
     * 对外 URL 仍是 /plugin-icon/<code>/assets/diy/<file>，不跟磁盘路径绑死。
     */
    public function resolveDiyWidgetIcon(string $code, string $file): Response
    {
        if (!preg_match(self::CODE_REGEX, $code) || !preg_match(self::FILE_REGEX, $file)) {
            return $this->notFound();
        }
        if (!in_array(strtolower($file), $this->declaredDiyWidgetIcons($code), true)) {
            return $this->notFound();
        }

        $pluginRoot = $this->pluginsDir . '/' . $code;

        return $this->serveFile(self::diyIconDiskPath($pluginRoot, $file), $file);
    }

    /**
     * 优先新约定，没有再回退旧顶层 assets/diy/。
     */
    public static function diyIconDiskPath(string $pluginRoot, string $file): string
    {
        $root = rtrim($pluginRoot, '/\\');
        $current = $root . '/' . self::DIY_ICON_DIR . '/' . $file;
        if (is_file($current)) {
            return $current;
        }

        return $root . '/' . self::DIY_ICON_DIR_LEGACY . '/' . $file;
    }

    private function serveFile(string $path, string $file): Response
    {
        if (!is_file($path)) {
            return $this->notFound();
        }
        $size = filesize($path);
        if ($size === false || $size > self::MAX_BYTES) {
            return $this->notFound();
        }

        $content = (string) file_get_contents($path);
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mime = self::MIME[$ext] ?? 'application/octet-stream';
        $etag = '"' . hash('sha256', $content) . '"';

        return new Response(200, [
            'Content-Type'  => $mime,
            'Cache-Control' => 'public, max-age=86400, immutable',
            'ETag'          => $etag,
        ], $content);
    }

    /**
     * 磁盘 manifest 中 diy_widgets[].icon 声明的文件名集合（小写）。
     * 只走磁盘扫描：widget 图标文件本身必须在磁盘才可服务，无 DB 独立字段可回退。
     * @return array<int, string>
     */
    private function declaredDiyWidgetIcons(string $code): array
    {
        $manifests = $this->scanner->scan();
        $manifest = $manifests[$code] ?? null;
        if (!is_array($manifest)) {
            return [];
        }
        $out = [];
        foreach ((array) ($manifest['diy_widgets'] ?? []) as $w) {
            $icon = is_array($w) ? ($w['icon'] ?? null) : null;
            if (is_string($icon) && $icon !== '' && !preg_match('#^https?://#i', $icon) && preg_match(self::FILE_REGEX, $icon)) {
                $out[] = strtolower($icon);
            }
        }
        return $out;
    }

    /**
     * 解析某插件 manifest 声明的 icon 文件名。
     * - 已启用且未软删的 DB 行：用其 icon 字段（已安装插件）
     * - 否则回退磁盘扫描：git 一起带的本地插件即使未登记/未安装也能拿到声明的 icon
     * - 找不到返回 ''
     */
    private function declaredIcon(string $code): string
    {
        $icon = $this->store->enabledIcon($code);
        if ($icon !== null) {
            return $icon;
        }

        $manifests = $this->scanner->scan();
        $manifest = $manifests[$code] ?? null;
        return is_array($manifest) ? (string) ($manifest['icon'] ?? '') : '';
    }

    private function notFound(): Response
    {
        return new Response(404, ['Content-Type' => 'text/html'], '');
    }

    /**
     * 把 manifest 的 icon 值转为浏览器可拉的 URL。
     * - 空 → 返回 ''（前端走渐变色兜底字母）
     * - http(s):// 开头 → 透传（暂未启用但保留扩展点）
     * - 简单文件名（icon.png 等） → 拼成 /plugin-icon/<code>/<file>
     * - 其它非法值（含 /、..） → 返回 ''
     */
    public static function iconUrl(string $code, string $iconValue): string
    {
        if ($iconValue === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $iconValue)) {
            return $iconValue;
        }
        if (!preg_match(self::FILE_REGEX, $iconValue) || !preg_match(self::CODE_REGEX, $code)) {
            return '';
        }
        return '/plugin-icon/' . $code . '/' . $iconValue;
    }

    /**
     * 把 diy_widgets[].icon 值转为浏览器可拉的 URL（URL 通道名仍是 assets/diy）。
     * 规则同 iconUrl：空/非法 → ''；http(s) 透传；文件名 → /plugin-icon/<code>/assets/diy/<file>
     */
    public static function diyWidgetIconUrl(string $code, string $iconValue): string
    {
        if ($iconValue === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $iconValue)) {
            return $iconValue;
        }
        if (!preg_match(self::FILE_REGEX, $iconValue) || !preg_match(self::CODE_REGEX, $code)) {
            return '';
        }
        return '/plugin-icon/' . $code . '/assets/diy/' . $iconValue;
    }
}
