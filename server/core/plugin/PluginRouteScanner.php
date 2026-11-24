<?php

declare(strict_types=1);

namespace core\plugin;

use core\plugin\contracts\RouteBuckets;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use support\annotation\route\Route;
use support\annotation\route\RouteGroup;
use support\Container;

/**
 * 扫描插件控制器上的路由属性，生成相对路径匹配表。
 * 不注册 webman 路由，也不读取启动期路由表。
 */
final class PluginRouteScanner
{
    private readonly RouteBuckets $buckets;

    public function __construct(?RouteBuckets $buckets = null)
    {
        $this->buckets = $buckets ?? Container::get(RouteBuckets::class);
    }

    /**
     * @param array<string, mixed> $manifest
     * @return list<array{bucket: string, method: string, path: string, controller: string, action: string, apiAuth: bool}>
     */
    public function scan(string $pluginDir, array $manifest): array
    {
        $pluginDir = rtrim(str_replace('\\', '/', $pluginDir), '/');
        $code = (string) ($manifest['code'] ?? '');
        $routes = (array) ($manifest['routes'] ?? []);
        $psr4 = (array) ($manifest['psr4'] ?? []);

        $groups = [];
        foreach ($this->buckets->controllerDirs() as $key => $relativeDir) {
            if (empty($routes[$key])) {
                continue;
            }
            $groups[$relativeDir][$key] = true;
        }

        $rows = [];
        $seen = [];
        foreach ($groups as $relativeDir => $keys) {
            $directory = $pluginDir . '/' . $relativeDir;
            if (!is_dir($directory)) {
                continue;
            }
            foreach ($this->phpFiles($directory) as $file) {
                $class = $this->className($pluginDir, $file, $psr4, $code);
                $this->loadClass($code, $file, $class);
                $ref = new ReflectionClass($class);
                if ($ref->isAbstract()) {
                    continue;
                }
                $collected = $this->collect($ref, $relativeDir, $keys, $file, $pluginDir, $code);
                foreach ($collected as $row) {
                    $duplicateKey = $row['bucket'] . "\0" . $row['method'] . "\0" . $row['path'];
                    if (isset($seen[$duplicateKey])) {
                        throw new \RuntimeException(
                            "插件 {$code} 路由重复：{$row['bucket']} {$row['method']} {$row['path']}"
                        );
                    }
                    $seen[$duplicateKey] = true;
                    $rows[] = $row;
                }
            }
        }

        usort($rows, static function (array $a, array $b): int {
            return [$a['file'], $a['order']] <=> [$b['file'], $b['order']];
        });

        return array_map(static function (array $row): array {
            unset($row['file'], $row['order']);

            return $row;
        }, $rows);
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $files[] = str_replace('\\', '/', $file->getPathname());
        }
        sort($files);

        return $files;
    }

    /**
     * @param array<string, mixed> $psr4
     */
    private function className(string $pluginDir, string $file, array $psr4, string $code): string
    {
        $best = null;
        $bestLength = -1;
        foreach ($psr4 as $namespace => $relative) {
            $base = $pluginDir . '/' . trim(str_replace('\\', '/', (string) $relative), '/');
            $prefix = $base . '/';
            if (!str_starts_with($file, $prefix) || strlen($base) <= $bestLength) {
                continue;
            }
            $classFile = substr($file, strlen($prefix));
            if (!str_ends_with($classFile, '.php')) {
                continue;
            }
            $classFile = substr($classFile, 0, -4);
            $ns = trim(str_replace('/', '\\', (string) $namespace), '\\');
            $best = $ns . '\\' . str_replace('/', '\\', $classFile);
            $bestLength = strlen($base);
        }
        if ($best === null) {
            throw new \RuntimeException("插件 {$code} 控制器加载失败：{$file}");
        }

        return $best;
    }

    private function loadClass(string $code, string $file, string $class): void
    {
        if (!class_exists($class, false)) {
            try {
                require_once $file;
            } catch (\Throwable $e) {
                throw new \RuntimeException("插件 {$code} 控制器加载失败：{$file}", 0, $e);
            }
        }
        if (!class_exists($class, false)) {
            throw new \RuntimeException("插件 {$code} 控制器加载失败：{$file}");
        }
    }

    /**
     * @param array<string, true> $keys
     * @return list<array{bucket: string, method: string, path: string, controller: string, action: string, apiAuth: bool, file: string, order: int}>
     */
    private function collect(
        ReflectionClass $ref,
        string $relativeDir,
        array $keys,
        string $file,
        string $pluginDir,
        string $code,
    ): array {
        $prefix = '';
        $groupAttrs = $ref->getAttributes(RouteGroup::class);
        if ($groupAttrs !== []) {
            /** @var RouteGroup $group */
            $group = $groupAttrs[0]->newInstance();
            $prefix = $this->normalizePrefix($group->prefix);
        }

        $relativeFile = ltrim(substr($file, strlen($pluginDir)), '/');
        $class = $ref->getName();
        $classAuth = $ref->getAttributes(PluginApiAuth::class) !== [];
        $rows = [];
        $order = 0;
        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor() || $method->isDestructor()) {
                continue;
            }
            if ($method->getDeclaringClass()->getName() !== $class) {
                continue;
            }
            $apiAuth = $method->getAttributes(PluginApiAuth::class) !== [] || $classAuth;
            $source = $class . '::' . $method->getName();
            $routeAttrs = $method->getAttributes(Route::class, ReflectionAttribute::IS_INSTANCEOF);
            foreach ($routeAttrs as $routeAttr) {
                /** @var Route $route */
                $route = $routeAttr->newInstance();
                if ($route->path === null) {
                    continue;
                }
                $path = $prefix . $this->normalizePath($route->path, $source);
                if ($path === '') {
                    throw new \RuntimeException("插件注解路由解析后路径为空（{$source}）");
                }
                PluginRouteMatch::assertValid($path, $code, $source);
                $bucket = $this->bucket($relativeDir, $keys, $apiAuth);
                if ($bucket === null) {
                    continue;
                }
                foreach ($route->methods as $httpMethod) {
                    $rows[] = [
                        'bucket' => $bucket,
                        'method' => strtoupper((string) $httpMethod),
                        'path' => $path,
                        'controller' => $class,
                        'action' => $method->getName(),
                        'apiAuth' => $apiAuth,
                        'file' => $relativeFile,
                        'order' => $order,
                    ];
                    $order++;
                }
            }
        }

        return $rows;
    }

    /**
     * @param array<string, true> $keys
     */
    private function bucket(string $relativeDir, array $keys, bool $apiAuth): ?string
    {
        $dirs = $this->buckets->controllerDirs();
        foreach ($this->buckets->authPairs() as $anonymous => $auth) {
            if (($dirs[$anonymous] ?? null) !== $relativeDir) {
                continue;
            }
            if ($apiAuth) {
                return isset($keys[$auth]) ? $auth : null;
            }

            return isset($keys[$anonymous]) ? $anonymous : null;
        }

        return array_key_first($keys);
    }

    private function normalizePrefix(string $prefix): string
    {
        $prefix = trim($prefix);
        if ($prefix === '') {
            return '';
        }
        if ($prefix[0] !== '/') {
            $prefix = '/' . $prefix;
        }

        return rtrim($prefix, '/');
    }

    private function normalizePath(string $path, string $source): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        if ($path[0] !== '/') {
            throw new \RuntimeException("注解路由路径必须以 / 开头：'{$path}'（{$source}）");
        }

        return $path;
    }
}
