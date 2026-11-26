<?php

declare(strict_types=1);

namespace app\bootstrap;

use core\plugin\PluginFingerprint;
use core\plugin\PluginManager;
use core\plugin\WorkerFingerprintRegistry;
use support\Container;
use support\Log;
use Webman\Bootstrap;
use Workerman\Worker;

/**
 * worker 启动时装载已启用的插件，并让 HTTP worker（名为 webman）登记自己的插件指纹。
 *
 * 三步都不让异常逃出 start()：创建 PluginManager、装载插件、登记指纹失败都只记日志，worker 照常起来；
 * 管理器创建失败时不装载也不登记指纹，指纹没写出去，saga 侧会超时回滚。
 * 环境变量 YDADMIN_PLUGIN_BOOT=0 关闭装载，测试和一次性管理命令可关闭预装载。
 */
class Plugin implements Bootstrap
{
    public static function start(?Worker $worker): void
    {
        if ($worker === null || getenv('YDADMIN_PLUGIN_BOOT') === '0') {
            return;
        }

        try {
            /** @var PluginManager $manager */
            $manager = Container::get(PluginManager::class);
        } catch (\Throwable $e) {
            Log::error('[plugin-boot] 创建 PluginManager 失败：' . $e->getMessage(), ['exception' => $e]);

            return;
        }
        self::bootSafely($manager);

        if (self::recordsFor($worker?->name)) {
            self::recordFingerprintSafely($manager);
        }
    }

    public static function bootSafely(PluginManager $manager): void
    {
        if (getenv('YDADMIN_PLUGIN_BOOT') === '0') {
            return;
        }

        try {
            $manager->bootAll();
        } catch (\Throwable $e) {
            Log::error('[plugin-boot] 装载插件失败：' . $e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * 只有名为 webman 的 HTTP worker 登记指纹：scheduler、queue 等进程不接 HTTP 请求，
     * 登记了会让 saga 把它们算进“需要收敛的 worker”。
     */
    public static function recordsFor(?string $workerName): bool
    {
        return $workerName === 'webman';
    }

    public static function recordFingerprintSafely(PluginManager $manager): void
    {
        try {
            WorkerFingerprintRegistry::write(
                (int) getmypid(),
                PluginFingerprint::of($manager->loadedVersions())
            );
        } catch (\Throwable $e) {
            Log::error('[plugin-boot] 登记 worker 指纹失败：' . $e->getMessage(), ['exception' => $e]);
        }
    }
}
