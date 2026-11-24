<?php

declare(strict_types=1);

namespace core\plugin\saga;

final class PluginSagaState
{
    public const OP_INSTALL   = 'install';
    public const OP_UPGRADE   = 'upgrade';
    public const OP_UNINSTALL = 'uninstall';
    public const OP_ENABLE    = 'enable';
    public const OP_DISABLE   = 'disable';

    public const ST_PENDING      = 'pending';
    public const ST_RUNNING      = 'running';
    public const ST_COMPENSATING = 'compensating';
    public const ST_COMPLETED    = 'completed';
    public const ST_FAILED       = 'failed';

    /** 占着这个插件、不允许再发起新操作的状态集合。 */
    public const ACTIVE_STATUSES = [self::ST_PENDING, self::ST_RUNNING, self::ST_COMPENSATING];
}
