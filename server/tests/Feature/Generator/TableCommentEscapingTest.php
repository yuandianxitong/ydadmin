<?php

declare(strict_types=1);

namespace tests\Feature\Generator;

use app\service\system\GeneratorService;
use core\generator\GeneratorRequest;
use core\generator\ModuleBlueprint;
use core\generator\TableDefinition;
use support\Container;
use support\Db;
use tests\Support\ConfigOverride;
use tests\Support\GeneratorFixture;
use tests\TestCase;

/**
 * table_comment 是自由文本，会被插进互不相同的字面量上下文：PHP 文档注释、双引号 HTML 属性里的
 * Vue 单引号字符串、HTML 文本节点、SQL 字符串字面量（外加语言包那一处的 PHP 单引号字面量，由
 * lang.stub.php 在落点上 addslashes）。各处的转义规则完全不同，所以 ModuleBlueprint::vars() 按落点
 * 派生了 tableCommentPhpDoc / tableCommentHtml / tableCommentJs / tableCommentSql 四个变量，
 * 模板只许取对应的那个。
 *
 * 本类的判定方式是**不变量**，不是「某几个已知输入的期望输出」：
 *   1. 四个后端产物仍是合法 PHP，且表说明带来的文本永远只出现在注释类 token 里——以前
 *      「闭合文档注释 + echo shell_exec + 重新打开注释」能产出**顶层可执行 PHP**，而 `php -l`
 *      返回 0（那是合法 PHP），php -l / phpstan / cs-fixer 四道静态门禁天然拦不住。
 *      门禁全绿不等于没有注入，只能靠「它没有变成代码」来判。
 *   2. PhpDoc 落点的变量里一个 星号加斜杠 都不许剩，且不含换行与控制字符（重组类攻击的死穴）。
 *   3. `:title` 那一行外层 HTML 属性引号配对、内层 JS 表达式恰好 4 个字符串定界符。
 *   4. 菜单 SQL 在测试库里真跑一遍，表说明原样进 menus.title 且后续字段不错位。
 *   5. 所有落点下多字节字符逐个原样保留。
 *
 * 输入集按攻击类别组织（见 HOSTILE_COMMENTS）：照抄某一份报告里的具体字符串，只能证明那几条
 * 被修好了，证明不了这一类进不来。
 *
 * preview() 只渲染不落盘，所以本类用真实的 GeneratorService（不换落盘根目录）是安全的。
 */
final class TableCommentEscapingTest extends TestCase
{
    use ConfigOverride;
    use GeneratorFixture;

