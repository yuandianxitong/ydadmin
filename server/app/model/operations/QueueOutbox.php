<?php

declare(strict_types=1);

namespace app\model\operations;

use core\base\Model;

final class QueueOutbox extends Model
{
    protected $table = 'queue_outbox';
    public $timestamps = false;
    /** @var array<string, string> */
    protected $casts = ['payload' => 'array', 'id' => 'integer', 'attempts' => 'integer', 'status' => 'integer'];
}
