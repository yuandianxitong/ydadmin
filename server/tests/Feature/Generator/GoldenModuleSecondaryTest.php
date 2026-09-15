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
        // json 列：列表里直接 prop="settings" 会把对象渲染成 [object Object]，必须序列化后显示
        $this->assertStringContainsString('JSON.stringify(row.settings)', $content, 'json 列在列表里必须序列化显示');
        $this->assertStringNotContainsString('prop="settings" />', $content, 'json 列不能再用裸 prop 渲染');
        $this->assertGoldenFile($path, $content, 'generated-secondary');
    }

    public function test_form_matches_the_golden_file(): void
    {
        [$path, $content] = $this->renderGoldenSecondary('form');

        $this->assertSame('admin/src/views/catalog/gen-category/components/GenCategoryForm.vue', $path);
        // json 列：后端规则是 nullable|array，表单里存 JSON 文本，打开时解码、提交前编码回对象
        $this->assertStringContainsString('<el-input v-model="form.settings" type="textarea"', $content, 'json 列必须用 textarea 编辑 JSON 文本');
        $this->assertStringContainsString('settings?: string', $content, 'json 列在表单状态里是 JSON 文本，类型必须是 string');
        $this->assertStringNotContainsString('settings?: Record<string, any>', $content, '表单状态不能再把 json 列声明成对象');
        $this->assertStringContainsString("const JSON_FIELDS = ['settings'] as const", $content, '必须登记表中的 json 列');
        $this->assertStringContainsString('sourceData: () => decodeJsonFields(props.formData)', $content, '编辑回填必须先把对象解码成 JSON 文本');
        $this->assertStringContainsString('create(encodeJsonFields(data))', $content, '新增提交前必须把 JSON 文本编码回对象');
        $this->assertStringContainsString('update(id, encodeJsonFields(data))', $content, '修改提交前必须把 JSON 文本编码回对象');
        $this->assertStringContainsString('validator: validateJsonField', $content, 'json 列必须在前端校验 JSON 合法性，避免撞后端 422');
        // useFormDialog() 内部对 sourceData 做 watch({ immediate: true })，getter 在调用返回前就同步执行一次，
        // 会立刻调 decodeJsonFields → 读 JSON_FIELDS。const 若声明在调用之后，组件一挂载就抛 TDZ ReferenceError。
        $jsonFieldsAt = strpos($content, 'const JSON_FIELDS');
        $dialogCallAt = strpos($content, 'useFormDialog<');
        $this->assertNotFalse($jsonFieldsAt);
        $this->assertNotFalse($dialogCallAt);
        $this->assertLessThan($dialogCallAt, $jsonFieldsAt, 'JSON_FIELDS 必须声明在 useFormDialog() 调用之前，否则 immediate watch 触发 TDZ 错误');
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
