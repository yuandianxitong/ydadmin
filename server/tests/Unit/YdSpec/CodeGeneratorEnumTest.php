<?php
// tests/Unit/YdSpec/CodeGeneratorEnumTest.php
declare(strict_types=1);

namespace tests\Unit\YdSpec;

use app\service\system\CodeGeneratorService;
use core\ai\ydspec\YdSpecCompiler;
use tests\TestCase;

class CodeGeneratorEnumTest extends TestCase
{
    private function articleFiles(): array
    {
        $spec = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/fixtures/ydspec/article.json'), true);
        $cols = (new YdSpecCompiler())->entityColumns($spec['entities'][0]);
        return (new CodeGeneratorService())->generate([
            'table_name'    => 'articles',
            'module_name'   => 'article',
            'model_name'    => 'Article',
            'table_comment' => '文章管理',
            'columns'       => $cols,
        ]);
    }

    public function testValidateUsesEnumInRule(): void
    {
        $validate = $this->articleFiles()['validate']['content'];
        $this->assertStringContainsString('in:draft,published', $validate);
        $this->assertStringNotContainsString('in:0,1', $validate);
    }

    public function testModelDoesNotTreatEnumStatusAsSwitch(): void
    {
        $model = $this->articleFiles()['model']['content'];
        $this->assertStringNotContainsString('status_text', $model);
    }

    public function testFrontendFormSelectHasEnumOptions(): void
    {
        $form = $this->articleFiles()['form']['content'];
        $this->assertStringContainsString('<el-option label="draft" value="draft" />', $form);
        $this->assertStringContainsString('<el-option label="published" value="published" />', $form);
    }

    public function testTinyintStatusStillUsesZeroOne(): void
    {
        // 非枚举的经典 0/1 status 行为保持不变
        $cols = [
            ['name' => 'id', 'type' => 'bigint', 'raw_type' => 'bigint', 'nullable' => false, 'default' => null, 'comment' => 'ID', 'key' => 'PRI', 'extra' => 'auto_increment', 'form_type' => 'number', 'searchable' => false, 'in_list' => true, 'in_form' => false],
            ['name' => 'status', 'type' => 'tinyint', 'raw_type' => 'tinyint(1)', 'nullable' => false, 'default' => 1, 'comment' => '状态', 'key' => '', 'extra' => '', 'form_type' => 'switch', 'searchable' => true, 'in_list' => true, 'in_form' => true],
        ];
        $files = (new CodeGeneratorService())->generate([
            'table_name' => 'widgets', 'module_name' => 'widget', 'model_name' => 'Widget', 'table_comment' => '组件', 'columns' => $cols,
        ]);
        $this->assertStringContainsString('in:0,1', $files['validate']['content']);
        $this->assertStringContainsString('status_text', $files['model']['content']);
        // 控制器保留 0/1 状态机（OpenAPI 枚举 + status 动作）
        $this->assertStringContainsString('enum: [0, 1]', $files['controller']['content']);
        $this->assertStringContainsString('public function status()', $files['controller']['content']);
        // Service 保留 updateStatus 与默认值 1
        $this->assertStringContainsString('function updateStatus', $files['service']['content']);
        $this->assertStringContainsString("'status' => \$data['status'] ?? 1,", $files['service']['content']);
        // 路由保留 :id/status
        $this->assertStringContainsString(":id/status", $files['route']['content']);
    }
}
