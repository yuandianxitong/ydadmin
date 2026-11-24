<?php

declare(strict_types=1);

namespace app\model\plugin;

use core\base\Model;

/** 插件 saga 台账（plugin_sagas）。状态常量与 core\plugin\saga\PluginSagaState 相同。 */
class PluginSaga extends Model
{
    public const ST_PENDING = 'pending';
    public const ST_RUNNING = 'running';
    public const ST_COMPENSATING = 'compensating';
    public const ST_COMPLETED = 'completed';
    public const ST_FAILED = 'failed';

    public const ACTIVE_STATUSES = [self::ST_PENDING, self::ST_RUNNING, self::ST_COMPENSATING];

    protected $table = 'plugin_sagas';
}
