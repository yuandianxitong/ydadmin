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
use think\Model;

class MenuRepository extends Repository
{
    protected function getModel(): Model
    {
        return new Menu();
    }

    /**
     * 获取菜单树
     */
    public function getMenuTree(bool $onlyEnabled = true): array
    {
        return Menu::getMenuTree(0, $onlyEnabled);
    }

    /**
     * 获取前端路由菜单
     */
    public function getFrontendRoutes(array $menuIds = []): array
    {
        return Menu::getFrontendRoutes($menuIds);
    }

    /**
     * 获取菜单选项树
     */
    public function getMenuOptions(int $excludeId = 0): array
    {
        return Menu::getMenuOptions($excludeId);
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
            ->select(['id', 'title', 'permission'])
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

        $buttons = \think\facade\Db::table((new Menu())->getTable())
            ->whereIn('id', $menuIds)
            ->where('type', 3)
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
        return \think\facade\Db::table((new Menu())->getTable())
            ->where('status', 1)
            ->column('id');
    }

    /**
     * 获取菜单的所有子菜单ID
     */
    public function getAllChildrenIds(int $id): array
    {
        $menu = $this->model->find($id);
        if (!$menu) {
            return [];
        }
        return $menu->getAllChildrenIds();
    }

    /**
     * 检查菜单是否被角色使用
     */
    public function isUsedByRole(int $menuId): bool
    {
        return \think\facade\Db::table('role_menus')->where('menu_id', $menuId)->count() > 0;
    }

    /**
     * 获取指定父级下所有子菜单ID
     */
    public function getChildrenIdsByParent(int $parentId): array
    {
        return \think\facade\Db::table((new Menu())->getTable())
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
     * 删除菜单及其子菜单
     */
    public function deleteWithChildren(int $id): bool
    {
        $menu = $this->model->find($id);
        if (!$menu) {
            return false;
        }

        $childrenIds = $menu->getAllChildrenIds();
        return $this->model->whereIn('id', $childrenIds)->delete() > 0;
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
        \think\facade\Db::execute($sql);
        return true;
    }
}
