<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\model\system;

use core\base\Model;
use think\model\relation\BelongsToMany;

class Permission extends Model
{
    protected $name = 'permissions';

    protected $fillable = [
        'name', 'title', 'group', 'description', 'guard_name',
        'status', 'sort'
    ];

    protected $type = [
        'status' => 'integer',
        'sort' => 'integer',
    ];

    protected $append = ['status_text'];

    // 关联角色
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions', 'permission_id', 'role_id');
    }

    // 访问器
    public function getStatusTextAttr($value, $data): string
    {
        if (!isset($data['status'])) return '';
        return $this->getStatusText($data['status'], [1 => '启用', 0 => '禁用']);
    }

}
