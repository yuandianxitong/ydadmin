<?php

declare(strict_types=1);

namespace tests\Feature\Generator;

use app\service\system\GeneratorService;
use core\generator\GeneratorRequest;
use support\Container;
use support\Db;
use tests\Support\ConfigOverride;
use tests\Support\GeneratorFixture;
use tests\TestCase;

/**
 * table_comment 是自由文本，会被插进三种互不相同的字面量上下文：PHP 文档注释、Vue 单引号
 * 字符串字面量、SQL 字符串字面量。三处的转义规则各不相同，所以 ModuleBlueprint::vars() 按落点
 * 派生了 tableCommentPhpDoc / tableCommentJs / tableCommentSql 三个变量，模板只许取对应的那个。
 *
 * 本类拿评审里实测过的三个真实输入各跑一遍 preview()，逐个落点验：
 *   1. 「闭合文档注释 + echo shell_exec + 重新打开注释」这一串 —— 以前能在四个后端产物里产出
 *      **顶层可执行 PHP**，且 `php -l` 返回 0。它是合法 PHP，所以 php -l / phpstan / cs-fixer
 *      四道静态门禁天然拦不住，只能靠「产物里根本不该出现这段文本」来判——门禁全绿不等于没有注入。
 *   2. `文章'列表` —— 不需要恶意，中文注释里写个撇号（「用户's 列表」）就中：Vue 的
 *      `:title="form.id ? '编辑X' : '新增X'"` 表达式被打断，页面白屏。
 *   3. `x\` —— 以反斜杠结尾。MySQL 默认不开 NO_BACKSLASH_ESCAPES，只把 `'` 翻倍是不够的，
 *      会产出 `'x\'` 把后面的字段一路吃进字符串里，整条 INSERT 错位；而这份菜单 SQL 是
 *      设计上要人手工执行的。
 *
 * preview() 只渲染不落盘，所以本类用真实的 GeneratorService（不换落盘根目录）是安全的。
 */
final class TableCommentEscapingTest extends TestCase
{
    use ConfigOverride;
    use GeneratorFixture;

    /**
     * 评审报告里实测可注入/可打断的三个输入，逐字照搬；第四个是 HTML 文本节点那个落点的样本。
     *
     * 这四个输入都不含换行或尖括号以外的字符，走 GeneratorRequest 直接构造——刻意绕过
     * GeneratorController 的入口校验，模拟「表说明来自 SHOW TABLE STATUS 而不经过请求校验」
     * 这条真实路径。模板侧的转义是这条路径上唯一的防线。
     */
    private const HOSTILE_COMMENTS = [
        'php 文档注释逃逸' => "*/ echo shell_exec('id'); /*",
        '中文注释里的撇号' => "文章'列表",
        '反斜杠结尾'       => 'x\\',
        'HTML 标签注入'    => '<img src=x onerror=alert(1)>',
    ];

    /** 四个会把表说明写进 PHP 文档注释的后端产物。 */
    private const PHP_ARTIFACTS = ['model', 'repository', 'service', 'controller'];

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

    protected function setUp(): void
    {
        parent::setUp();
        // 两个写端点在 APP_DEBUG=false 时被拒（spec §9.3），判定不能依赖开发机 .env 恰好是什么
        $this->overrideConfig('app.debug', true);
    }

    protected function tearDown(): void
    {
        $this->restoreConfig();
        parent::tearDown();
    }

    /** @return array<string, array{path: string, content: string}> */
    private function previewWithComment(string $comment): array
    {
        $service = Container::get(GeneratorService::class);

        return $service->preview(new GeneratorRequest(
            self::GOLDEN_TABLE,
            self::GOLDEN_MODULE,
            self::GOLDEN_MODEL,
            $comment,
            [],
        ));
    }

