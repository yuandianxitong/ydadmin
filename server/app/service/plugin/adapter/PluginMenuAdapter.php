<?php

declare(strict_types=1);

namespace app\service\plugin\adapter;

use core\exception\BusinessException;
use core\plugin\ManifestReader;
use core\plugin\contracts\PluginMenus;
use core\plugin\PluginPaths;
use core\saga\SagaContext;
use support\Db;

/**
 * 按插件清单的 menus 写 menus / plugin_menus；启用和停用只改这些菜单的 status。
 * 插入规则：按 plugin_menus 归属加 permission 判重，已有就不再插入；parent_path 指向已启用菜单，
 * 按钮菜单（type 3）必须能找到父菜单；新插入的菜单 status 为 1。
 */
final class PluginMenuAdapter implements PluginMenus
{
    public function install(int $pluginId, string $code, SagaContext $ctx): void
    {
        try {
            $this->insertMenus($code, $this->readMenus($code), $ctx);
        } catch (\Throwable $e) {
            $this->compensateInstall($pluginId, $code, $ctx);
            throw $e;
        }
    }

    public function compensateInstall(int $pluginId, string $code, SagaContext $ctx): void
    {
        $menuIds = array_map('intval', (array) $ctx->get('installed_menu_ids', []));
        if ($menuIds === []) {
            return;
        }

        $db = Db::connection();
        $db->table('plugin_menus')->where('plugin_code', $code)->whereIn('menu_id', $menuIds)->delete();
        $db->table('menus')->whereIn('id', $menuIds)->delete();
    }

    public function sync(string $pluginCode, ?int $pluginId = null, ?array $manifest = null, ?int $scopeId = null): void
    {
        $menus = $manifest !== null ? $this->menusOf($manifest) : $this->readMenus($pluginCode);
        $this->insertMenus($pluginCode, $menus, null);
    }

    /**
     * 写库前检查清单里的 menus：必须是数组，每项是对象且带非空的 title 和 permission，否则 400。
     * 按钮的 parent_path 要查库才知道，仍由 install 时的 insertMenus 检查。
     *
     * @param array<string, mixed> $manifest
     */
    public function assertManifestMenus(array $manifest): void
    {
        $menus = $manifest['menus'] ?? [];
        if (!is_array($menus)) {
            throw new BusinessException('menus 必须是数组', 400);
        }
        foreach ($menus as $index => $menu) {
            if (!is_array($menu)) {
                throw new BusinessException("menus[{$index}] 必须是对象", 400);
            }
            $this->assertMenuFields($menu, (string) $index);
        }
    }

    public function remove(string $code): void
    {
        $db = Db::connection();
        $ownership = $db->table('plugin_menus')->where('plugin_code', $code);
        $ids = $ownership->pluck('menu_id')->all();
        if ($ids === []) {
            return;
        }

        $db->table('role_menus')->whereIn('menu_id', $ids)->delete();
        $db->table('menus')->whereIn('id', $ids)->delete();
        $ownership->delete();
    }

    /** 把本插件名下全部菜单的 status 改成 $menuStatus（1 显示，0 隐藏）。 */
    public function setStatus(string $code, int $menuStatus): void
    {
        $db = Db::connection();
        $menuIds = $db->table('plugin_menus')->where('plugin_code', $code)->pluck('menu_id')->all();
        if ($menuIds === []) {
            return;
        }

        $db->table('menus')->whereIn('id', $menuIds)->update([
            'status' => $menuStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 按清单把本插件名下已有菜单的 component 改成清单里写的值。
     * 清单条目没有非空字符串 component 的跳过，按钮菜单（type 3）没有 component 也跳过；不插入新行。
     */
    public function syncComponents(string $code): void
    {
        foreach ($this->readMenus($code) as $menu) {
            $this->updateOwnedComponent($code, $menu);
        }
    }

    /** @param array<string, mixed> $menu */
    private function updateOwnedComponent(string $code, array $menu): void
    {
        $component = $menu['component'] ?? null;
        $permission = $menu['permission'] ?? null;
        if (!is_string($component) || trim($component) === '' || !is_string($permission) || $permission === '') {
            return;
        }
        if ((int) ($menu['type'] ?? 2) === 3) {
            return;
        }

        $db = Db::connection();
        $menuIds = $db->table('plugin_menus')
            ->join('menus', 'menus.id', '=', 'plugin_menus.menu_id')
            ->where('plugin_menus.plugin_code', $code)
            ->where('menus.permission', $permission)
            ->pluck('menus.id')
            ->all();
        if ($menuIds === []) {
            return;
        }

        $db->table('menus')->whereIn('id', $menuIds)->update([
            'component' => $component,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function readMenus(string $code): array
    {
        $manifest = ManifestReader::tryRead(PluginPaths::dir($code));

        return $manifest === null ? [] : $this->menusOf($manifest);
    }

    /**
     * @param array<string, mixed> $manifest
     * @return list<array<string, mixed>>
     */
    private function menusOf(array $manifest): array
    {
        $menus = $manifest['menus'] ?? [];

        return is_array($menus) ? array_values(array_filter($menus, 'is_array')) : [];
    }

    /** @param list<array<string, mixed>> $menus */
    private function insertMenus(string $code, array $menus, ?SagaContext $ctx): void
    {
        $db = Db::connection();
        $now = date('Y-m-d H:i:s');

        foreach ($menus as $index => $menu) {
            $this->assertMenuFields($menu, (string) $index);
            $permission = (string) $menu['permission'];
            $type = (int) ($menu['type'] ?? 2);
            $owned = $db->table('plugin_menus')
                ->join('menus', 'menus.id', '=', 'plugin_menus.menu_id')
                ->where('plugin_menus.plugin_code', $code)
                ->where('menus.permission', $permission)
                ->exists();
            if ($owned) {
                $this->updateOwnedComponent($code, $menu);
                continue;
            }

            $parentId = 0;
            $parentPath = $menu['parent_path'] ?? null;
            if (is_string($parentPath) && $parentPath !== '') {
                $parent = $db->table('menus')->where('path', $parentPath)->where('status', 1)->first();
                if ($parent !== null) {
                    $parentId = (int) $parent->id;
                }
            }
            if ($type === 3 && $parentId === 0) {
                throw new BusinessException('按钮菜单的 parent_path 必须指向已启用菜单');
            }

            $path = $menu['path'] ?? null;
            $component = $menu['component'] ?? null;
            if ($type !== 3 && $component === null && is_string($path)) {
                $component = ltrim($path, '/') . '/index';
            }

            $menuId = (int) $db->table('menus')->insertGetId([
                'parent_id' => $parentId,
                'type' => $type,
                'title' => $menu['title'],
                'name' => $type === 3 ? null : $permission,
                'path' => $type === 3 ? null : $path,
                'component' => $type === 3 ? null : $component,
                'icon' => $menu['icon'] ?? null,
                'permission' => $permission,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $ctx?->push('installed_menu_ids', $menuId);
            $db->table('plugin_menus')->insert([
                'plugin_code' => $code,
                'menu_id' => $menuId,
                'created_at' => $now,
            ]);
        }
    }

    /** @param array<string, mixed> $menu */
    private function assertMenuFields(array $menu, string $index): void
    {
        foreach (['title', 'permission'] as $field) {
            $value = $menu[$field] ?? null;
            if (!is_string($value) || trim($value) === '') {
                throw new BusinessException("menus[{$index}].{$field} 不能为空", 400);
            }
        }
    }
}
