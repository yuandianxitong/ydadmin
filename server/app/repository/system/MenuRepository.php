<?php

declare(strict_types=1);

namespace app\repository\system;

use app\model\system\Menu;
use core\base\Model;
use core\base\Repository;
use support\Db;

/** 菜单仓储。 */
class MenuRepository extends Repository
{
    /** @var list<string> */
    protected array $sortable = ['id', 'sort'];

    protected function getModel(): Model
    {
        return new Menu();
    }

    /**
     * 获取菜单树。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMenuTree(bool $onlyEnabled = true): array
    {
        $query = $this->query()->orderBy('sort', 'asc')->orderBy('id', 'asc');
        if ($onlyEnabled) {
            $query->where('status', 1);
        }

        $menus = $query->get()->toArray();

        return $this->buildTree($menus, 0);
    }

    /**
     * 获取前端路由菜单（扁平 → 路由树）。
     *
     * @param array<int, int> $menuIds
     * @return array<int, array<string, mixed>>
     */
    public function getFrontendRoutes(array $menuIds = []): array
    {
        if ($menuIds === []) {
            return [];
        }

        $menus = $this->query()->where('status', 1)
            ->where('type', '<>', 3)
            ->whereIn('id', $menuIds)
            ->orderBy('sort', 'asc')->orderBy('id', 'asc')
            ->get()->toArray();

        $routes = [];
        foreach ($menus as $menu) {
            $path = (string) $menu['path'];
            if ($path === '' && (int) $menu['type'] === 1) {
                // 目录类型 path 为空时合成路径，否则前端 createRouteRecord 会过滤掉该目录及其子菜单
                $path = '/dir-' . $menu['id'];
            }

            $route = [
                'id'        => $menu['id'],
                'parent_id' => $menu['parent_id'],
                'name'      => $menu['name'],
                'path'      => $path,
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

            if (!empty($menu['meta']) && is_array($menu['meta'])) {
                $route['meta'] = array_merge($route['meta'], $menu['meta']);
            }

            $routes[] = $route;
        }

        return $this->buildTree($routes, 0);
    }

    /**
     * 获取菜单选项树（用于表单选择，含合成的根目录节点）。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMenuOptions(int $excludeId = 0): array
    {
        $query = $this->query()->where('status', 1)
            ->where('type', '<>', 3)
            ->orderBy('sort', 'asc')->orderBy('id', 'asc');

        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }

        $menus = $query->get(['id', 'parent_id', 'title', 'type'])->toArray();

        array_unshift($menus, [
            'id'        => 0,
            'parent_id' => -1,
            'title'     => '根目录',
            'type'      => 0,
        ]);

        return $this->buildTree($menus, -1);
    }

    /**
     * 根据菜单 ID 列表获取按钮权限标识（真权限源：type∈(2,3) 且已启用且 permission 非空）。
     *
     * @param array<int, int> $menuIds
     * @return list<string>
     */
    public function getButtonPermissionsByMenuIds(array $menuIds): array
    {
        if ($menuIds === []) {
            return [];
        }

        $permissions = $this->query()
            ->whereIn('id', $menuIds)
            ->whereIn('type', [2, 3])
            ->where('status', 1)
            ->where('permission', '<>', '')
            ->pluck('permission')
            ->all();

        return array_values(array_filter(array_unique($permissions), static fn ($p) => is_string($p) && $p !== ''));
    }

    /** @return array<int, int> */
    public function getAllEnabledMenuIds(): array
    {
        return $this->query()->where('status', 1)->pluck('id')->all();
    }

    /**
     * 获取菜单的所有子菜单 ID（含自身）。
     *
     * 一次全量查询 + 内存 BFS，避免深度递归的数据库往返。
     *
     * @return array<int, int>
     */
    public function getAllChildrenIds(int $id): array
    {
        $rows = $this->query()->get(['id', 'parent_id'])->toArray();

        $childrenMap = [];
        foreach ($rows as $row) {
            $childrenMap[(int) $row['parent_id']][] = (int) $row['id'];
        }

        $ids = [$id];
        $queue = [$id];
        while ($queue !== []) {
            $current = array_shift($queue);
            foreach ($childrenMap[$current] ?? [] as $childId) {
                $ids[] = $childId;
                $queue[] = $childId;
            }
        }

        return $ids;
    }

    /** 检查菜单是否被角色使用。 */
    public function isUsedByRole(int $menuId): bool
    {
        return Db::table('role_menus')->where('menu_id', $menuId)->exists();
    }

    /** @return array<int, int> */
    public function getChildrenIdsByParent(int $parentId): array
    {
        return $this->query()->where('parent_id', $parentId)->pluck('id')->all();
    }

    public function existsName(string $name, int $excludeId = 0): bool
    {
        $query = $this->query()->where('name', $name)->where('name', '<>', '');
        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }

        return $query->exists();
    }

    public function existsPath(string $path, int $excludeId = 0): bool
    {
        $query = $this->query()->where('path', $path)->where('path', '<>', '');
        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }

        return $query->exists();
    }

    /**
     * 批量更新 sort（单条 UPDATE ... CASE WHEN，绑定参数防注入）。
     *
     * @param array<int, array{id:int, sort:int}> $rows
     */
    public function batchUpdateSortCase(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $ids = array_map('intval', array_column($rows, 'id'));

        $cases = 'CASE id ';
        $bindings = [];
        foreach ($rows as $row) {
            $cases .= 'WHEN ? THEN ? ';
            $bindings[] = (int) $row['id'];
            $bindings[] = (int) $row['sort'];
        }
        $cases .= 'END';

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $bindings = array_merge($bindings, $ids);

        Db::statement("UPDATE menus SET sort = {$cases} WHERE id IN ({$placeholders})", $bindings);
    }

    /**
     * @param array<int, int|string> $ids
     * @return list<int>
     */
    public function existingIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return array_values(array_map('intval', $this->query()->whereIn($this->qualify('id'), $ids)->pluck($this->qualify('id'))->all()));
    }

    /**
     * 扁平数组 → 树形（引用构建，单次遍历）。
     *
     * @param array<int, array<string, mixed>> $rows 每行须含 'id'/'parent_id'
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(array $rows, int $rootParentId): array
    {
        $mapped = [];
        foreach ($rows as $row) {
            $row['id'] = (int) $row['id'];
            $row['parent_id'] = (int) $row['parent_id'];
            $row['children'] = [];
            $mapped[$row['id']] = $row;
        }

        $tree = [];
        foreach ($mapped as &$item) {
            $pid = $item['parent_id'];
            if ($pid === $rootParentId || !isset($mapped[$pid])) {
                $tree[] = &$item;
            } else {
                $mapped[$pid]['children'][] = &$item;
            }
        }
        unset($item);

        return $tree;
    }
}
