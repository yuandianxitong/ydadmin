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
use think\model\relation\HasMany;
use think\model\relation\BelongsToMany;

class Menu extends Model
{
    protected $name = 'menus';

    protected $fillable = [
        'parent_id', 'type', 'title', 'name', 'path', 'component', 'redirect',
        'icon', 'permission', 'is_hidden', 'is_cache', 'is_affix', 'is_iframe',
        'external_link', 'breadcrumb', 'active_menu', 'meta', 'status', 'sort',
        'created_by', 'updated_by'
    ];

    protected $type = [
        'parent_id' => 'integer',
        'type' => 'integer',
        'is_hidden' => 'boolean',
        'is_cache' => 'boolean',
        'is_affix' => 'boolean',
        'is_iframe' => 'boolean',
        'breadcrumb' => 'boolean',
        'status' => 'integer',
        'sort' => 'integer',
        'meta' => 'json',
    ];

    protected $append = ['type_text', 'status_text', 'is_hidden_text', 'is_cache_text'];

    // 关联角色
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_menus', 'role_id', 'menu_id');
    }

    // 关联子菜单
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->order('sort asc, id asc');
    }

    // 关联父菜单
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    // 访问器
    public function getTypeTextAttr($value, $data): string
    {
        if (!isset($data['type'])) return '';
        $types = [1 => '目录', 2 => '菜单', 3 => '按钮'];
        return $types[$data['type']] ?? '未知';
    }

    public function getStatusTextAttr($value, $data): string
    {
        if (!isset($data['status'])) return '';
        return $this->getStatusText($data['status'], [1 => '启用', 0 => '禁用']);
    }

    public function getIsHiddenTextAttr($value, $data): string
    {
        if (!isset($data['is_hidden'])) return '';
        return $data['is_hidden'] ? '是' : '否';
    }

    public function getIsCacheTextAttr($value, $data): string
    {
        if (!isset($data['is_cache'])) return '';
        return $data['is_cache'] ? '是' : '否';
    }

}
