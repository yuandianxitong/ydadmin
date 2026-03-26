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
use core\helper\ArrayHelper;

class Menu extends Model
{
    protected $table = 'menus';

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
        $types = [1 => '目录', 2 => '菜单', 3 => '按钮'];
        return $types[$data['type']] ?? '未知';
    }

    public function getStatusTextAttr($value, $data): string
    {
        return $this->getStatusText($data['status'], [1 => '启用', 0 => '禁用']);
    }

    public function getIsHiddenTextAttr($value, $data): string
    {
        return $data['is_hidden'] ? '是' : '否';
    }

    public function getIsCacheTextAttr($value, $data): string
    {
        return $data['is_cache'] ? '是' : '否';
    }

    /**
     * 获取菜单树
     */
    public static function getMenuTree(int $parentId = 0, bool $onlyEnabled = true): array
    {
        $query = self::order('sort asc, id asc');

        if ($onlyEnabled) {
            $query->where('status', 1);
        }

        $menus = $query->select()->toArray();

        return ArrayHelper::toTree($menus, 'id', 'parent_id', 'children', $parentId);
    }

    /**
     * 获取前端路由菜单
     */
    public static function getFrontendRoutes(array $menuIds = []): array
    {
        // 没有分配任何菜单则返回空路由
        if (empty($menuIds)) {
            return [];
        }

        $menus = self::where('status', 1)
            ->where('type', '<>', 3) // 排除按钮
            ->whereIn('id', $menuIds)
            ->order('sort asc, id asc')
            ->select()
            ->toArray();

        // 转换为前端路由格式
        $routes = [];
        foreach ($menus as $menu) {
            $route = [
                'id' => $menu['id'],
                'parent_id' => $menu['parent_id'], // 添加 parent_id 字段
                'name' => $menu['name'],
                'path' => $menu['path'],
                'component' => $menu['component'],
                'redirect' => $menu['redirect'],
                'type' => $menu['type'], // 添加菜单类型字段
                'meta' => [
                    'title' => $menu['title'],
                    'icon' => $menu['icon'],
                    'hidden' => (bool) $menu['is_hidden'],
                    'cache' => (bool) $menu['is_cache'],
                    'affix' => (bool) $menu['is_affix'],
                    'iframe' => (bool) $menu['is_iframe'],
                    'breadcrumb' => (bool) $menu['breadcrumb'],
                    'activeMenu' => $menu['active_menu'],
                    'permission' => $menu['permission'],
                    'externalLink' => $menu['external_link'],
                ]
            ];

            // 合并自定义meta数据
            if ($menu['meta']) {
                $route['meta'] = array_merge($route['meta'], $menu['meta']);
            }

            $routes[] = $route;
        }

        return ArrayHelper::toTree($routes, 'id', 'parent_id', 'children', 0);
    }

    /**
     * 获取菜单选项树（用于表单选择）
     */
    public static function getMenuOptions(int $excludeId = 0): array
    {
        $menus = self::where('status', 1)
            ->where('type', '<>', 3) // 排除按钮
            ->when($excludeId > 0, function($query) use ($excludeId) {
                $query->where('id', '<>', $excludeId);
            })
            ->order('sort asc, id asc')
            ->field('id, parent_id, title, type')
            ->select()
            ->toArray();

        // 添加根节点
        array_unshift($menus, [
            'id' => 0,
            'parent_id' => -1,
            'title' => '根目录',
            'type' => 0
        ]);

        return ArrayHelper::toTree($menus, 'id', 'parent_id', 'children', -1);
    }

    /**
     * 获取所有子菜单ID
     */
    public function getAllChildrenIds(): array
    {
        $ids = [$this->id];
        $children = self::where('parent_id', $this->id)->select();

        foreach ($children as $child) {
            $ids = array_merge($ids, $child->getAllChildrenIds());
        }

        return $ids;
    }
}
