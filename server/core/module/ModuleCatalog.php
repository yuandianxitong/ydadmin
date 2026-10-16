<?php

declare(strict_types=1);

namespace core\module;

use core\support\PathGuard;

final class ModuleCatalog
{
    /** @return array{modules: list<array<string, mixed>>, errors: list<array{path: string, message: string}>} */
    public function inspect(string $serverRoot, string $repoRoot, bool $checkFiles = false): array
    {
        $modules = $errors = $ids = $owners = [];
        foreach (glob($serverRoot . '/modules/*/*.json') ?: [] as $file) {
            $display = 'server/' . substr($file, strlen($serverRoot) + 1);
            try {
                PathGuard::within($serverRoot, $file);
                $size = @filesize($file);
                if ($size === false || $size > 1024 * 1024) {
                    throw new \RuntimeException('模块描述超过大小限制');
                }
                $content = @file_get_contents($file);
                if ($content === false) {
                    throw new \RuntimeException('模块描述无法读取');
                }
                $data = json_decode($content, true, 64, JSON_THROW_ON_ERROR);
                if (!is_array($data)) {
                    throw new \RuntimeException('模块描述必须是对象');
                }
                $descriptor = new ModuleDescriptor($data);
                $id = $data['id'];
                if (isset($ids[$id]) || $id !== basename(dirname($file)) . '.' . basename($file, '.json')
                    || $data['module'] !== basename(dirname($file))) {
                    throw new \RuntimeException('模块标识重复或与描述路径不一致');
                }
                foreach ($data['artifacts'] as $path) {
                    if (isset($owners[$path])) {
                        throw new \RuntimeException('模块产物路径冲突：' . $path);
                    }
                    $root = str_starts_with($path, 'server/') ? $serverRoot : $repoRoot;
                    $absolute = str_starts_with($path, 'server/') ? $serverRoot . '/' . substr($path, 7) : $repoRoot . '/' . $path;
                    PathGuard::within($root, $absolute);
                    if ($checkFiles && !is_file($absolute)) {
                        throw new \RuntimeException('缺少模块产物：' . $path);
                    }
                }
                $ids[$id] = true;
                foreach ($data['artifacts'] as $path) {
                    $owners[$path] = $id;
                }
                $modules[] = $descriptor->data;
            } catch (\Throwable $e) {
                // 路径只使用相对展示形式，底层 IO 错误不带出绝对目录。
                $errors[] = ['path' => $display, 'message' => $e instanceof \JsonException ? '模块描述 JSON 不合法' : str_replace([$serverRoot, $repoRoot], ['server', '.'], $e->getMessage())];
            }
        }
        return ['modules' => $modules, 'errors' => $errors];
    }
}
