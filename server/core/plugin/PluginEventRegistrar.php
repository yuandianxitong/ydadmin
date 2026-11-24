<?php

declare(strict_types=1);

namespace core\plugin;

use core\plugin\contracts\EventSubscriber;
use Illuminate\Contracts\Events\Dispatcher;
use support\Container;
use support\Log;

/**
 * 插件事件订阅注册器，由 PluginManager 在 boot 已启用插件时调用。
 *
 * 两种声明方式（可并存）：
 *   1. manifest.events    —— 声明式：{"事件名": "Plugin\\Xxx\\listeners\\Foo"} 或值为类名数组
 *   2. manifest.subscriber —— 实现 EventSubscriber 接口的类，subscribe() 返回同构映射
 *
 * 字符串 handler 必须位于 Plugin\ 命名空间，防止插件把监听器绑到框架内部类上；
 * manifest 层由 PluginManifestValidator 把关，这里对 subscribe() 的运行期返回值兜底。跳过一律记日志。
 */
class PluginEventRegistrar
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
    ) {
    }

    /**
     * @param array<string, mixed> $manifest 校验通过的 plugin.json
     * @return int 实际注册的监听器数量
     */
    public function register(string $code, array $manifest): int
    {
        $bindings = [];

        foreach ((array) ($manifest['events'] ?? []) as $event => $handlers) {
            foreach ((array) $handlers as $handler) {
                $bindings[] = [(string) $event, $handler];
            }
        }

        $subscriber = (string) ($manifest['subscriber'] ?? '');
        if ($subscriber !== '') {
            if (!class_exists($subscriber) || !is_subclass_of($subscriber, EventSubscriber::class)) {
                Log::warning(sprintf(
                    '[plugin:%s] subscriber %s 不存在或未实现 EventSubscriber，跳过',
                    $code,
                    $subscriber,
                ));
            } else {
                foreach ($subscriber::subscribe() as $event => $handlers) {
                    foreach ((array) $handlers as $handler) {
                        $bindings[] = [(string) $event, $handler];
                    }
                }
            }
        }

        $registered = 0;
        foreach ($bindings as [$event, $handler]) {
            if ($event === '') {
                continue;
            }
            // 不接受通配符事件名：Dispatcher 对含 * 的事件按 wildcard 语义以 ($eventName, $payload) 调用监听器，
            // 与插件 handler 的单参数签名不兼容，会表现为“订阅了但从不生效”。
            if (str_contains($event, '*')) {
                Log::warning(sprintf(
                    '[plugin:%s] 事件名 %s 含通配符，本仓库不支持 wildcard 事件订阅，跳过',
                    $code,
                    $event,
                ));
                continue;
            }
            if (is_string($handler) && !str_starts_with($handler, 'Plugin\\')) {
                Log::warning(sprintf(
                    '[plugin:%s] 事件 %s 的 handler 必须位于 Plugin\\ 命名空间，跳过：%s',
                    $code,
                    $event,
                    $handler,
                ));
                continue;
            }
            // 注册时就校验类存在，否则类名写错要到每次 dispatch 才报错，且注册数量上看不出来。
            if (is_string($handler) && !class_exists($handler)) {
                Log::warning(sprintf(
                    '[plugin:%s] 事件 %s 的 handler 类 %s 不存在，跳过',
                    $code,
                    $event,
                    $handler,
                ));
                continue;
            }
            if (!is_string($handler) && !is_callable($handler)) {
                Log::warning(sprintf('[plugin:%s] 事件 %s 的 handler 类型非法，跳过', $code, $event));
                continue;
            }
            // 每个 handler 在注册时单独包一层隔离：插件监听器抛异常不能冒给支付回调等核心流程，
            // 也不能中断同一事件上其它插件的监听器。
            // 字符串 handler 用 Container::make() 每次构造新实例，不用 get()：单例会在常驻进程里跨请求、跨租户残留状态。
            // 实例化发生在 dispatch 时。固定调用 handle()，不支持 Class@method 或 __invoke()。
            $resolvedListener = is_string($handler)
                ? static function (...$payload) use ($handler) {
                    return Container::make($handler, [])->handle(...$payload);
                }
            : $handler;
            $handlerLabel = is_string($handler) ? $handler : '(callable)';
            $this->dispatcher->listen($event, static function (...$payload) use (
                $code,
                $event,
                $handlerLabel,
                $resolvedListener,
            ) {
                try {
                    $resolvedListener(...$payload);
                } catch (\Throwable $e) {
                    Log::error(sprintf(
                        '[plugin:%s] 监听器 %s 处理事件 %s 时抛出异常，已隔离（钱的流程不因插件失败）：%s',
                        $code,
                        $handlerLabel,
                        $event,
                        substr($e->getMessage(), 0, 500),
                    ));
                }

                // 固定返回 null：监听器返回 false 会让 Dispatcher 中断同一事件后续的监听器。
                return null;
            });
            $registered++;
        }

        return $registered;
    }
}
