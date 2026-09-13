# 代码生成器模板

`.stub.php` 是纯 PHP 模板文件，由 `core\generator\TemplateRenderer` 用 `ob_start()` +
静态闭包 `require` 渲染（spec §5.2 决策记录 #2）。模板变量见
`docs/superpowers/specs/2026-09-13-m2a-code-generator-design.md` §5、§7 与起草底稿 §3.1。

**模板里输出 PHP 开标签一律写 `<?= '<?php' ?>`**，不要直接写字面的 `<?php`——模板文件
本身就是被 `require` 执行的 PHP 代码，字面 `<?php` 会被当成模板自己的代码开头解析，而不
是想要输出到生成文件里的文本。

这个目录下的文件不受本仓库的三道静态门禁扫描（原因就是上一条：模板里天然会出现
`use app\...`、未闭合的类定义片段等在“真正的” PHP 文件里不合法的写法）：

- `phpstan.neon` 的 `excludePaths` 排除了 `core/generator/stubs/*`。
- `.php-cs-fixer.php` 的 Finder 用 `notName('*.stub.php')` 排除。
- `scripts/check-context-discipline.sh` 规则六的 `grep` 对 `core/` 加了
  `--exclude-dir=stubs`。

新增模板文件、或改现有模板的变量约定，不需要再动这三处；这三处排除的是整个目录。
