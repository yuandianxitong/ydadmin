<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\model\diy;

use core\base\Model;

class DiyPageVersion extends Model
{
    protected $name = 'diy_page_versions';

    protected $updateTime = false;
    protected $deleteTime = false;

    protected $json = ['components', 'page_settings'];
    protected $jsonAssoc = true;

    protected $type = [
        'page_id'    => 'integer',
        'version_no' => 'integer',
    ];
}
