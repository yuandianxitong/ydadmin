<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\model\plugin;

use core\base\Model;

class Plugin extends Model
{
    protected $name = 'plugins';

    // 插件表不使用软删除
    protected $deleteTime = false;

    protected $schema = [
        'id'           => 'int',
        'name'         => 'string',
        'title'        => 'string',
        'version'      => 'string',
        'author'       => 'string',
        'description'  => 'string',
        'status'       => 'int',
        'installed_at' => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];
}
