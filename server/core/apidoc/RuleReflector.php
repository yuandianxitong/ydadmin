<?php

declare(strict_types=1);

namespace core\apidoc;

/**
 * 按动作名反射 {action}Rules() 私有方法，经注入的容器解析闭包后实调，
 * 拿到完全求值后的规则数组（$scene 三元、常量拼接、注入服务的方法调用都已展开）。
 *
 * 只接收类名字符串与解析闭包：core/ 不 use app\（check:context 规则六），容器绑定
 * 由 app/ 侧注入（见 app\service\system\ApiDocService，接线在后续任务里做）。
 *
 * 绝不用 newInstanceWithoutConstructor()：规则方法可能碰注入的服务，未初始化实例
 * 一访问就是 Error，会把整份文档打挂。任何一步失败都降级为 null + 一条 warning，
 * 不让单个坏端点波及其余端点。
 */
final class RuleReflector
{
    /** @var list<string> */
    private array $warnings = [];

    /** @param \Closure(string): object $resolver 类名 → 控制器实例（由 app/ 注入容器解析器，core/ 不碰容器） */
    public function __construct(private readonly \Closure $resolver)
    {
    }

    /** @return array<string, string>|null 字段名 => 规则串；null 表示该动作没有规则方法 */
    public function rulesFor(string $controller, string $action): ?array
    {
        $method = $action . 'Rules';

        try {
            if (!class_exists($controller)) {
                $this->warnings[] = sprintf('%s 不存在，已跳过该控制器的规则反射', $controller);

                return null;
            }

            if (!method_exists($controller, $method)) {
                return null;
            }

            $reflectionMethod = new \ReflectionMethod($controller, $method);
            $reflectionMethod->setAccessible(true);
            $instance = ($this->resolver)($controller);
            /** @var array<string, string> $rules */
            $rules = $reflectionMethod->invoke($instance);
        } catch (\Throwable $e) {
            $this->warnings[] = sprintf(
                '%s::%s() 反射求值失败，已降级为无参数端点：%s',
                $controller,
                $method,
                $e->getMessage(),
            );

            return null;
        }

        return $rules;
    }

    /** @return list<string> 解析失败的记录，供 x-doc-warnings 用 */
    public function warnings(): array
    {
        return $this->warnings;
    }
}
