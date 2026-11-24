<?php

declare(strict_types=1);

namespace core\plugin;

use core\exception\BusinessException;
use core\plugin\contracts\LifecycleHook;
use support\Log;

/**
 * 调 manifest.lifecycle 指向的 LifecycleHook::{$hook}()，install/upgrade/uninstall 共用这一份实现。
 *
 * PHP 无法卸载已声明的类：同一进程内卸载后换版本重装，new 出来的仍是第一次加载的旧字节码。
 * 这里无法根治，只在进入时类已声明的情况下记一条 warning。
 */
final class PluginLifecycleInvoker
{
    /**
     * @param array<string, mixed> $manifest
     */
    public function invoke(array $manifest, string $hook): void
    {
        $lifecycleClass = (string) ($manifest['lifecycle'] ?? '');
        if ($lifecycleClass === '') {
            return; // 无 lifecycle 是合法的
        }
        if (class_exists($lifecycleClass, false) === true) {
            Log::warning(
                '[plugin-lifecycle] 本 worker 已加载过同名 lifecycle 类，若插件代码已更新，'
                . '本次执行的可能是旧版钩子；如需确保新版生效请重启 worker',
                [
                    'plugin_code'     => (string) ($manifest['code'] ?? '?'),
                    'lifecycle_class' => $lifecycleClass,
                    'hook'            => $hook,
                ]
            );
        }
        if (!class_exists($lifecycleClass)) {
            return; // 无 lifecycle 是合法的
        }
        $this->callOn($lifecycleClass, $manifest, $hook);
    }

    /**
     * @param array<string, mixed> $manifest
     * @param list<mixed> $args
     */
    public function callOn(string $lifecycleClass, array $manifest, string $hook, array $args = []): void
    {
        $code = (string) ($manifest['code'] ?? '?');
        $instance = new $lifecycleClass();
        if (!$instance instanceof LifecycleHook) {
            throw BusinessException::pluginRuntimeError(
                $code,
                $hook,
                new \RuntimeException('lifecycle 类未实现 LifecycleHook 接口')
            );
        }
        try {
            $instance->{$hook}(...$args);
        } catch (\Throwable $e) {
            throw BusinessException::pluginRuntimeError($code, $hook, $e);
        }
    }
}
