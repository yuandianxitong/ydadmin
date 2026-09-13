<?php

declare(strict_types=1);

namespace tests\Unit\Generator;

use core\generator\TemplateRenderer;
use support\Db;
use tests\TestCase;

final class MenuStubTest extends TestCase
{
    use GenArticleFixtureColumns;

    public function test_renders_byte_identical_to_golden_fixture(): void
    {
        $renderer = new TemplateRenderer(base_path() . '/core/generator/stubs');
        $content = $renderer->render('menu.stub.php', self::genArticleVars());

        $expected = file_get_contents(base_path() . '/tests/fixtures/generated/database/generated/demo-menu.sql');
        $this->assertSame($expected, $content);
    }

    /**
     * 菜单 SQL 在测试库里真正执行一遍：证明 LAST_INSERT_ID() 会话变量的写法确实可用、
     * 字段与 menus 表结构对得上。执行完立刻按插入的 id 删除，不留痕迹。
     */
    public function test_generated_sql_executes_and_inserts_expected_menu_rows(): void
    {
        $renderer = new TemplateRenderer(base_path() . '/core/generator/stubs');
        $sql = $renderer->render('menu.stub.php', self::genArticleVars());

        $statements = array_values(array_filter(
            array_map('trim', explode(";\n", $sql)),
            static fn (string $s): bool => $s !== ''
        ));
        $this->assertCount(3, $statements, '生成的菜单 SQL 应为 3 条语句（插入父菜单、取 LAST_INSERT_ID、插入按钮）');

        $insertedIds = [];
        try {
            foreach ($statements as $statement) {
                Db::statement($statement);
            }

            $parent = Db::table('menus')->where('name', 'DemoGenArticle')->first();
            $this->assertNotNull($parent, '父菜单未插入成功');
            $insertedIds[] = $parent->id;
            $this->assertSame('生成器夹具表', $parent->title);
            $this->assertSame('/demo/gen-article', $parent->path);
            $this->assertSame('demo/gen-article/index', $parent->component);
            $this->assertSame('demo.gen_article.list', $parent->permission);
            $this->assertSame(0, (int) $parent->parent_id);

            $children = Db::table('menus')->where('parent_id', $parent->id)->orderBy('sort')->get();
            $this->assertCount(4, $children);
            $expectedButtons = [
                ['新增', 'demo.gen_article.create'],
                ['编辑', 'demo.gen_article.update'],
                ['删除', 'demo.gen_article.delete'],
                ['状态', 'demo.gen_article.status'],
            ];
            foreach ($children as $i => $child) {
                $insertedIds[] = $child->id;
                $this->assertSame($expectedButtons[$i][0], $child->title);
                $this->assertSame($expectedButtons[$i][1], $child->permission);
                $this->assertSame(3, (int) $child->type);
            }
        } finally {
            if ($insertedIds !== []) {
                Db::table('menus')->whereIn('id', $insertedIds)->delete();
            }
        }
    }
}
