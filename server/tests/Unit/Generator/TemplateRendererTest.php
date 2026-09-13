<?php

declare(strict_types=1);

namespace tests\Unit\Generator;

use core\generator\TemplateRenderer;
use tests\TestCase;

final class TemplateRendererTest extends TestCase
{
    private string $stubDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubDir = sys_get_temp_dir() . '/ydadmin-generator-stubs-' . bin2hex(random_bytes(8));
        mkdir($this->stubDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->stubDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->stubDir);
        parent::tearDown();
    }

    private function writeStub(string $name, string $contents): void
    {
        file_put_contents($this->stubDir . '/' . $name, $contents);
    }

    public function test_template_can_read_extracted_vars_and_emit_a_php_open_tag(): void
    {
        $this->writeStub('greeting.stub.php', <<<'STUB'
<?= '<?php' ?> declare(strict_types=1);
class <?= $model ?> {}
// items: <?= implode(',', $items) ?>
STUB);

        $renderer = new TemplateRenderer($this->stubDir);

        $output = $renderer->render('greeting.stub.php', ['model' => 'GenArticle', 'items' => ['a', 'b']]);

        $this->assertStringContainsString('<?php declare(strict_types=1);', $output);
        $this->assertStringContainsString('class GenArticle {}', $output);
        $this->assertStringContainsString('// items: a,b', $output);
    }

    /**
     * 隔离作用域：渲染闭包是 static 的，模板里引用 $this 必须拿不到调用方（TemplateRenderer 自己）
     * 的实例——否则模板就能访问 $this->stubDir 之类的私有状态，隔离形同虚设。
     * PHP 对「在非对象上下文里使用 $this」的判定是运行时的 \Error，而不是编译期错误，
     * 所以只有真正执行到那一行才会抛出；这里用 render() 触发它来断言隔离生效。
     *
     * 用 try/catch/finally 而不是 expectException()：render() 在 ob_start() 之后抛出异常，
     * 异常穿透时缓冲区不会被 ob_get_clean() 关闭，用 expectException() 会让这个用例被
     * PHPUnit 标成 risky（"Test code or tested code did not close its own output buffers"）。
     */
    public function test_template_cannot_access_this_because_the_including_closure_is_static(): void
    {
        $this->writeStub('leaky.stub.php', "<?php\necho \$this->stubDir;\n");

        $renderer = new TemplateRenderer($this->stubDir);

        $levelBefore = ob_get_level();
        try {
            $renderer->render('leaky.stub.php', []);
            $this->fail('期望渲染抛出 \Error（模板不应该能访问 $this）。');
        } catch (\Error $e) {
            $this->assertStringContainsString('Using $this when not in object context', $e->getMessage());
        } finally {
            while (ob_get_level() > $levelBefore) {
                ob_end_clean();
            }
        }
    }

    public function test_extract_skip_prevents_vars_from_overwriting_the_closures_own_arguments(): void
    {
        // EXTR_SKIP：即便调用方在 vars 里塞进 __stub/__vars 这两个闭包参数名，也不能覆盖它们，
        // 否则第二次 require 时落盘路径就被使用者数据篡改了。
        $this->writeStub('safe.stub.php', '<?php echo "ok";');

        $renderer = new TemplateRenderer($this->stubDir);

        $output = $renderer->render('safe.stub.php', ['__stub' => '/etc/passwd', '__vars' => 'anything']);

        $this->assertSame('ok', $output);
    }
}
