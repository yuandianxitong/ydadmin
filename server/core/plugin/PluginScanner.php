<?php

/* ============================================================
 * 项目：元点Saas
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace core\plugin;

use support\Log;

/**
 * 扫 plugins/*（复数，避开 webman 占用的 plugin/ 单数目录），返回所有 manifest 合法的插件。扫描与 manifest 校验只在这里实现。
 *
 * - 不碰 DB；manifest 不合法的跳过并记 warning，一个坏插件不能拖垮整个平台
 * - 点开头目录、非目录、缺 plugin.json 静默跳过
 * - plugins/ 不存在时返回空数组，不让 boot 期因 scandir() 的 warning 中断
 */
class PluginScanner
{
    public function __construct(
        private PluginManifestValidator $validator,
        private ?string $pluginsDir = null,
    ) {
        $this->pluginsDir = $pluginsDir ?? PluginPaths::root();
    }

    /**
     * @return array<string, array<string, mixed>> code => manifest
     */
    public function scan(): array
    {
        $result = [];
        foreach ($this->scanDetailed() as $code => $entry) {
            $result[$code] = $entry['manifest'];
        }
        return $result;
    }

    /**
     * 与 {@see scan()} 同一套规则，额外附带每个插件的目录绝对路径，供装载时解析 manifest 里的相对路径。
     *
     * @return array<string, array{manifest: array<string, mixed>, dir: string}> code => {manifest, dir}
     */
    public function scanDetailed(): array
    {
        if (!is_dir($this->pluginsDir)) {
            return [];
        }
        $entries = @scandir($this->pluginsDir) ?: [];
        $result = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }
            $dir = $this->pluginsDir . '/' . $entry;
            if (!is_dir($dir)) {
                continue;
            }
            $manifestFile = $dir . '/plugin.json';
            if (!is_file($manifestFile)) {
                continue;
            }
            $raw = @file_get_contents($manifestFile);
            if ($raw === false) {
                Log::warning("[plugin-scanner] cannot read {$manifestFile}");
                continue;
            }
            $manifest = json_decode($raw, true);
            if (!is_array($manifest)) {
                Log::warning("[plugin-scanner] invalid json: {$manifestFile}");
                continue;
            }
            $errors = $this->validator->validate($manifest);
            if ($errors !== []) {
                Log::warning("[plugin-scanner] manifest 校验失败 {$entry}: " . implode('; ', $errors));
                continue;
            }
            $code = (string) ($manifest['code'] ?? '');
            if ($code === '') {
                // 校验器已要求 code 必填，这里防校验规则变化，跳过也要留日志。
                Log::warning("[plugin-scanner] manifest.code 为空，已跳过：{$entry}");
                continue;
            }
            $result[$code] = ['manifest' => $manifest, 'dir' => $dir];
        }
        return $result;
    }
}