    /**
     * 四个后端产物必须仍是合法 PHP，且表注释带来的文本不得落在**可执行位置**上。
     *
     * 判定用 PHP 自己的词法器（token_get_all）而不是子串匹配：转义到位以后，注入的那段文本
     * 仍然原样躺在文档注释里（剥掉的只是能提前闭合注释的 `*` `/` 序列），此时它是惰性的注释内容，
     * 出现在产物文本里完全正常。真正要钉死的不变量是「它没有变成代码」——所以把注释类 token
     * 全部剔除后，再断言剩下的可执行 token 流里不含这段文本。
     *
     * 同时跑一次 `php -l`：它证明产物没被写坏，但**单独看它没有意义**——注入成功时它一样返回 0
     * （那是合法 PHP），四道静态门禁对这种注入天然无能为力，这正是 I1 的要害。
     */
    public function test_php_doc_comment_cannot_break_out_of_the_doc_block(): void
    {
        foreach (self::HOSTILE_COMMENTS as $label => $comment) {
            $preview = $this->previewWithComment($comment);

            foreach (self::PHP_ARTIFACTS as $key) {
                $content = (string) $preview[$key]['content'];
                $message = '';

                $this->assertSame(
                    0,
                    $this->lintExitCode($content, $message),
                    "{$label}：{$key} 产物不是合法 PHP：{$message}"
                );
                $this->assertStringNotContainsString(
                    'shell_exec',
                    $this->executableCode($content),
                    "{$label}：{$key} 产物里出现了可执行的 shell_exec——文档注释被提前闭合了"
                );
                $this->assertStringNotContainsString(
                    'echo',
                    $this->executableCode($content),
                    "{$label}：{$key} 产物的可执行部分里出现了注释带来的语句"
                );
            }
        }
    }

    /**
     * 表单组件的 :title 表达式：注释里的单引号必须被转义，否则 Vue 模板表达式直接语法错误。
     *
     * 只数引号的奇偶是不够的（`'编辑文章'列表'` 是 6 个引号，同样是偶数，却已经断了），
     * 所以数的是**未被转义的**单引号：前面的反斜杠个数为偶数才算定界符。`x\` 这个输入转义后
     * 产出 `'编辑x\\'`，那个引号前面是两个反斜杠（偶数）——它确实是定界符，JS 里这个字面量
     * 的值是 `编辑x\`，正确。
     */
    public function test_vue_dialog_title_keeps_exactly_its_four_string_delimiters(): void
    {
        foreach (self::HOSTILE_COMMENTS as $label => $comment) {
            $form = (string) $this->previewWithComment($comment)['form']['content'];

            $titleLine = null;
            foreach (explode("\n", $form) as $line) {
                if (str_contains($line, ':title=')) {
                    $titleLine = $line;
                    break;
                }
            }
            $this->assertNotNull($titleLine, "{$label}：表单产物里没找到 :title= 那一行");

            $this->assertSame(
                4,
                $this->unescapedSingleQuotes((string) $titleLine),
                "{$label}：:title 行未转义的单引号不是 4 个定界符，表达式已被打断（{$titleLine}）"
            );
        }
    }

