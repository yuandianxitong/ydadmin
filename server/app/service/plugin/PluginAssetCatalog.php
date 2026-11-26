<?php

declare(strict_types=1);

namespace app\service\plugin;

use core\plugin\PluginAssetPublisher;
use core\plugin\PluginPaths;
use core\plugin\PluginStatus;
use support\Db;

/**
 * 后台插件页清单：已启用且自带 dist/admin/manifest.json 的插件，及其页面到 chunk 的映射和样式文件。
 *
 * 前端据此把菜单的 component（plugins/<code>/...）解析成产物 URL，并在加载页面前挂上 styles 里的样式表。
 * base 带 token，来自 {@see PluginAssetPublisher::baseUrl()}，与发布目录一致；pages 和 styles 的值都相对 base。
 */
final class PluginAssetCatalog
{
    /** @return list<array{code: string, base: string, pages: array<string, string>, styles: list<string>}> */
    public function adminEntries(): array
    {
        $codes = Db::table('plugins')
            ->where('status', PluginStatus::ENABLED)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->pluck('code')
            ->all();

        $entries = [];
        foreach ($codes as $code) {
            $code = (string) $code;
            $manifest = $this->readManifest($code);
            if ($manifest === null) {
                continue;
            }
            $entries[] = [
                'code' => $code,
                'base' => PluginAssetPublisher::baseUrl('admin', $code),
                'pages' => $manifest['pages'],
                'styles' => $manifest['styles'],
            ];
        }

        return $entries;
    }

    /** @return array{pages: array<string, string>, styles: list<string>}|null null = 没有可读的清单 */
    private function readManifest(string $code): ?array
    {
        $file = PluginPaths::dir($code) . '/dist/admin/manifest.json';
        if (!is_file($file) || !is_readable($file)) {
            return null;
        }
        $raw = file_get_contents($file);
        if ($raw === false) {
            return null;
        }
        try {
            $manifest = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        $pages = is_array($manifest) ? ($manifest['pages'] ?? null) : null;
        if (!is_array($pages)) {
            return null;
        }

        $clean = [];
        foreach ($pages as $key => $path) {
            if (is_string($path)) {
                $clean[(string) $key] = $path;
            }
        }

        // 清单没有 styles 或不是数组时按没有样式处理，页面照常列出
        $styles = [];
        foreach (is_array($manifest['styles'] ?? null) ? $manifest['styles'] : [] as $path) {
            if (is_string($path) && $path !== '') {
                $styles[] = $path;
            }
        }

        return ['pages' => $clean, 'styles' => $styles];
    }
}
