<?php

declare(strict_types=1);

namespace core\generator;

/**
 * 纯 PHP 模板渲染：`ob_start()` + 静态闭包 `require`（spec §5.2 决策记录 #2）。
 *
 * 用 static 闭包隔离作用域，模板里访问不到 $this（本类或任何调用方实例）；
 * `extract($vars, EXTR_SKIP)` 保证 vars 里即便出现 `__stub`/`__vars` 这两个闭包
 * 参数名也不会覆盖它们。模板文件里输出 PHP 开标签一律写 `<?= '<?php' ?>`。
 *
 * 构造函数收 stub 所在目录，`render()` 收 stub **文件名**（不是完整路径）——以底稿 §3
 * 的签名为准；spec §5.2 的示例代码把 `render()` 的参数写成完整路径是 spec 笔误。
 */
final class TemplateRenderer
{
    public function __construct(private readonly string $stubDir)
    {
    }

    /** @param array<string, mixed> $vars */
    public function render(string $stub, array $vars): string
    {
        $stubPath = $this->stubDir . '/' . $stub;

        ob_start();
        (static function (string $__stub, array $__vars): void {
            extract($__vars, EXTR_SKIP);
            require $__stub;
        })($stubPath, $vars);

        return (string) ob_get_clean();
    }
}