    /**
     * 剔除全部注释 token 后的可执行代码文本。
     *
     * 注释里的文本是惰性的，只有逃出注释才谈得上「注入」；用词法器分离两者，比任何子串规则
     * 都准。T_INLINE_HTML 一并剔除：产物是纯 PHP 文件，开标签之前没有内容。
     */
    private function executableCode(string $content): string
    {
        $parts = [];
        foreach (token_get_all($content) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT, \T_INLINE_HTML], true)) {
                    continue;
                }
                $parts[] = $token[1];

                continue;
            }
            $parts[] = $token;
        }

        return implode(' ', $parts);
    }

    /** 未被转义的单引号个数：前导反斜杠为偶数个才算字符串定界符。 */
    private function unescapedSingleQuotes(string $line): int
    {
        $count = 0;
        $backslashes = 0;
        $length = strlen($line);

        for ($i = 0; $i < $length; $i++) {
            $char = $line[$i];
            if ($char === '\\') {
                $backslashes++;

                continue;
            }
            if ($char === "'" && $backslashes % 2 === 0) {
                $count++;
            }
            $backslashes = 0;
        }

        return $count;
    }

    /**
     * 菜单 SQL 在测试库里真跑一遍，断言表说明原样存进 menus.title、后续字段没有错位。
     *
     * 这比「数引号成对」有力得多：`'x\'` 的引号也是成对的，坏在 MySQL 把 `\'` 当成转义单引号，
     * 于是 name/path/component 整体左移一位。只有真执行一次才看得见这个错位。
     * 插入的行在 finally 里按 id 删干净，不留痕迹（与 MenuStubTest 同一手法）。
     */
    public function test_menu_sql_round_trips_the_comment_through_mysql(): void
    {
        foreach (self::HOSTILE_COMMENTS as $label => $comment) {
            $sql = (string) $this->previewWithComment($comment)['menu']['content'];

            $this->assertSame(0, substr_count($sql, "'") % 2, "{$label}：菜单 SQL 里的单引号必须成对");

            $statements = array_values(array_filter(
                array_map('trim', explode(";\n", $sql)),
                static fn (string $s): bool => $s !== ''
            ));

            $insertedIds = [];
            try {
                foreach ($statements as $statement) {
                    Db::statement($statement);
                }

                $parent = Db::table('menus')->where('name', 'DemoGenArticle')->orderBy('id', 'desc')->first();
                $this->assertNotNull($parent, "{$label}：父菜单没插进去");
                $insertedIds[] = $parent->id;
                foreach (Db::table('menus')->where('parent_id', $parent->id)->get() as $child) {
                    $insertedIds[] = $child->id;
                }

                $this->assertSame($comment, (string) $parent->title, "{$label}：表说明没有原样存进 menus.title");
                // 错位的第一个受害者就是紧跟其后的字段
                $this->assertSame('DemoGenArticle', (string) $parent->name, "{$label}：注释后面的字段错位了");
                $this->assertSame('/demo/gen-article', (string) $parent->path, "{$label}：注释后面的字段错位了");
                $this->assertSame('demo.gen_article.list', (string) $parent->permission, "{$label}：注释后面的字段错位了");
                $this->assertCount(4, $insertedIds === [] ? [] : array_slice($insertedIds, 1), "{$label}：按钮权限行数不对");
            } finally {
                if ($insertedIds !== []) {
                    Db::table('menus')->whereIn('id', $insertedIds)->delete();
                }
            }
        }
    }

    /**
     * 列表页的 `<div class="table-title">` 是 HTML **文本节点**：尖括号必须实体化，否则表说明
     * 能直接开出一个新标签，而这份 .vue 是要被编译进后台的。这个落点用 tableCommentJs 毫无用处
     * （它根本不碰尖括号），必须用 tableCommentHtml。
     *
     * 仍按语义断言而不是字面包含：取出文本节点的内容，(1) 里面不得再出现任何 `<` 或 `>`；
     * (2) 反实体化之后必须逐字等于原始表说明——既证明转义到位，也证明没有把值改坏。
     */
    public function test_list_page_title_is_an_escaped_html_text_node(): void
    {
        foreach (self::HOSTILE_COMMENTS as $label => $comment) {
            $page = (string) $this->previewWithComment($comment)['page']['content'];

            $matches = [];
            $this->assertSame(
                1,
                preg_match('#<div class="table-title">(.*)</div>#u', $page, $matches),
                "{$label}：列表页里没找到 table-title 文本节点"
            );
            $inner = $matches[1];

            $this->assertStringNotContainsString('<', $inner, "{$label}：表说明在 HTML 文本节点里开出了新标签");
            $this->assertStringNotContainsString('>', $inner, "{$label}：表说明在 HTML 文本节点里开出了新标签");
            $this->assertSame(
                $comment,
                html_entity_decode($inner, ENT_QUOTES, 'UTF-8'),
                "{$label}：反实体化后必须逐字等于原始表说明（转义不能改变值）"
            );
        }
    }

    /** HTML 标签注入那个输入的定点断言：`<img` 不得原样出现，必须实体化成 `&lt;img`。 */
    public function test_html_tag_injection_is_entity_encoded_in_the_list_page(): void
    {
        $comment = self::HOSTILE_COMMENTS['HTML 标签注入'];
        $page = (string) $this->previewWithComment($comment)['page']['content'];

        $this->assertStringNotContainsString('<img', $page, '注入的标签不得原样出现在列表页产物里');
        $this->assertStringContainsString('&lt;img', $page, '尖括号必须实体化');
    }

    /** 合法的中文说明不该被转义改样子：转义只在必要时发生，不是无条件改写产物。 */
    public function test_a_plain_comment_is_passed_through_unchanged(): void
    {
        $preview = $this->previewWithComment('生成器夹具表');

        $this->assertStringContainsString('生成器夹具表（由代码生成器生成）。', (string) $preview['controller']['content']);
        $this->assertStringContainsString("'编辑生成器夹具表'", (string) $preview['form']['content']);
        $this->assertStringContainsString("'生成器夹具表'", (string) $preview['menu']['content']);
    }

    /** 把内容写进临时文件跑一次 `php -l`（只做语法检查，不执行文件内容）。 */
    private function lintExitCode(string $content, ?string &$message = null): int
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'gen_lint_') . '.php';
        file_put_contents($file, $content);

        try {
            $output = [];
            $exitCode = 0;
            exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $exitCode);
            $message = implode("\n", $output);

            return $exitCode;
        } finally {
            @unlink($file);
        }
    }
}
