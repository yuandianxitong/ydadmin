<?php

declare(strict_types=1);

namespace core\base;

use support\Db;

/**
 * Service 基类。依赖注入：受保护强类型属性 + #[\DI\Attribute\Inject]。
 *
 * 单例纪律（常驻内存关键）：Service 是 php-di 容器级单例，严禁在实例属性里存请求态；
 * 请求态一律经 support\Context 传递。实例属性只允许放注入的无状态依赖。
 */
abstract class Service
{
    /**
     * 统一事务入口。禁止手写 beginTransaction/commit/rollBack。
     *
     * @template TReturn
     * @param \Closure(): TReturn $fn
     * @return TReturn
     */
    protected function runInTransaction(\Closure $fn): mixed
    {
        return Db::connection()->transaction($fn);
    }

    /**
     * 注册在最外层事务真正提交后执行的回调；不在事务中则立即执行；事务回滚则丢弃。
     * 用于缓存失效等「必须在数据落库后才做」的副作用。
     *
     * 注意：回调按注册顺序同步执行，某个回调抛出的异常会中止后续回调，并从外层
     * runInTransaction() 调用点抛出（此时数据库已经提交）。涉及资金的事务里，回调体必须自带 try/catch。
     */
    protected function afterCommit(\Closure $fn): void
    {
        Db::connection()->afterCommit($fn);
    }
}
