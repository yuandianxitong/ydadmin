<?php

declare(strict_types=1);

namespace core\plugin;

use Composer\Autoload\ClassLoader;
use core\exception\BusinessException;
use core\plugin\contracts\RouteBuckets;
use support\Container;

/**
 * 把单个插件注入运行时：PSR-4 挂到 composer ClassLoader，并校验 manifest.routes 声明的控制器目录存在。
 * 插件路由不进 FastRoute，由 PluginRouteDispatcher 在 Route::fallback 里按请求匹配。
 *
 * $classLoader 必须是 composer 真正 register() 过的那个实例（ClassLoader::getRegisteredLoaders()）；
 * 让 php-di 自动装配会得到一个孤立实例，addPsr4() 全部落空且不报错。
 * load() 只在 boot 阶段调用；registerPsr4() 也会在安装/卸载期调用，$registeredPsr4 只增不减、只用于去重。
 */
class PluginLoader
{
    /**
     * 已注册过的 (namespace => [absPath => true]) 去重表：addPsr4() 是追加语义，重复调用会让前缀表无限增长。
     *
     * @var array<string, array<string, true>>
     */
    private array $registeredPsr4 = [];

    private readonly RouteBuckets $buckets;

    public function __construct(
        private readonly ClassLoader $classLoader,
        ?RouteBuckets $buckets = null,
    ) {
        $this->buckets = $buckets ?? Container::get(RouteBuckets::class);
    }

    /**
     * 把 manifest.psr4 声明的命名空间挂载到 ClassLoader，幂等。
     *
     * 幂等不解决 PHP 无法卸载已声明类的问题：同一进程内换版本重装，拿到的仍是第一次加载的旧字节码。
     *
     * @param array<string, mixed> $manifest
     * @param string $pluginDir 插件根目录绝对路径，末尾可带可不带 `/`
     */
    public function registerPsr4(array $manifest, string $pluginDir): void
    {
        $code = (string) ($manifest['code'] ?? '');
        $pluginDir = rtrim($pluginDir, '/') . '/';

        $psr4 = (array) ($manifest['psr4'] ?? []);
        foreach ($psr4 as $namespace => $relPath) {
            $namespace = (string) $namespace;
            if (!str_starts_with($namespace, 'Plugin\\')) {
                throw new BusinessException("插件 [{$code}] load 失败：命名空间必须以 Plugin\\ 开头：{$namespace}");
            }
            $absPath = $pluginDir . ltrim((string) $relPath, '/');
            if (isset($this->registeredPsr4[$namespace][$absPath])) {
                continue;
            }
            $this->classLoader->addPsr4($namespace, $absPath);
            $this->registeredPsr4[$namespace][$absPath] = true;
        }
    }

    /**
     * @param array<string, mixed> $manifest 已通过 PluginManifestValidator 校验的 plugin.json
     * @param string $pluginDir 插件根目录绝对路径
     * @return array<string, array{code: string, entitlement: string}>
     *         键为 {@see RouteBuckets::controllerDirs()} 中、manifest 声明了的路由分组
     */
    public function load(array $manifest, string $pluginDir): array
    {
        $code = (string) ($manifest['code'] ?? '');
        if ($code === '') {
            throw new BusinessException('插件 load 失败：manifest.code 为空');
        }
        $pluginDir = rtrim($pluginDir, '/') . '/';

        $this->registerPsr4($manifest, $pluginDir);

        // 路由声明只做登记 + 控制器目录存在性检查，不注册任何路由。
        $routes = (array) ($manifest['routes'] ?? []);
        $entitlement = (string) ($manifest['entitlement'] ?? $code);
        $entries = [];
        foreach ($this->buckets->controllerDirs() as $appName => $controllerRel) {
            $routeRel = $routes[$appName] ?? null;
            if (!$routeRel) {
                continue;
            }
            if (!is_dir($pluginDir . $controllerRel)) {
                throw new BusinessException("插件 [{$code}] load 失败：控制器目录不存在：{$controllerRel}");
            }

            $entries[$appName] = [
                'code' => $code,
                'entitlement' => $entitlement,
            ];
        }

        return $entries;
    }

    /**
     * 空操作：插件路由与中间件链由 PluginRouteDispatcher 按请求处理，本方法不注册任何路由。
     *
     * @param array<int|string, array{code: string, entitlement: string}> $entries
     */
    public function mountAppRoutes(string $appName, array $entries): void
    {
    }
}