    /**
     * 敌意输入集**按攻击类别**组织，而不是照抄某一份报告里实测过的那几条字符串。
     *
     * 这一点是上一轮漏掉两个洞的根因：输入集逐字抄自评审报告，于是测试只能证明「报告里那三个
     * 输入现在不灵了」，证明不了「这一类输入都进不来」。星号加斜杠 的剥离恰好对报告里那一条单遍
     * 替换就够，于是「重组类」整类没人看；`:title` 的断言只数单引号，于是「双引号打断外层
     * HTML 属性」整类没人看。所以下面每一条都标注它代表的**类别**，往后要加输入也按类别加：
     *
     *   1. 注释闭合类：直接闭合 PHP 文档注释。
     *   2. 注释重组类：剥离本身会把左右两半粘成新的闭合序列（单遍 str_replace 的盲区）。
     *   3. 引号与反斜杠类：打断 Vue 表达式（内层 JS 字符串）、HTML 属性（外层双引号）、SQL 字面量。
     *   4. 标签注入类：在 HTML 文本节点里开出新标签。
     *   5. 多字节类：中文与全角标点必须原样活下来，转义不能把值吃掉或变成 `?`。
     *
     * 这些输入走 GeneratorRequest 直接构造——刻意绕过 GeneratorController 的入口校验，模拟
     * 「表说明来自 SHOW TABLE STATUS 而不经过请求校验」这条真实路径。模板侧的转义是这条路径上
     * 唯一的防线。（其中「两个星号接两个斜杠」那一条连入口校验都不违反：不含 CR/LF 也不含尖括号，
     * 也就是说它能从 HTTP 端点一路走到落盘。）
     */
    private const HOSTILE_COMMENTS = [
        // 1. 注释闭合类
        'php 文档注释逃逸'   => "*/ echo shell_exec('id'); /*",
        // 2. 注释重组类：剥掉中间的 */ 之后，剩下的 * 与 / 相邻重新构成 */
        '注释重组·双星双斜杠' => "**// echo shell_exec('id'); /*",
        '注释重组·星后换行'   => "*\n/ echo shell_exec('id'); /*",
        '注释重组·三星三斜杠' => "***/// echo shell_exec('id'); /*",
        '注释重组·控制字符'   => "*\x0B/ echo shell_exec('id'); /*",
        // 3. 引号与反斜杠类
        '双引号'             => 'A"B',
        '单引号'             => "A'B",
        '反斜杠结尾'         => 'A\\',
        '中文注释里的撇号'   => "文章'列表",
        // 4. 标签注入类
        'HTML 标签注入'      => '<img src=x onerror=alert(1)>',
        // 5. 多字节类
        '多字节混合引号'     => '中文表：文章\'列表"测试',
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
     * 某个表说明派生出的四个落点变量（不经数据库：列集为空不影响转义那几个变量）。
     *
     * 直接盯变量而不是产物文本，是为了把不变量钉在**转义函数的出口**上：产物里的 星号加斜杠 有可能
     * 来自模板自己的注释，盯变量则一个都不许有。
     *
     * @return array<string, mixed>
     */
    private function commentVars(string $comment): array
    {
        return (new ModuleBlueprint(
            new TableDefinition(self::GOLDEN_TABLE, $comment, []),
            new GeneratorRequest(self::GOLDEN_TABLE, self::GOLDEN_MODULE, self::GOLDEN_MODEL, $comment, []),
        ))->artifacts()['model']['vars'];
    }

    /**
     * 不变量：PhpDoc 落点的变量里**一个 星号加斜杠 都不许剩**，也不许剩换行或控制字符。
     *
     * 这条直接钉死「重组类」：单遍 str_replace 删掉中间那一对之后，左边剩的 `*` 和右边剩的 `/`
     * 会重新贴在一起。判定不看具体输入长什么样，只看出口字符串——不论攻击者怎么堆 `*` 和 `/`，
     * 只要出口里还能找到闭合序列就算没修好。换行同理：文档注释是逐行 ` * ` 前缀的，一个换行
     * 就能让后半段脱离注释，而且它还会参与重组（`*\n/` 剥掉换行就是 星号加斜杠）。
     */
    public function test_php_doc_variable_never_contains_a_comment_terminator(): void
    {
        foreach (self::HOSTILE_COMMENTS as $label => $comment) {
            $phpDoc = (string) $this->commentVars($comment)['tableCommentPhpDoc'];

            $this->assertStringNotContainsString(
                '*/',
                $phpDoc,
                "{$label}：PhpDoc 变量里仍能拼出文档注释闭合序列（{$phpDoc}）"
            );
            $this->assertDoesNotMatchRegularExpression(
                '/[\x00-\x1F\x7F]/',
                $phpDoc,
                "{$label}：PhpDoc 变量里仍有换行或控制字符，逐行 ` * ` 的注释块会被它打散"
            );
        }
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
     * 表单组件的 `:title="form.id ? '编辑X' : '新增X'"` 落点**同时处于两层上下文**，两层都要验。
     *
     * 外层是双引号 HTML 属性，内层是单引号 JS 字符串。上一轮只看了内层（数单引号），于是一个
     * 双引号就能提前闭合属性、向 `<el-dialog>` 注入裸属性，而断言对此毫无感觉。这里按 Vue 的
     * 实际解析顺序倒着验：
     *
     *   1. 外层：整行必须仍是「`:title="` + 不含裸双引号的值 + `"` 收尾」。用 `[^"]*` 加行尾锚点
     *      来判——属性一旦提前闭合，后面就还有内容，正则匹配不上，这比数引号个数更直接。
     *   2. 内层：把属性值按 HTML 反实体化（Vue 编译时就是这么做的），再数**未被转义的**单引号，
     *      必须恰好是 4 个定界符。只数奇偶是不够的（`'编辑文章'列表'` 是 6 个，偶数却已经断了）；
     *      前导反斜杠为偶数个才算定界符，所以 `A\` 转义后的 `'编辑A\\'` 那个引号算定界符（对）。
     */
    public function test_vue_dialog_title_survives_both_the_html_attribute_and_the_js_string(): void
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

            $matches = [];
            $this->assertSame(
                1,
                preg_match('/:title="([^"]*)"\s*$/u', (string) $titleLine, $matches),
                "{$label}：:title 的双引号属性没有正常收尾，值里混进了裸双引号（{$titleLine}）"
            );

            $expression = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
            $this->assertSame(
                4,
                $this->unescapedSingleQuotes($expression),
                "{$label}：HTML 反实体化后，:title 表达式未转义的单引号不是 4 个定界符（{$expression}）"
            );
        }
    }

    /**
     * 转义不能吃字符：所有落点变量里，输入中的多字节字符必须逐个原样活着。
     *
     * 转义器最容易的「修法」就是把可疑字符连同周围一起删掉，或者 htmlspecialchars 漏了
     * ENT_SUBSTITUTE / 字符集导致整串变空——两种都能让上面的安全断言全绿，产物却已经废了。
     * 这条是那些断言的对照组：安全之外，值还得是原来那个值。
     */
    public function test_multi_byte_characters_survive_every_escaping_context(): void
    {
        $keys = ['tableCommentPhpDoc', 'tableCommentHtml', 'tableCommentJs', 'tableCommentSql'];

        foreach (self::HOSTILE_COMMENTS as $label => $comment) {
            $vars = $this->commentVars($comment);

            foreach ($this->multiByteCharacters($comment) as $char) {
                foreach ($keys as $key) {
                    $this->assertStringContainsString(
                        $char,
                        (string) $vars[$key],
                        "{$label}：{$key} 把多字节字符「{$char}」弄丢了"
                    );
                }
            }
        }
    }

    /**
     * 非法 UTF-8 字节不得让整个表说明蒸发。
     *
     * 表说明可能直接来自 `SHOW TABLE STATUS`，编码不干净是现实存在的，而两种很自然的写法都会
     * 让整段表说明变成**空串**——静态门禁全绿，产物里的标题却整段消失：
     *
     *   - `htmlspecialchars()` 少写 ENT_SUBSTITUTE，遇非法字节直接返回空串；
     *   - 剥控制字符的正则多写一个 `/u`，`preg_replace()` 遇非法 UTF-8 返回 null。
     *
     * 所以三个落点变量都要验，PhpDoc 那个不能漏：它正是走正则那条路的。
     */
    public function test_invalid_utf8_bytes_do_not_wipe_out_the_comment(): void
    {
        $vars = $this->commentVars("中文\xFF表");

        foreach (['tableCommentPhpDoc', 'tableCommentHtml', 'tableCommentJs'] as $key) {
            $this->assertNotSame('', (string) $vars[$key], "{$key}：非法 UTF-8 字节让整段表说明变成了空串");
            $this->assertStringContainsString('中文', (string) $vars[$key], "{$key}：合法的部分也被一起丢掉了");
        }
    }

    /**
     * 字符串里的多字节字符（按 UTF-8 码点切分后长度大于 1 字节的那些）。
     *
     * @return list<string>
     */
    private function multiByteCharacters(string $value): array
    {
        $chars = preg_split('//u', $value, -1, \PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter($chars, static fn (string $char): bool => strlen($char) > 1));
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
                // 非贪婪 + /s：表说明里可能带换行（HTML 文本节点不剥它），贪婪匹配会一路吃到
                // 文件里最后一个 </div>，判定就形同虚设
                preg_match('#<div class="table-title">(.*?)</div>#us', $page, $matches),
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
