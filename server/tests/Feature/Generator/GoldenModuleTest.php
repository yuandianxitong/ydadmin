<?php

declare(strict_types=1);

namespace tests\Feature\Generator;

use tests\Support\GeneratorFixture;
use tests\Support\GoldenFile;
use tests\TestCase;

/**
 * 黄金测试（spec §11.1）：夹具表 gen_articles 的产物与 tests/fixtures/generated/ 下的期望文件逐字节相等。
 * 后续模板任务在这里各加一个用例，共用同一张夹具表与同一套断言，不要另起测试类。
 */
final class GoldenModuleTest extends TestCase
{
    use GeneratorFixture;
    use GoldenFile;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createGoldenTable();
    }

    public static function tearDownAfterClass(): void
    {
        self::dropGoldenTable();
        parent::tearDownAfterClass();
    }

    public function test_the_fixture_table_is_inferred_as_the_spec_says(): void
    {
        $table = $this->goldenBlueprint()->table;

        $this->assertCount(17, $table->columns);
        $this->assertTrue($table->hasStatus());
        $this->assertTrue($table->hasSoftDeletes());
        $this->assertSame('created_by', $table->creatorColumn());
        $this->assertSame('dept_id', $table->deptColumn());
        $this->assertSame(['slug'], $table->uniqueColumns());
        $this->assertSame('id', $table->primaryKey());
        $this->assertSame('生成器夹具表', $table->comment);

        $searchable = array_values(array_map(
            static fn (object $column): string => $column->name,
            array_filter($table->columns, static fn (object $column): bool => $column->searchable),
        ));
        $this->assertSame(['title', 'status'], $searchable, '底稿 §5：只有 title 与 status 是可搜索的');
    }

    public function test_model_matches_the_golden_file(): void
    {
        [$path, $content] = $this->renderGolden('model');

        $this->assertSame('app/model/demo/GenArticle.php', $path);
        $this->assertGoldenFile($path, $content);
    }

    public function test_repository_matches_the_golden_file(): void
    {
        [$path, $content] = $this->renderGolden('repository');

        $this->assertSame('app/repository/demo/GenArticleRepository.php', $path);
        $this->assertGoldenFile($path, $content);
    }

    public function test_repository_with_overridden_search_switches_matches_the_golden_file(): void
    {
        // 夹具表里没有「可搜索的日期列」，range 分支只能由前端回传的开关覆盖（§4.3 允许改 searchable）
        $overrides = [
            'title'        => ['form_type' => 'input', 'searchable' => false, 'in_list' => true, 'in_form' => true],
            'category'     => ['form_type' => 'select', 'searchable' => true, 'in_list' => true, 'in_form' => true],
            'published_at' => ['form_type' => 'datepicker', 'searchable' => true, 'in_list' => true, 'in_form' => true],
        ];

        [$path, $content] = $this->renderGolden('repository', $overrides);

        $this->assertGoldenFile($path, $content, 'generated-override');
    }

    public function test_service_matches_the_golden_file(): void
    {
        [$path, $content] = $this->renderGolden('service');

        $this->assertSame('app/service/demo/GenArticleService.php', $path);
        $this->assertGoldenFile($path, $content);
    }

    public function test_controller_matches_the_golden_file(): void
    {
        [$path, $content] = $this->renderGolden('controller');

        $this->assertSame('app/adminapi/controller/demo/GenArticleController.php', $path);
        $this->assertGoldenFile($path, $content);
    }

    public function test_api_matches_the_golden_file(): void
    {
        [$path, $content] = $this->renderGolden('api');

        $this->assertSame('admin/src/api/gen-article.ts', $path);
        $this->assertGoldenFile($path, $content);
    }

    public function test_page_matches_the_golden_file(): void
    {
        [$path, $content] = $this->renderGolden('page');

        $this->assertSame('admin/src/views/demo/gen-article/index.vue', $path);
        $this->assertGoldenFile($path, $content);
    }

    public function test_form_matches_the_golden_file(): void
    {
        [$path, $content] = $this->renderGolden('form');

        $this->assertSame('admin/src/views/demo/gen-article/components/GenArticleForm.vue', $path);
        $this->assertGoldenFile($path, $content);
    }
}
