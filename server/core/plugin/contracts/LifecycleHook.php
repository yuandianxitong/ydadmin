<?php

declare(strict_types=1);

namespace core\plugin\contracts;

/**
 * 插件生命周期钩子。
 * 每个插件可选地实现该接口，由宿主在对应时点调用；enable/disable 由宿主按作用域逐个调用。
 * 抛出异常会被包装成 BusinessException::pluginRuntimeError() 重抛。
 */
interface LifecycleHook
{
    /**
     * 首次安装：跑完 migration 后调用，写入默认数据。
     */
    public function install(): void;

    /**
     * 卸载前：仅做注册信息清理。
     * 实际数据保留，由独立的"清理数据"动作（migrator::down）负责。
     */
    public function uninstall(): void;

    /**
     * 原位升级：在新版本代码就位、新 migration 跑完后调用。
     */
    public function upgrade(string $from, string $to): void;

    /**
     * 租户启用：初始化租户级数据。
     * 按套餐启用时在宿主事务内执行，抛错会连同 tenant_plugins 与菜单一起回滚；
     * 因此不得执行 DDL（MySQL 会隐式提交），数据库以外的副作用不会被回滚。
     * 购买激活时在事务提交后执行，抛错只记日志。
     */
    public function enable(int $tenantId): void;

    /**
     * 租户禁用：可选清理租户级缓存等。
     */
    public function disable(int $tenantId): void;
}
