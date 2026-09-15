<?php

declare(strict_types=1);

namespace tests\Feature\Generator;

use tests\Support\GeneratorFixture;
use tests\Support\GoldenFile;
use tests\TestCase;

/**
 * 第二张黄金夹具表（M2a 延续清单 #5、#6）：gen_categories 无 status / 图片列 / created_by 列，
 * 补齐 hasStatus=false、hasImage=false、dataScoped=false、boolean/json 校验规则、email 文案
 * 五类此前从未被跑过的模板分支。用法与 GoldenModuleTest 完全一致，只是喂第二张表、落到
 * generated-secondary 这棵独立的夹具树（模块名同为 catalog，不会和 generated/ 下的产物撞路径）。
 */
final class GoldenModuleSecondaryTest extends TestCase
{
    use GeneratorFixture;
    use GoldenFile;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createGoldenTable2();
    }

    public static function tearDownAfterClass(): void
    {
        self::dropGoldenTable2();
        parent::tearDownAfterClass();
    }

    public function test_the_second_fixture_table_closes_the_named_gaps(): void
    {
        $table = $this->goldenBlueprintSecondary()->table;

        $this->assertFalse($table->hasStatus(), '夹具表二不应含 status 列，否则测不出 hasStatus=false 分支');
        $this->assertNull($table->creatorColumn(), '夹具表二不应含 created_by 列，否则测不出 dataScoped=false 分支');
        $this->assertSame('boolean', $table->column('is_featured')?->type, 'tinyint(1) 必须归一化成 boolean 类型');
        $this->assertSame('json', $table->column('settings')?->type);
        $this->assertNotNull($table->column('contact_email'), '夹具表二必须含一个 email 命名列');
    }

    public function test_repository_matches_the_golden_file(): void
    {
        [$path, $content] = $this->renderGoldenSecondary('repository');

        $this->assertSame('app/repository/catalog/GenCategoryRepository.php', $path);
        $this->assertStringContainsString('protected bool $dataScoped = false;', $content, 'dataScoped=false 分支没有被走到');
        $this->assertGoldenFile($path, $content, 'generated-secondary');
    }

    public function test_controller_matches_the_golden_file(): void
    {
        [$path, $content] = $this->renderGoldenSecondary('controller');

        $this->assertSame('app/adminapi/controller/catalog/GenCategoryController.php', $path);
        $this->assertStringNotContainsString('function status(', $content, 'hasStatus=false 时不应生成 status 端点');
        $this->assertStringContainsString("'is_featured'", $content, 'boolean 列必须出现在规则表里');
        $this->assertGoldenFile($path, $content, 'generated-secondary');
    }

    public function test_model_matches_the_golden_file(): void
    {
        [$path, $content] = $this->renderGoldenSecondary('model');

        $this->assertSame('app/model/catalog/GenCategory.php', $path);
        $this->assertGoldenFile($path, $content, 'generated-secondary');
    }

    public function test_service_matches_the_golden_file(): void
    {
        [$path, $content] = $this->renderGoldenSecondary('service');

        $this->assertSame('app/service/catalog/GenCategoryService.php', $path);
        $this->assertGoldenFile($path, $content, 'generated-secondary');
    }

    public function test_api_matches_the_golden_file(): void
    {
        [$path, $content] = $this->renderGoldenSecondary('api');

        $this->assertSame('admin/src/api/gen-category.ts', $path);
        $this->assertStringContainsString('updateStatus: _updateStatus', $content, 'hasStatus=false 时必须剔除 updateStatus 导出');
        $this->assertGoldenFile($path, $content, 'generated-secondary');
    }

    public function test_page_matches_the_golden_file(): void
    {
        [$path, $content] = $this->renderGoldenSecondary('page');

        $this->assertSame('admin/src/views/catalog/gen-category/index.vue', $path);
        $this->assertGoldenFile($path, $content, 'generated-secondary');
    }

    public function test_form_matches_the_golden_file(): void
    {
        [$path, $content] = $this->renderGoldenSecondary('form');

        $this->assertSame('admin/src/views/catalog/gen-category/components/GenCategoryForm.vue', $path);
        $this->assertGoldenFile($path, $content, 'generated-secondary');
    }

    public function test_lang_zh_matches_the_golden_file(): void
    {
        [$path, $content] = $this->renderGoldenSecondary('lang_zh');

        $this->assertSame('resource/lang/zh_CN/catalog.php', $path);
        $this->assertStringContainsString('格式不正确', $content, 'email 文案分支没有被走到');
        $this->assertGoldenFile($path, $content, 'generated-secondary');
    }

    public function test_lang_en_matches_the_golden_file(): void
    {
        [$path, $content] = $this->renderGoldenSecondary('lang_en');

        $this->assertSame('resource/lang/en/catalog.php', $path);
        $this->assertStringContainsString('format is invalid', $content, 'email 文案分支没有被走到');
        $this->assertGoldenFile($path, $content, 'generated-secondary');
    }
}
