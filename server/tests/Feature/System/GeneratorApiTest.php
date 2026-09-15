<?php

declare(strict_types=1);

namespace tests\Feature\System;

use support\Db;
use tests\Support\ApiTestCase;

/**
 * 代码生成器只读端点（本任务范围：tables / columns；preview / generate 由后续任务补）。
 *
 * gen_articles 是 m2a-drafting-brief.md §5 里全体起草任务共用的黄金夹具表：字段覆盖 status、
 * deleted_at、created_by+dept_id、enum、text、decimal、图片列名、unique 索引、可空列、日期列
 * 全部分支。本测试类自建自删这张表，不依赖任何共享夹具 trait，避免与其他任务的落地顺序耦合。
 */
final class GeneratorApiTest extends ApiTestCase
{
    private const BASE = '/adminapi/system/generator';

    private const FIXTURE_TABLE = 'gen_articles';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropFixtureTable();
        Db::statement(<<<'SQL'
            CREATE TABLE `gen_articles` (
              `id` int unsigned NOT NULL AUTO_INCREMENT,
              `title` varchar(200) NOT NULL COMMENT '标题',
              `summary` varchar(500) DEFAULT NULL COMMENT '摘要',
              `content` longtext COMMENT '正文',
              `cover_image` varchar(255) DEFAULT NULL COMMENT '封面图',
              `category` enum('news','tech','life') NOT NULL DEFAULT 'news' COMMENT '分类',
              `price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '价格',
              `view_count` int unsigned NOT NULL DEFAULT '0' COMMENT '浏览量',
              `slug` varchar(100) NOT NULL COMMENT '别名',
              `published_at` datetime DEFAULT NULL COMMENT '发布时间',
              `status` tinyint NOT NULL DEFAULT '1' COMMENT '状态',
              `sort` int NOT NULL DEFAULT '0' COMMENT '排序',
              `created_by` int unsigned DEFAULT NULL,
              `dept_id` int unsigned DEFAULT NULL,
              `created_at` datetime DEFAULT NULL,
              `updated_at` datetime DEFAULT NULL,
              `deleted_at` datetime DEFAULT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_slug` (`slug`),
              KEY `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='生成器夹具表'
            SQL);
    }

    protected function tearDown(): void
    {
        $this->dropFixtureTable();
        parent::tearDown();
    }

    private function dropFixtureTable(): void
    {
        Db::statement('DROP TABLE IF EXISTS `' . self::FIXTURE_TABLE . '`');
    }

    public function test_menu_seeds(): void
    {
        $menus = Db::table('menus')->whereIn('id', [3, 200, 201])->orderBy('id')->get(['id', 'parent_id', 'type', 'component', 'permission'])->all();
        $this->assertSame(
            [
                [3, 0, 1, 'LAYOUT', null],
                [200, 3, 2, 'system/generator/index', 'system.generator.list'],
                [201, 200, 3, null, 'system.generator.generate'],
            ],
            array_map(static fn (object $menu): array => [
                (int) $menu->id,
                (int) $menu->parent_id,
                (int) $menu->type,
                $menu->component === null ? null : (string) $menu->component,
                $menu->permission === null ? null : (string) $menu->permission,
            ], $menus)
        );
    }

    public function test_module_name_reserved_list_includes_apidoc(): void
    {
        $admin = $this->actingAsAdmin(['system.generator.list', 'system.generator.generate']);

        $response = $this->post(self::BASE . '/preview', [
            'table_name'    => 'dictionaries',
            'module_name'   => 'apidoc',
            'model_name'    => 'ApidocProbe',
            'table_comment' => '探测语言分组 apidoc 是否被 RESERVED_MODULES 拦下',
        ], $admin->token);

        $response->assertCode(422);
        $this->assertSame(lang('generator.module_name_reserved'), $response->data()['errors']['module_name'] ?? null);
    }

    public function test_tables_endpoint_requires_permission_and_lists_shape(): void
    {
        $noPerm = $this->actingAsAdmin([]);
        $this->get(self::BASE . '/tables', [], $noPerm->token)->assertCode(403);

        $admin = $this->actingAsAdmin(['system.generator.list']);
        $data = $this->get(self::BASE . '/tables', [], $admin->token)->assertOk()->data();
        $this->assertIsArray($data);
        $names = array_column($data, 'name');
        $this->assertContains(self::FIXTURE_TABLE, $names);

        $row = $data[array_search(self::FIXTURE_TABLE, $names, true)];
        $this->assertSame(['name', 'comment', 'engine', 'rows'], array_keys($row));
        $this->assertSame('生成器夹具表', $row['comment']);
        $this->assertIsInt($row['rows']);
    }

    public function test_columns_endpoint_returns_twelve_fields_for_every_column(): void
    {
        $admin = $this->actingAsAdmin(['system.generator.list']);
        $columns = $this->get(self::BASE . '/columns', ['table' => self::FIXTURE_TABLE], $admin->token)->assertOk()->data();

        $this->assertCount(17, $columns);
        $expectedKeys = ['name', 'type', 'raw_type', 'nullable', 'default', 'comment', 'key', 'extra', 'form_type', 'searchable', 'in_list', 'in_form'];
        foreach ($columns as $column) {
            $this->assertSame($expectedKeys, array_keys($column), '字段列表必须齐全十二个字段：' . json_encode($column, JSON_UNESCAPED_UNICODE));
        }

        $byName = [];
        foreach ($columns as $column) {
            $byName[$column['name']] = $column;
        }

        // 主键自增列、唯一索引列（直接来自 MySQL 元数据，不依赖字段推断逻辑）
        $this->assertSame('PRI', $byName['id']['key']);
        $this->assertSame('auto_increment', $byName['id']['extra']);
        $this->assertFalse($byName['id']['in_form'], 'id 默认不进表单');
        $this->assertSame('UNI', $byName['slug']['key']);

        // 列名/原始类型触发的控件类型（spec §7.1，只断言与 MySQL 版本渲染细节无关的推断结果）
        $this->assertSame('image', $byName['cover_image']['form_type']);
        $this->assertSame('switch', $byName['status']['form_type']);
        $this->assertSame('select', $byName['category']['form_type']);
        $this->assertSame('textarea', $byName['content']['form_type']);
        $this->assertSame('datepicker', $byName['published_at']['form_type']);

        // 可搜索白名单与状态列（spec §7.2）
        $this->assertTrue($byName['title']['searchable']);
        $this->assertTrue($byName['status']['searchable']);
        $this->assertFalse($byName['slug']['searchable']);

        // 软删列不进表单也不进列表
        $this->assertFalse($byName['deleted_at']['in_form']);
        $this->assertFalse($byName['deleted_at']['in_list']);
    }

    public function test_columns_endpoint_requires_table_param(): void
    {
        $admin = $this->actingAsAdmin(['system.generator.list']);
        $response = $this->get(self::BASE . '/columns', [], $admin->token)->assertCode(422);
        $this->assertSame(lang('generator.table_require'), $response->data()['errors']['table']);
    }

    public function test_columns_endpoint_rejects_unknown_table(): void
    {
        $admin = $this->actingAsAdmin(['system.generator.list']);

        $response = $this->get(self::BASE . '/columns', ['table' => 'no_such_table_xyz'], $admin->token)->assertCode(400);
        $this->assertSame(lang('generator.table_not_found'), $response->message());
    }

    /** 表名白名单（spec §9.2）：任何不逐字命中 listTables() 的输入都必须被当作「表不存在」拒绝，不得拼进 SQL。 */
    public function test_columns_endpoint_rejects_whitelist_bypass_attempts(): void
    {
        $admin = $this->actingAsAdmin(['system.generator.list']);

        $payloads = [
            'admins` -- ',
            'admins`; DROP TABLE admins; -- ',
            self::FIXTURE_TABLE . '` OR 1=1 -- ',
            self::FIXTURE_TABLE . ' ',
        ];
        foreach ($payloads as $payload) {
            $response = $this->get(self::BASE . '/columns', ['table' => $payload], $admin->token)->assertCode(400);
            $this->assertSame(lang('generator.table_not_found'), $response->message(), "payload 未被拒绝：{$payload}");
        }

        // 确认恶意 payload 没有被真的当 SQL 执行：admins 表还在
        $this->assertTrue(Db::table('admins')->exists());
    }
}
