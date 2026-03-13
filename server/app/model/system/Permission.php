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
    protected $table = 'permissions';

    protected $fillable = [
        'name', 'title', 'group', 'description', 'guard_name',
        'status', 'sort'
    ];

    protected $type = [
        'status' => 'integer',
        'sort' => 'integer',
    ];

    // 关联角色
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions', 'permission_id', 'role_id');
    }

    // 访问器
    public function getStatusTextAttr($value, $data): string
    {
        return $this->getStatusText($data['status'], [1 => '启用', 0 => '禁用']);
    }

    /**
     * 按分组获取权限
     */
    public static function getGroupedPermissions(): array
    {
        $permissions = self::where('status', 1)
            ->order('group asc, sort asc, id asc')
            ->select()
            ->toArray();

        $grouped = [];
        foreach ($permissions as $permission) {
            $grouped[$permission['group']][] = $permission;
        }

        return $grouped;
    }

    /**
     * 获取权限树
     */
    public static function getPermissionTree(): array
    {
        $permissions = self::where('status', 1)
            ->order('group asc, sort asc, id asc')
            ->select()
            ->toArray();

        $tree = [];
        foreach ($permissions as $permission) {
            if (!isset($tree[$permission['group']])) {
                $tree[$permission['group']] = [
                    'label' => $permission['group'],
                    'children' => []
                ];
            }

            $tree[$permission['group']]['children'][] = [
                'id' => $permission['id'],
                'label' => $permission['title'],
                'value' => $permission['name']
            ];
        }

        return array_values($tree);
    }
}
