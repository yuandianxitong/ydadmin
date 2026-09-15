<?php

declare(strict_types=1);

namespace tests\Feature\System;

use support\Db;
use tests\Support\ApiTestCase;

/**
 * 菜单 210（API 文档，spec §8）。与 GeneratorApiTest::test_menu_seeds（菜单 3/200/201）
 * 同一种写法：直接查种子行，不经接口。
 */
final class ApiDocApiTest extends ApiTestCase
{
    public function test_menu_seed_210(): void
    {
        $menu = Db::table('menus')->where('id', 210)->first();

        $this->assertNotNull($menu, '菜单 210 未种子——init.sql 第 156 行的占位注释还没被替换成真正的 INSERT');
        $this->assertSame(3, (int) $menu->parent_id, '挂在开发工具（id=3）下');
        $this->assertSame(2, (int) $menu->type, 'type=2 菜单项，前端路由解析要求');
        $this->assertSame('system/api-doc/index', $menu->component, '必须与前端 import.meta.glob 的键逐字对应');
        $this->assertSame('system.api_doc', $menu->permission, '本仓库没有 permissions 表，权限节点直接挂在菜单行');
        $this->assertSame('i-svg:notebook-text', $menu->icon, 'notebook-text.svg 已在 148 个图标里核实存在——图标名对不上会静默渲染成空白');
        $this->assertSame(1, (int) $menu->status);
    }
}
