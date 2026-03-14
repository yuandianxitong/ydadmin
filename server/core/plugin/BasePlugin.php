<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\plugin;

use think\facade\Db;
use think\facade\Log;
use core\exception\BusinessException;

abstract class BasePlugin
{
    /**
     * 插件名称（目录名）
     */
    protected string $name;

    /**
     * 插件根目录路径
     */
    protected string $path;

    /**
     * plugin.json 中解析出的插件信息
     */
    protected array $info = [];

    /**
     * 构造函数
     *
     * @param string $name 插件名称（目录名）
     */
    public function __construct(string $name)
    {
        $this->name = $name;
        $this->path = root_path('plugins') . $name . '/';
        $this->loadInfo();
    }

    /**
     * 加载 plugin.json 配置信息
     */
    protected function loadInfo(): void
    {
        $infoFile = $this->path . 'plugin.json';

        if (!file_exists($infoFile)) {
            throw new BusinessException("插件配置文件不存在: {$infoFile}");
        }

        $content = file_get_contents($infoFile);
        $info = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new BusinessException("插件配置文件格式错误: {$infoFile}");
        }

        $this->info = $info;
    }

    // ------------------------------------------------------------------
    // 基本信息获取
    // ------------------------------------------------------------------

    /**
     * 获取插件名称（目录名）
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 获取插件根目录路径
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * 获取插件标题
     */
    public function getTitle(): string
    {
        return $this->info['title'] ?? $this->name;
    }

    /**
     * 获取插件版本号
     */
    public function getVersion(): string
    {
        return $this->info['version'] ?? '1.0.0';
    }

    /**
     * 获取插件作者
     */
    public function getAuthor(): string
    {
        return $this->info['author'] ?? '';
    }

    /**
     * 获取插件描述
     */
    public function getDescription(): string
    {
        return $this->info['description'] ?? '';
    }

    /**
     * 获取完整的插件信息数组
     */
    public function getInfo(): array
    {
        return $this->info;
    }

    // ------------------------------------------------------------------
    // 依赖检查
    // ------------------------------------------------------------------

    /**
     * 检查插件依赖是否满足
     *
     * 读取 plugin.json 中的 require 字段，逐一检查依赖插件是否已安装
     *
     * @return bool 所有依赖均满足时返回 true
     */
    public function checkDependencies(): bool
    {
        $require = $this->info['require'] ?? [];

        if (empty($require)) {
            return true;
        }

        foreach ($require as $dependency => $version) {
            if (!PluginManager::isInstalled($dependency)) {
                Log::warning('插件依赖未满足', [
                    'plugin'     => $this->name,
                    'dependency' => $dependency,
                    'required'   => $version,
                ]);
                return false;
            }
        }

        return true;
    }

    // ------------------------------------------------------------------
    // 数据库迁移
    // ------------------------------------------------------------------

    /**
     * 执行数据库迁移（按文件名升序）
     */
    public function runMigrations(): void
    {
        $migrationPath = $this->path . 'backend/migration/';

        if (!is_dir($migrationPath)) {
            return;
        }

        $files = $this->getMigrationFiles($migrationPath);

        foreach ($files as $file) {
            $this->executeMigration($file, 'up');
        }
    }

    /**
     * 回滚数据库迁移（按文件名降序）
     */
    public function rollbackMigrations(): void
    {
        $migrationPath = $this->path . 'backend/migration/';

        if (!is_dir($migrationPath)) {
            return;
        }

        $files = $this->getMigrationFiles($migrationPath);
        $files = array_reverse($files);

        foreach ($files as $file) {
            $this->executeMigration($file, 'down');
        }
    }

    /**
     * 获取迁移文件列表（按文件名升序排序）
     *
     * @param string $path 迁移文件目录
     * @return array 排序后的迁移文件完整路径列表
     */
    protected function getMigrationFiles(string $path): array
    {
        $files = glob($path . '*.php');

        if (empty($files)) {
            return [];
        }

        sort($files);
        return $files;
    }

    /**
     * 执行单个迁移文件
     *
     * @param string $file      迁移文件完整路径
     * @param string $direction 迁移方向：up | down
     */
    protected function executeMigration(string $file, string $direction): void
    {
        try {
            require_once $file;

            // 从文件名推导类名（文件名格式：20260314010000_create_xxx_table.php）
            $basename = pathinfo($file, PATHINFO_FILENAME);
            $className = $this->migrationFileToClassName($basename);

            if (!class_exists($className)) {
                Log::warning('迁移类不存在', [
                    'plugin' => $this->name,
                    'file'   => $file,
                    'class'  => $className,
                ]);
                return;
            }

            $migration = new $className();

            if (method_exists($migration, $direction)) {
                $migration->$direction();
                Log::info('插件迁移执行成功', [
                    'plugin'    => $this->name,
                    'file'      => basename($file),
                    'direction' => $direction,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('插件迁移执行失败', [
                'plugin'    => $this->name,
                'file'      => basename($file),
                'direction' => $direction,
                'error'     => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 将迁移文件名转换为类名
     *
     * 例：20260314010000_create_departments_table → CreateDepartmentsTable
     *
     * @param string $filename 不含扩展名的文件名
     * @return string 类名
     */
    protected function migrationFileToClassName(string $filename): string
    {
        // 去掉时间戳前缀（格式如 20260314010000_）
        $name = preg_replace('/^\d+_/', '', $filename);

        // 下划线分隔转为 PascalCase
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
    }

    // ------------------------------------------------------------------
    // 生命周期钩子（子类可覆盖）
    // ------------------------------------------------------------------

    /**
     * 安装插件
     *
     * 子类可覆盖此方法以执行自定义安装逻辑。
     * 如果插件目录下存在 install.php，会自动加载执行。
     *
     * @return bool 安装是否成功
     */
    public function install(): bool
    {
        $installScript = $this->path . 'install.php';

        if (file_exists($installScript)) {
            require_once $installScript;
        }

        return true;
    }

    /**
     * 卸载插件
     *
     * 子类可覆盖此方法以执行自定义卸载逻辑。
     * 如果插件目录下存在 uninstall.php，会自动加载执行。
     *
     * @return bool 卸载是否成功
     */
    public function uninstall(): bool
    {
        $uninstallScript = $this->path . 'uninstall.php';

        if (file_exists($uninstallScript)) {
            require_once $uninstallScript;
        }

        return true;
    }

    /**
     * 启用插件
     *
     * 子类可覆盖此方法以执行启用时的自定义逻辑。
     *
     * @return bool 启用是否成功
     */
    public function enable(): bool
    {
        return true;
    }

    /**
     * 禁用插件
     *
     * 子类可覆盖此方法以执行禁用时的自定义逻辑。
     *
     * @return bool 禁用是否成功
     */
    public function disable(): bool
    {
        return true;
    }

    /**
     * 升级插件
     *
     * 子类可覆盖此方法以执行升级时的自定义逻辑。
     *
     * @param string $version 目标版本号
     * @return bool 升级是否成功
     */
    public function upgrade(string $version): bool
    {
        return true;
    }
}
