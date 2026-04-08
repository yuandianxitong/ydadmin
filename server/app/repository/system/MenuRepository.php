<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\repository\system;

use app\model\system\Menu;
use core\base\Repository;
use core\cache\CacheableRepository;
use core\helper\ArrayHelper;
use think\Model;

class MenuRepository extends Repository
{
    use CacheableRepository;

    protected string $cacheTag = 'menu';
    protected int $cacheTTL = 3600;

    protected function getModel(): Model
    {
        return new Menu();
    }

    /**
     * 获取菜单树
     */
    public function getMenuTree(bool $onlyEnabled = true): array
    {
        return $this->cacheRemember('menu_tree:' . ($onlyEnabled ? '1' : '0'), function () use ($onlyEnabled) {
            $query = $this->model->order('sort asc, id asc');

            if ($onlyEnabled) {
                $query->where('status', 1);
            }

            $menus = $query->select()->toArray();

            return ArrayHelper::toTree($menus, 'id', 'parent_id', 'children', 0);
        });
    }

    /**
     * 获取前端路由菜单
     */
    public function getFrontendRoutes(array $menuIds = []): array
    {
        if (empty($menuIds)) {
            return [];
        }

        $menus = $this->model->where('status', 1)
            ->where('type', '<>', 3)
            ->whereIn('id', $menuIds)
            ->order('sort asc, id asc')
            ->select()
            ->toArray();

        $routes = [];
        foreach ($menus as $menu) {
            $route = [
                'id'        => $menu['id'],
                'parent_id' => $menu['parent_id'],
                'name'      => $menu['name'],
                'path'      => $menu['path'],
                'component' => $menu['component'],
                'redirect'  => $menu['redirect'],
                'type'      => $menu['type'],
                'meta'      => [
                    'title'        => $menu['title'],
                    'icon'         => $menu['icon'],
                    'hidden'       => (bool) $menu['is_hidden'],
                    'cache'        => (bool) $menu['is_cache'],
                    'affix'        => (bool) $menu['is_affix'],
                    'iframe'       => (bool) $menu['is_iframe'],
                    'breadcrumb'   => (bool) $menu['breadcrumb'],
                    'activeMenu'   => $menu['active_menu'],
                    'permission'   => $menu['permission'],
                    'externalLink' => $menu['external_link'],
                ],
            ];

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
    public function getMenuOptions(int $excludeId = 0): array
    {
        return $this->cacheRemember('menu_options:' . $excludeId, function () use ($excludeId) {
            $query = $this->model->where('status', 1)
                ->where('type', '<>', 3)
                ->order('sort asc, id asc')
                ->field('id, parent_id, title, type');

            if ($excludeId > 0) {
                $query->where('id', '<>', $excludeId);
            }

            $menus = $query->select()->toArray();

            array_unshift($menus, [
                'id'        => 0,
                'parent_id' => -1,
                'title'     => '根目录',
                'type'      => 0,
            ]);

            return ArrayHelper::toTree($menus, 'id', 'parent_id', 'children', -1);
        });
    }

    /**
     * 获取所有按钮权限
     */
    public function getButtonPermissions(int $parentId): array
    {
        return $this->model->where('parent_id', $parentId)
            ->where('type', 3)
            ->where('status', 1)
            ->order('sort asc, id asc')
            ->field('id, title, permission')
            ->select()
            ->toArray();
    }

    /**
     * 根据菜单ID列表获取按钮权限标识
     */
    public function getButtonPermissionsByMenuIds(array $menuIds): array
    {
        if (empty($menuIds)) {
            return [];
        }

        $buttons = \think\facade\Db::name('menus')
            ->whereIn('id', $menuIds)
            ->whereIn('type', [2, 3])
            ->where('status', 1)
            ->where('permission', '<>', '')
            ->column('permission');

        return array_unique($buttons);
    }

    /**
     * 获取所有启用菜单的ID列表
     */
    public function getAllEnabledMenuIds(): array
    {
        return \think\facade\Db::name('menus')
            ->where('status', 1)
            ->column('id');
    }

    /**
     * 获取菜单的所有子菜单ID（含自身）
     *
     * 采用"一次全量查询 + 内存 BFS"策略，避免深度递归的数据库往返和潜在栈溢出。
     * 无论菜单树多深，整个方法只会产生 1 次 SELECT。
     */
    public function getAllChildrenIds(int $id): array
    {
        // 一次性取出所有菜单的 id/parent_id 映射
        $rows = $this->model->field('id, parent_id')->select()->toArray();

        // 构建 parent_id => [child_ids] 索引
        $childrenMap = [];
        foreach ($rows as $row) {
            $pid = (int) $row['parent_id'];
            $childrenMap[$pid][] = (int) $row['id'];
        }

        // BFS 遍历
        $ids = [$id];
        $queue = [$id];
        while (!empty($queue)) {
            $current = array_shift($queue);
            if (!empty($childrenMap[$current])) {
                foreach ($childrenMap[$current] as $childId) {
                    $ids[] = $childId;
                    $queue[] = $childId;
                }
            }
        }

        return $ids;
    }

    /**
     * 检查菜单是否被角色使用
     */
    public function isUsedByRole(int $menuId): bool
    {
        return \think\facade\Db::name('role_menus')->where('menu_id', $menuId)->count() > 0;
    }

    /**
     * 获取指定父级下所有子菜单ID
     */
    public function getChildrenIdsByParent(int $parentId): array
    {
        return \think\facade\Db::name('menus')
            ->where('parent_id', $parentId)
            ->column('id');
    }

    /**
     * 检查菜单名称是否存在
     */
    public function existsName(string $name, int $excludeId = 0): bool
    {
        $query = $this->model->where('name', $name)->where('name', '<>', '');
        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }
        return $query->count() > 0;
    }

    /**
     * 检查路由路径是否存在
     */
    public function existsPath(string $path, int $excludeId = 0): bool
    {
        $query = $this->model->where('path', $path)->where('path', '<>', '');
        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }
        return $query->count() > 0;
    }

    /**
     * 批量更新 sort（使用 CASE WHEN）
     * @param array<int, array{id:int, sort:int}> $rows
     */
    public function batchUpdateSortCase(array $rows): bool
    {
        if (empty($rows)) return true;

        $table = (new Menu())->getTable();
        $ids   = array_column($rows, 'id');

        // 组装 CASE
        $cases = 'CASE id ';
        foreach ($rows as $r) {
            $id = (int)$r['id'];
            $sort = (int)$r['sort'];
            $cases .= "WHEN {$id} THEN {$sort} ";
        }
        $cases .= 'END';

        // update menus set sort = CASE id WHEN 1 THEN 10 WHEN 2 THEN 20 END where id in (1,2)
        $sql = sprintf(
            "UPDATE %s SET sort = %s WHERE id IN (%s)",
            $table,
            $cases,
            implode(',', array_map('intval', $ids))
        );

        // 执行原生 SQL
        // 缓存失效由 MenuService::batchSort() 触发的 menu.changed 事件统一处理
        \think\facade\Db::execute($sql);
        return true;
    }
}
