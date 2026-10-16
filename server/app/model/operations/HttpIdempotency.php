<?php

declare(strict_types=1);

namespace app\model\operations;

use core\base\Model;

final class HttpIdempotency extends Model
{
    protected $table = 'http_idempotency';
    public $timestamps = false;
    /** @var array<string, string> */
    protected $casts = ['id' => 'integer', 'actor_id' => 'integer'];
}
