<?php

declare(strict_types=1);

namespace app\service\system;

use app\repository\system\MenuRepository;
use core\auth\Permission;
use core\auth\SuperAdminGuard;
use core\base\Service;
use core\context\RequestContext;
use core\exception\BusinessException;
use DI\Attribute\Inject;

/**
 * 菜单。写操作（增、改——含经 update 改状态、删、批量删、批量排序）仅超管（SuperAdminGuard，M1b 越权收口）：
 * 菜单的权限码就是 RBAC 本身，改它等于给持有该菜单的人换权限。读接口照旧按权限点。
 */
class MenuService extends Service
{
    #[Inject]
    protected MenuRepository $menuRepository;

    #[Inject]
    protected Permission $permission;

    #[Inject]
    protected SuperAdminGuard $superAdminGuard;

    /**
     * 前端路由树（契约 §4.3）：auth/info 与 menu/routes 共用，保证两者一致。
     *
     * @param array<int, int> $menuIds
     * @return array<int, array<string, mixed>>
     */
    public function getFrontendRoutes(array $menuIds): array
    {
        return $this->menuRepository->getFrontendRoutes($menuIds);
    }

    /**
     * @param array<int, int> $menuIds
     * @return list<string>
     */
    public function getButtonPermissions(array $menuIds): array
    {
        return $this->menuRepository->getButtonPermissionsByMenuIds($menuIds);
    }

    /**
     * 菜单树（含按钮）。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMenuTree(bool $onlyEnabled = true): array
    {
        return $this->menuRepository->getMenuTree($onlyEnabled);
    }

    /**
     * 菜单选项树（不含按钮，根部为虚拟节点「根目录」）。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMenuOptions(int $excludeId = 0): array
    {
        return $this->menuRepository->getMenuOptions($excludeId);
    }

    /**
     * 创建菜单。菜单写操作后经 afterCommit 清全部权限缓存：菜单的状态、权限点会改变所有人的
     * 权限集合，超管的权限集合也包含全部菜单。
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function createMenu(array $data): array
    {
        $this->superAdminGuard->assert();

        if (!empty($data['name']) && $this->menuRepository->existsName((string) $data['name'])) {
            throw new BusinessException(lang('business.menu_name_exists'));
        }

        if (!empty($data['path']) && $this->menuRepository->existsPath((string) $data['path'])) {
            throw new BusinessException(lang('business.route_path_exists'));
        }

        $parentId = (int) ($data['parent_id'] ?? 0);
        if ($parentId > 0) {
            $parent = $this->menuRepository->find($parentId);
            if ($parent === null) {
                throw new BusinessException(lang('business.parent_menu_not_found'));
            }

            // 按钮类型的菜单只能是叶子节点
            if ((int) $parent['type'] === 3) {
                throw new BusinessException(lang('business.button_no_children'));
            }
        }

        $menuData = [
            'parent_id'     => $parentId,
            'type'          => $data['type'],
            'title'         => $data['title'],
            'name'          => $data['name'] ?? '',
            'path'          => $data['path'] ?? '',
            'component'     => $data['component'] ?? '',
            'redirect'      => $data['redirect'] ?? '',
            'icon'          => $data['icon'] ?? '',
            'permission'    => $data['permission'] ?? '',
            'is_hidden'     => $data['is_hidden'] ?? 0,
            'is_cache'      => $data['is_cache'] ?? 1,
            'is_affix'      => $data['is_affix'] ?? 0,
            'is_iframe'     => $data['is_iframe'] ?? 0,
            'external_link' => $data['external_link'] ?? '',
            'breadcrumb'    => $data['breadcrumb'] ?? 1,
            'active_menu'   => $data['active_menu'] ?? '',
            'meta'          => $data['meta'] ?? null,
            'status'        => $data['status'] ?? 1,
            'sort'          => $data['sort'] ?? 0,
            'created_by'    => RequestContext::actingUser() ?: null,
        ];

        return $this->runInTransaction(function () use ($menuData) {
            $menu = $this->menuRepository->create($menuData);
            $this->afterCommit(fn () => $this->permission->clearAllCache());

            return $menu;
        });
    }

    /**
     * 更新菜单。
     *
     * @param array<string, mixed> $data
     */
    public function updateMenu(int $id, array $data): void
    {
        $this->superAdminGuard->assert();

        $menu = $this->menuRepository->find($id);
        if ($menu === null) {
            throw new BusinessException(lang('business.menu_not_found'));
        }

        if (isset($data['name']) && !empty($data['name']) && $this->menuRepository->existsName((string) $data['name'], $id)) {
            throw new BusinessException(lang('business.menu_name_exists'));
        }

        if (isset($data['path']) && !empty($data['path']) && $this->menuRepository->existsPath((string) $data['path'], $id)) {
            throw new BusinessException(lang('business.route_path_exists'));
        }

        if (isset($data['parent_id']) && (int) $data['parent_id'] === $id) {
            throw new BusinessException(lang('business.parent_not_self'));
        }

        if (isset($data['parent_id']) && (int) $data['parent_id'] > 0) {
            $parentId = (int) $data['parent_id'];
            $parent = $this->menuRepository->find($parentId);
            if ($parent === null) {
                throw new BusinessException(lang('business.parent_menu_not_found'));
            }

            // 不能设置子菜单为父级
            $allChildrenIds = $this->menuRepository->getAllChildrenIds($id);
            if (in_array($parentId, $allChildrenIds, true)) {
                throw new BusinessException(lang('business.parent_not_child'));
            }
        }

        $updateData = array_filter([
            'parent_id'     => isset($data['parent_id']) ? (int) $data['parent_id'] : null,
            'type'          => $data['type'] ?? null,
            'title'         => $data['title'] ?? null,
            'name'          => $data['name'] ?? null,
            'path'          => $data['path'] ?? null,
            'component'     => $data['component'] ?? null,
            'redirect'      => $data['redirect'] ?? null,
            'icon'          => $data['icon'] ?? null,
            'permission'    => $data['permission'] ?? null,
            'is_hidden'     => $data['is_hidden'] ?? null,
            'is_cache'      => $data['is_cache'] ?? null,
            'is_affix'      => $data['is_affix'] ?? null,
            'is_iframe'     => $data['is_iframe'] ?? null,
            'external_link' => $data['external_link'] ?? null,
            'breadcrumb'    => $data['breadcrumb'] ?? null,
            'active_menu'   => $data['active_menu'] ?? null,
            'meta'          => $data['meta'] ?? null,
            'status'        => $data['status'] ?? null,
            'sort'          => $data['sort'] ?? null,
            'updated_by'    => RequestContext::actingUser() ?: null,
        ], static fn ($value) => $value !== null);

        $this->runInTransaction(function () use ($id, $updateData): void {
            $this->menuRepository->update($id, $updateData);
            $this->afterCommit(fn () => $this->permission->clearAllCache());
        });
    }

