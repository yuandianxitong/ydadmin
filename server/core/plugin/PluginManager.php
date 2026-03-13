<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace core\plugin;

use think\facade\Cache;
use think\facade\Db;
use think\facade\Event;
use think\facade\Log;
use core\exception\BusinessException;

class PluginManager
{
    protected static array $plugins = [];
    protected static array $enabledPlugins = [];
    protected static bool $initialized = false;

    /**
     * 初始化插件系统
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::loadPlugins();
        self::registerAutoload();
        self::registerHooks();
        self::loadRoutes();

        self::$initialized = true;
    }

    /**
     * 安装插件
     */
    public static function install(string $name): bool
    {
        try {
            $plugin = self::getInstance($name);

            // 检查依赖
            if (!$plugin->checkDependencies()) {
                throw new BusinessException(lang('business.plugin_dependency_failed'));
            }

            // 执行数据库迁移
            $plugin->runMigrations();

            // 执行插件安装逻辑
            $result = $plugin->install();

            if ($result) {
                // 记录到数据库
                Db::table('plugins')->insert([
                    'name' => $name,
                    'title' => $plugin->getTitle(),
                    'version' => $plugin->getVersion(),
                    'author' => $plugin->getAuthor(),
                    'description' => $plugin->getDescription(),
                    'status' => 1,
                    'installed_at' => date('Y-m-d H:i:s'),
                ]);

                // 清除缓存
                self::clearCache();

                Log::info('插件安装成功', ['plugin' => $name]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('插件安装失败', [
                'plugin' => $name,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 卸载插件
     */
    public static function uninstall(string $name): bool
    {
        try {
            $plugin = self::getInstance($name);

            // 执行插件卸载逻辑
            $result = $plugin->uninstall();

            if ($result) {
                // 回滚数据库迁移
                $plugin->rollbackMigrations();

                // 从数据库删除记录
                Db::table('plugins')->where('name', $name)->delete();

                // 清除缓存
                self::clearCache();

                Log::info('插件卸载成功', ['plugin' => $name]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('插件卸载失败', [
                'plugin' => $name,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 启用插件
     */
    public static function enable(string $name): bool
    {
        try {
            $plugin = self::getInstance($name);
            $result = $plugin->enable();

            if ($result) {
                Db::table('plugins')
                    ->where('name', $name)
                    ->update(['status' => 1]);

                self::clearCache();
                Log::info('插件启用成功', ['plugin' => $name]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('插件启用失败', [
                'plugin' => $name,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 禁用插件
     */
    public static function disable(string $name): bool
    {
        try {
            $plugin = self::getInstance($name);
            $result = $plugin->disable();

            if ($result) {
                Db::table('plugins')
                    ->where('name', $name)
                    ->update(['status' => 0]);

                self::clearCache();
                Log::info('插件禁用成功', ['plugin' => $name]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('插件禁用失败', [
                'plugin' => $name,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 升级插件
     */
    public static function upgrade(string $name, string $version): bool
    {
        try {
            $plugin = self::getInstance($name);
            $result = $plugin->upgrade($version);

            if ($result) {
                Db::table('plugins')
                    ->where('name', $name)
                    ->update([
                        'version' => $version,
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);

                self::clearCache();
                Log::info('插件升级成功', ['plugin' => $name, 'version' => $version]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('插件升级失败', [
                'plugin' => $name,
                'version' => $version,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 获取所有已安装插件
     */
    public static function getInstalledPlugins(): array
    {
        return Db::table('plugins')->select()->toArray();
    }

    /**
     * 获取已启用插件
     */
    public static function getEnabledPlugins(): array
    {
        return Db::table('plugins')
            ->where('status', 1)
            ->select()
            ->toArray();
    }

    /**
     * 检查插件是否已安装
     */
    public static function isInstalled(string $name): bool
    {
        return Db::table('plugins')->where('name', $name)->count() > 0;
    }

    /**
     * 检查插件是否已启用
     */
    public static function isEnabled(string $name): bool
    {
        return Db::table('plugins')
                ->where('name', $name)
                ->where('status', 1)
                ->count() > 0;
    }

    /**
     * 加载插件
     */
    protected static function loadPlugins(): void
    {
        $cacheKey = 'enabled_plugins';

        self::$enabledPlugins = Cache::remember($cacheKey, function() {
            return Db::table('plugins')
                ->where('status', 1)
                ->column('name');
        }, 3600);
    }

    /**
     * 注册自动加载
     */
    protected static function registerAutoload(): void
    {
        foreach (self::$enabledPlugins as $pluginName) {
            $pluginPath = root_path('plugins') . $pluginName . '/backend/';

            if (is_dir($pluginPath)) {
                spl_autoload_register(function ($class) use ($pluginName, $pluginPath) {
                    $prefix = "plugins\\{$pluginName}\\";

                    if (strpos($class, $prefix) === 0) {
                        $relativeClass = substr($class, strlen($prefix));
                        $file = $pluginPath . str_replace('\\', '/', $relativeClass) . '.php';

                        if (file_exists($file)) {
                            require $file;
                        }
                    }
                });
            }
        }
    }

    /**
     * 注册插件钩子
     */
    protected static function registerHooks(): void
    {
        foreach (self::$enabledPlugins as $pluginName) {
            $info = self::getPluginInfo($pluginName);

            if (!empty($info['hooks'])) {
                foreach ($info['hooks'] as $hook) {
                    Event::listen($hook['name'], $hook['callback']);
                }
            }
        }
    }

    /**
     * 加载插件路由
     */
    protected static function loadRoutes(): void
    {
        foreach (self::$enabledPlugins as $pluginName) {
            $routeFile = root_path('plugins') . $pluginName . '/backend/config/routes.php';

            if (file_exists($routeFile)) {
                include $routeFile;
            }
        }
    }

    /**
     * 获取插件实例
     */
    protected static function getInstance(string $name): BasePlugin
    {
        if (!isset(self::$plugins[$name])) {
            $className = "plugins\\{$name}\\Plugin";

            if (!class_exists($className)) {
                throw new BusinessException("插件类不存在: {$className}");
            }

            self::$plugins[$name] = new $className($name);
        }

        return self::$plugins[$name];
    }

    /**
     * 获取插件信息
     */
    protected static function getPluginInfo(string $name): array
    {
        $infoFile = root_path('plugins') . $name . '/plugin.json';

        if (!file_exists($infoFile)) {
            return [];
        }

        $content = file_get_contents($infoFile);
        return json_decode($content, true) ?: [];
    }

    /**
     * 清除缓存
     */
    protected static function clearCache(): void
    {
        Cache::delete('enabled_plugins');
        Cache::tag('plugin')->clear();
    }

    /**
     * 扫描可用插件
     */
    public static function scanAvailablePlugins(): array
    {
        $pluginsPath = root_path('plugins');
        $available = [];

        if (!is_dir($pluginsPath)) {
            return $available;
        }

        $dirs = scandir($pluginsPath);
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }

            $pluginPath = $pluginsPath . $dir;
            if (is_dir($pluginPath) && file_exists($pluginPath . '/plugin.json')) {
                $info = self::getPluginInfo($dir);
                $info['installed'] = self::isInstalled($dir);
                $info['enabled'] = self::isEnabled($dir);
                $available[$dir] = $info;
            }
        }

        return $available;
    }
}
