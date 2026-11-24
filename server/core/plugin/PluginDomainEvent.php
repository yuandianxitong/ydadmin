<?php

declare(strict_types=1);

namespace core\plugin;

/**
 * plugin 域终态事件常量，由 PluginSagaRunner::publishTerminalEvent() 发布。
 *
 * 只有 install/upgrade 的成功/失败：上游审计只认这两种操作，uninstall/enable/disable 没有消费方。
 *
 * 载荷键（`PAYLOAD_KEYS`）只是说明性文档，不参与任何运行时校验：
 *   - `code`：插件 code
 *   - `from_version`/`to_version`：install 时 `from_version` 恒为 null
 *   - `error_code`：仅在 *_FAILED 两个事件上有值，来自
 *     `core\support\ErrorClassifier::classify()`，不含原始异常消息
 *   - `saga_id`：`plugin_sagas.id`
 *   - `trace_id`：`core\context\RequestContext::traceId()`
 */
final class PluginDomainEvent
{
    public const INSTALLED       = 'plugin.installed';
    public const INSTALL_FAILED  = 'plugin.install_failed';
    public const UPGRADED        = 'plugin.upgraded';
    public const UPGRADE_FAILED  = 'plugin.upgrade_failed';

    /** 说明性文档，不参与运行时校验（见类头）。 */
    public const PAYLOAD_KEYS = ['code', 'from_version', 'to_version', 'error_code', 'saga_id', 'trace_id'];
}