    /** 删除菜单（软删）。 */
    public function deleteMenu(int $id): void
    {
        $this->superAdminGuard->assert();

        $menu = $this->menuRepository->find($id);
        if ($menu === null) {
            throw new BusinessException(lang('business.menu_not_found'));
        }

        if ($this->menuRepository->count(['parent_id' => $id]) > 0) {
            throw new BusinessException(lang('business.menu_has_children'));
        }

        if ($this->menuRepository->isUsedByRole($id)) {
            throw new BusinessException(lang('business.menu_used_by_role'));
        }

        $this->runInTransaction(function () use ($id): void {
            $this->menuRepository->delete($id);
            $this->afterCommit(fn () => $this->permission->clearAllCache());
        });
    }

    /**
     * 批量删除菜单（事务：任一失败整体回滚，避免部分成功）。
     *
     * @param array<int, int|string> $ids
     */
    public function batchDeleteMenus(array $ids): void
    {
        $this->superAdminGuard->assert();

        if ($ids === []) {
            return;
        }

        $this->runInTransaction(function () use ($ids): void {
            foreach ($ids as $id) {
                $this->deleteMenu((int) $id);
            }
        });
    }

    /**
     * 批量排序（仅同级内）。
     *
     * 每个 id 必须存在：MenuRepository::batchUpdateSortCase 是裸 SQL（`support` 层 query
     * builder 直调）按 id 更新 sort，不经 `query()`。因此在分组/重排之前，必须先对每个提交的
     * id 用 `find()`（走 `query()`）显式校验其存在——id 不存在一律拒绝。
     *
     * @param array<int, array{id:int,parent_id:int,sort:int}> $items
     */
    public function batchSort(array $items): void
    {
        $this->superAdminGuard->assert();

        foreach ($items as $row) {
            if (!isset($row['id'], $row['parent_id'], $row['sort'])) {
                throw new BusinessException(lang('business.sort_field_missing'));
            }
            if (!is_int($row['id']) || !is_int($row['parent_id']) || !is_int($row['sort'])) {
                throw new BusinessException(lang('business.sort_field_type_error'));
            }
        }

        foreach ($items as $row) {
            if ($this->menuRepository->find($row['id']) === null) {
                throw new BusinessException(lang('business.menu_not_found'));
            }
        }

        $groups = [];
        foreach ($items as $row) {
            $groups[$row['parent_id']][] = $row;
        }

        $this->runInTransaction(function () use ($groups): void {
            foreach ($groups as $parentId => $rows) {
                $dbChildren = $this->menuRepository->getChildrenIdsByParent((int) $parentId);

                $submitIds = array_column($rows, 'id');
                $diff = array_diff($submitIds, $dbChildren);
                if ($diff !== []) {
                    throw new BusinessException(lang('business.sort_parent_mismatch'));
                }

                usort($rows, static fn ($a, $b) => $a['sort'] <=> $b['sort']);
                foreach ($rows as $idx => &$r) {
                    $r['sort'] = ($idx + 1) * 10;
                }
                unset($r);

                $this->menuRepository->batchUpdateSortCase($rows);
            }
            $this->afterCommit(fn () => $this->permission->clearAllCache());
        });
    }
}
