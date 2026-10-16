<?php

declare(strict_types=1);

namespace app\model\system;

use core\base\Model;

final class ConfigurationState extends Model
{
    protected $table = 'configuration_state';
    public $timestamps = false;
    /** @var array<string, string> */
    protected $casts = ['id' => 'integer', 'revision' => 'integer'];
}
