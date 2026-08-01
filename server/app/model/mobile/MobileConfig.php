<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\model\mobile;

use core\base\Model;

class MobileConfig extends Model
{
    protected $name = 'mobile_configs';

    protected $deleteTime = false;

    protected $json = ['theme_colors', 'tabbar_json', 'tabbar_style'];
    protected $jsonAssoc = true;

    protected $type = [
        'status' => 'integer',
    ];
}
