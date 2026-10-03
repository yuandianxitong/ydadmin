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
 * 不猜：callback 不是 [控制器类, 动作] 数组、或控制器/动作无法反射的路由一律跳过并记录理由，
 * 供 x-doc-warnings 用——一条坏路由绝不能把整份文档打成 500（spec §9）。
 * requiresAuth 看运行时栈，不看路由对象。
 */
final class RouteHarvester
{
    /** @var list<string> */
    private array $skipped = [];

    /**
     * @param list<string> $authMiddleware 代表「需登录」的中间件类名。由 app/ 注入：core/ 不得写死
     *                                     app\ 下的类名（check:context 规则六）。默认空——不注入就
     *                                     不判定任何路由需登录，调用方必须显式说明哪些中间件算认证。
     */
    public function __construct(
        private readonly string $prefix,
        private readonly array $authMiddleware = [],
    ) {
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

        // webman 会保留 callback 不可调用的路由（例如控制器文件已删除，但进程内仍保留着它的路由），
        // 只在控制台打一行 "is not callable"。反射与注解实例化的任何异常都只影响这一条路由。
        // 只记异常类名，不回显异常原文。
        try {
            $reflection = new ReflectionMethod($controller, $action);

            $permission = null;
            foreach ($reflection->getAttributes(Permission::class) as $attribute) {
                $permission = $attribute->newInstance()->code;
            }

            $permissionSkipped = $reflection->getAttributes(PermissionSkip::class) !== [];
        } catch (\Throwable $e) {
            $this->skipped[] = sprintf(
                '%s %s：%s::%s 无法反射（%s），已跳过',
                $method,
                $route->getPath(),
                $controller,
                $action,
                $e::class,
            );

            return null;
        }

        $stack = \Webman\Middleware::getMiddleware('', '', [$controller, $action], $route);
        $names = [];
        foreach ($stack as $item) {
            if (is_array($item) && isset($item[0]) && is_string($item[0])) {
                $names[] = $item[0];
            }
        }

        return new EndpointDescriptor(
            method: $method,
            path: $route->getPath(),
            controller: $controller,
            action: $action,
            permission: $permission,
            permissionSkipped: $permissionSkipped,
            tag: $this->tagFor($route->getPath()),
            requiresAuth: array_intersect($this->authMiddleware, $names) !== [],
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
