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
 *
 * `require` 抛出的任何 `\Throwable`（模板语法错、模板里访问 `$this` 触发的 `\Error`、
 * 将来模板里的业务异常）都必须让本次 `ob_start()` 开的缓冲区照样关闭——webman 是常驻
 * 进程，一次异常渲染留下的脏缓冲区层级会污染同一 worker 后续请求的
 * `ob_get_clean()`/`ob_end_clean()`，这正是常驻内存纪律要防的跨请求状态泄漏。因此用
 * try/finally 兜底，不能只在正常路径调用 `ob_get_clean()`。
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
        try {
            (static function (string $__stub, array $__vars): void {
                extract($__vars, EXTR_SKIP);
                require $__stub;
            })($stubPath, $vars);
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output;
    }
}
