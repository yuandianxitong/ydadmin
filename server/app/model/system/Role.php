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

class Role extends Model
{
    protected $name = 'roles';

    protected $fillable = [
        'name', 'title', 'description', 'data_scope', 'is_system',
        'status', 'sort', 'created_by', 'updated_by'
    ];

    protected $type = [
        'data_scope' => 'integer',
        'is_system' => 'boolean',
        'status' => 'integer',
        'sort' => 'integer',
    ];

    protected $append = ['status_text', 'data_scope_text', 'is_system_text'];

    // 关联管理员
    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(Admin::class, 'admin_roles', 'admin_id', 'role_id');
    }

    // 关联菜单（统一管理权限）
    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'role_menus', 'menu_id', 'role_id');
    }

    // 访问器
    public function getStatusTextAttr($value, $data): string
    {
        if (!isset($data['status'])) return '';
        return $this->getStatusText($data['status'], [1 => '正常', 0 => '禁用']);
    }

    public function getDataScopeTextAttr($value, $data): string
    {
        if (!isset($data['data_scope'])) return '';
        $scopes = [
            1 => '全部数据',
            2 => '自定义数据',
            3 => '本部门数据',
            4 => '本部门及下级数据',
            5 => '仅本人数据'
        ];
        return $scopes[$data['data_scope']] ?? '未知';
    }

    public function getIsSystemTextAttr($value, $data): string
    {
        if (!isset($data['is_system'])) return '';
        return $data['is_system'] ? '是' : '否';
    }

    /**
     * 分配菜单权限
     */
    public function assignMenus(array $menuIds): bool
    {
        return $this->menus()->sync($menuIds) !== false;
    }

    /**
     * 获取菜单ID列表
     */
    public function getMenuIds(): array
    {
        return $this->menus()->column('id');
    }
}
