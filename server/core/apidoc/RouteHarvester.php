<?php

declare(strict_types=1);

namespace core\apidoc;

use core\permission\Permission;
use core\permission\PermissionSkip;
use ReflectionMethod;
use Webman\Route;
use Webman\Route\Route as RouteObject;

/**
 * 从 Webman\Route::getRoutes() 问活的路由表要 /adminapi（或 /api）下的端点。
 * 不猜：callback 不是 [控制器类, 动作] 数组的路由一律跳过并记录理由，供 x-doc-warnings 用。
 */
final class RouteHarvester
{
    /** @var list<string> */
    private array $skipped = [];

    public function __construct(private readonly string $prefix)
    {
    }

    /** @return list<EndpointDescriptor> 按 path 再按 method 稳定排序 */
    public function harvest(): array
    {
        $this->skipped = [];

        $endpoints = [];
        foreach (Route::getRoutes() as $route) {
            if (!str_starts_with($route->getPath(), $this->prefix)) {
                continue;
            }

            $endpoint = $this->describe($route);
            if ($endpoint !== null) {
                $endpoints[] = $endpoint;
            }
        }

        usort(
            $endpoints,
            static function (EndpointDescriptor $a, EndpointDescriptor $b): int {
                return $a->path <=> $b->path ?: $a->method <=> $b->method;
            }
        );

        return $endpoints;
    }

    /** @return list<string> 跳过的路由及原因 */
    public function skipped(): array
    {
        return $this->skipped;
    }

    private function describe(RouteObject $route): ?EndpointDescriptor
    {
        $methods = $route->getMethods();
        $method = strtoupper((string) ($methods[0] ?? ''));
        $callback = $route->getCallback();

        if (!is_array($callback) || !isset($callback[0], $callback[1]) || !is_string($callback[0]) || !is_string($callback[1])) {
            $this->skipped[] = "{$method} {$route->getPath()}：callback 不是 [控制器类, 动作] 数组，已跳过";

            return null;
        }

        [$controller, $action] = $callback;
        $reflection = new ReflectionMethod($controller, $action);

        $permission = null;
        foreach ($reflection->getAttributes(Permission::class) as $attribute) {
            $permission = $attribute->newInstance()->code;
        }

        $permissionSkipped = $reflection->getAttributes(PermissionSkip::class) !== [];

        return new EndpointDescriptor(
            method: $method,
            path: $route->getPath(),
            controller: $controller,
            action: $action,
            permission: $permission,
            permissionSkipped: $permissionSkipped,
            tag: $this->tagFor($route->getPath()),
        );
    }

    /** 第二段路径分段：'/adminapi/system/dictionary' → 'system'；'/adminapi/auth/info' → 'auth'。 */
    private function tagFor(string $path): string
    {
        $remainder = substr($path, strlen($this->prefix));
        $segments = array_values(array_filter(
            explode('/', $remainder),
            static fn (string $segment): bool => $segment !== ''
        ));

        return $segments[0] ?? '';
    }
}
