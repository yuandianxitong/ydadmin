<?php

declare(strict_types=1);

namespace app\model\operations;

use core\base\Model;

final class BackgroundTask extends Model
{
    protected $table = 'background_tasks';
    public $timestamps = false;
    /** @var array<string, string> */
    protected $casts = ['input' => 'array', 'errors' => 'array', 'result' => 'array', 'id' => 'integer', 'admin_id' => 'integer'];
}
