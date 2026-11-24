<?php

declare(strict_types=1);

namespace app\model\plugin;

use core\base\Model;

/** 插件 migration 执行记录（plugin_migrations）。表里没有 updated_at。 */
class PluginMigration extends Model
{
    public const DIRECTION_UP = 'up';
    public const DIRECTION_DOWN = 'down';

    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    protected $table = 'plugin_migrations';

    public $timestamps = false;
}
