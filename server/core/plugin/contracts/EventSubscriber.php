<?php

declare(strict_types=1);

namespace core\plugin\contracts;

/**
 * 插件事件订阅接口。
 * 与 plugin.json 中声明式 events 二选一；本接口适合复杂订阅场景
 * （需要条件判断/闭包 handler 的场景）。
 */
interface EventSubscriber
{
    /**
     * 返回事件名到 handler 的映射。
     * handler 可以是同一插件下的类名（Plugin\ 命名空间，自动实例化）或 callable。
     *
     * @return array<string, string|callable|array<int, string|callable>>
     */
    public static function subscribe(): array;
}
