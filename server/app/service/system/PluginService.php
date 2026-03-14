<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\service\system;

use app\repository\plugin\PluginRepository;
use core\base\Service;
use core\exception\BusinessException;
use core\plugin\PluginManager;

class PluginService extends Service
{
    protected PluginRepository $pluginRepository;

    /**
     * 获取插件列表（合并文件系统扫描和数据库状态）
     */
    public function getPluginList(): array
    {
        $available = PluginManager::scanAvailablePlugins();
        $installed = $this->pluginRepository->getInstalledPlugins();

        $installedMap = [];
        foreach ($installed as $item) {
            $installedMap[$item['name']] = $item;
        }

        $list = [];
        foreach ($available as $name => $info) {
            $dbRecord = $installedMap[$name] ?? null;
            $list[] = [
                'name'         => $name,
                'title'        => $info['title'] ?? $name,
                'version'      => $info['version'] ?? '1.0.0',
                'author'       => $info['author'] ?? '',
                'description'  => $info['description'] ?? '',
                'installed'    => $dbRecord !== null,
                'enabled'      => $dbRecord ? (int) $dbRecord['status'] === 1 : false,
                'installed_at' => $dbRecord['installed_at'] ?? null,
            ];
        }

        return $list;
    }

    /**
     * 安装插件
     */
    public function install(string $name): bool
    {
        if ($this->pluginRepository->findByName($name)) {
            throw new BusinessException('插件已安装');
        }

        $result = PluginManager::install($name);
        if (!$result) {
            throw new BusinessException('插件安装失败');
        }

        return true;
    }

    /**
     * 卸载插件
     */
    public function uninstall(string $name): bool
    {
        if (!$this->pluginRepository->findByName($name)) {
            throw new BusinessException('插件未安装');
        }

        $result = PluginManager::uninstall($name);
        if (!$result) {
            throw new BusinessException('插件卸载失败');
        }

        return true;
    }

    /**
     * 启用插件
     */
    public function enable(string $name): bool
    {
        if (!$this->pluginRepository->findByName($name)) {
            throw new BusinessException('插件未安装');
        }

        $result = PluginManager::enable($name);
        if (!$result) {
            throw new BusinessException('插件启用失败');
        }

        return true;
    }

    /**
     * 禁用插件
     */
    public function disable(string $name): bool
    {
        if (!$this->pluginRepository->findByName($name)) {
            throw new BusinessException('插件未安装');
        }

        $result = PluginManager::disable($name);
        if (!$result) {
            throw new BusinessException('插件禁用失败');
        }

        return true;
    }

    /**
     * 上传插件 ZIP 包
     */
    public function uploadPlugin(\think\file\UploadedFile $file): array
    {
        // 1. 校验扩展名
        $ext = strtolower($file->getOriginalExtension());
        if ($ext !== 'zip') {
            throw new BusinessException('仅支持 .zip 格式的插件包');
        }

        // 2. MIME 类型校验
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file->getPathname());
        if (!in_array($mime, ['application/zip', 'application/x-zip-compressed'])) {
            throw new BusinessException('文件类型不合法');
        }

        // 3. 解压到临时目录
        $tempDir = runtime_path('temp') . 'plugin_' . uniqid() . '/';
        mkdir($tempDir, 0755, true);

        $zip = new \ZipArchive();
        if ($zip->open($file->getPathname()) !== true) {
            $this->cleanDir($tempDir);
            throw new BusinessException('ZIP 文件打开失败');
        }

        // 4. 路径穿越检测
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            $targetPath = $tempDir . $entryName;
            $normalizedTarget = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $targetPath);
            if (strpos($normalizedTarget, '..') !== false) {
                $zip->close();
                $this->cleanDir($tempDir);
                throw new BusinessException('ZIP 包含非法路径');
            }
        }

        $zip->extractTo($tempDir);
        $zip->close();

        // 5. 查找 plugin.json（支持一级子目录）
        $pluginDir = $tempDir;
        if (!file_exists($tempDir . 'plugin.json')) {
            $subDirs = glob($tempDir . '*', GLOB_ONLYDIR);
            $found = false;
            foreach ($subDirs as $sub) {
                if (file_exists($sub . '/plugin.json')) {
                    $pluginDir = $sub . '/';
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $this->cleanDir($tempDir);
                throw new BusinessException('插件包中未找到 plugin.json');
            }
        }

        // 6. 解析并验证 plugin.json
        $pluginInfo = json_decode(file_get_contents($pluginDir . 'plugin.json'), true);
        if (!$pluginInfo || empty($pluginInfo['name']) || empty($pluginInfo['title'])) {
            $this->cleanDir($tempDir);
            throw new BusinessException('plugin.json 格式不合法，必须包含 name 和 title');
        }

        $pluginName = $pluginInfo['name'];
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $pluginName)) {
            $this->cleanDir($tempDir);
            throw new BusinessException('插件名称不合法，仅允许字母、数字和下划线');
        }

        // 7. 移动到 plugins/ 目录
        $targetDir = root_path('plugins') . $pluginName;
        if (is_dir($targetDir)) {
            $this->cleanDir($tempDir);
            throw new BusinessException("插件 {$pluginName} 已存在");
        }

        if (!is_dir(root_path('plugins'))) {
            mkdir(root_path('plugins'), 0755, true);
        }

        rename($pluginDir, $targetDir);

        // 8. 清理临时文件
        $this->cleanDir($tempDir);

        return $pluginInfo;
    }

    /**
     * 删除插件文件（仅未安装的）
     */
    public function deletePlugin(string $name): bool
    {
        if ($this->pluginRepository->findByName($name)) {
            throw new BusinessException('请先卸载插件再删除');
        }

        $pluginDir = root_path('plugins') . $name;
        if (!is_dir($pluginDir)) {
            throw new BusinessException('插件目录不存在');
        }

        $this->cleanDir($pluginDir);
        return true;
    }

    /**
     * 递归删除目录
     */
    private function cleanDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
