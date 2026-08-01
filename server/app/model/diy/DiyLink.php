<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\model\diy;

use core\base\Model;

class DiyLink extends Model
{
    protected $name = 'diy_links';

    protected $type = [
        'sort'   => 'integer',
        'status' => 'integer',
    ];
}
