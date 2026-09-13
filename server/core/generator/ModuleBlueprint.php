<?php

declare(strict_types=1);

namespace core\generator;

/**
 * 一个模块的全部产物：key → (相对基准的落盘路径, 基准, stub 文件名, 模板变量)。
 *
 * 路径只有一种权威形态：**相对其基准的路径**（`app/model/demo/GenArticle.php`、
 * `admin/src/api/gen-article.ts`），另配一个 base 标明挂在哪个基准下。另外两种形态都由它现算：
 *   - 绝对路径（落盘用）= GeneratorService::absolutePath()，用 Service 自己的 serverRoot() / repoRoot()
 *   - 展示路径（API 响应用）= base === 'server' ? 'server/' . path : path
 *   - 夹具路径（黄金测试用）= tests/fixtures/generated/ . path —— 夹具树因此天然镜像产物树
 * 三种形态并存一定会漂移，所以只存权威的那一种，其余现算。
 *
 * 本类**不持有**两个基准根：落盘基准的唯一持有者是 GeneratorService（它的 serverRoot() /
 * repoRoot() 是 protected，测试用子类覆写它们把产物打到临时目录）。蓝图再存一份就成了两个持有者，
 * 而真正生效的只有 Service 那份——陈旧的那份不会被任何测试照到，因为死代码根本不执行。
 * 产物归属由 base 显式标注，不靠「路径是不是以 admin/ 开头」这种字符串前缀去猜——猜错一次
 * 就是把 .vue 写进 server/ 里。
 *
 * 模板变量（底稿 §3.1）由本类统一算好并且每个 stub 拿到的是同一套：$formColumns / $listColumns /
 * $searchColumns 的筛选口径只有这一处，模板里不得再自己过滤。唯一的例外是 $locale，只有
 * lang.stub.php 用得到，由 artifacts() 在两条语言包产物上单独补。
 *
 * 产物顺序即前端预览的页签顺序：前端取 Object.keys(data)[0] 作默认选中页签，所以 model 必须在最前。
 */
final class ModuleBlueprint
{
    /**
     * @param string $tablePrefix 数据库连接的表前缀（DB_PREFIX）。由 GeneratorService 从
     *                            config('database.connections.mysql.prefix') 读出来传进来：
     *                            core/ 保持纯逻辑可测，读配置是 app/ 的编排职责。默认 ''
     *                            让既有调用点与测试不受影响（当前开发库前缀为空）。
     */
    public function __construct(
        public readonly TableDefinition $table,
        public readonly GeneratorRequest $request,
        public readonly string $tablePrefix = '',
    ) {
    }

    /** @return array<string, array{path: string, base: 'server'|'repo', stub: string, vars: array<string, mixed>}> */
    public function artifacts(): array
    {
        // kebab() / snake() 与表前缀无关（只有 modelFromTable() 用前缀），这里用默认构造即可
        $convention = new NameConvention();
        $module = $this->request->moduleName;
        $model = $this->request->modelName;
        $kebab = $convention->kebab($model);
        $vars = $this->vars($convention->snake($model), $kebab);

        return [
            'model'      => $this->server("app/model/{$module}/{$model}.php", 'model.stub.php', $vars),
            'repository' => $this->server("app/repository/{$module}/{$model}Repository.php", 'repository.stub.php', $vars),
            'service'    => $this->server("app/service/{$module}/{$model}Service.php", 'service.stub.php', $vars),
            'controller' => $this->server("app/adminapi/controller/{$module}/{$model}Controller.php", 'controller.stub.php', $vars),
            'route'      => $this->server("config/route/{$module}.php", 'route.stub.php', $vars),
            'lang_zh'    => $this->server("resource/lang/zh_CN/{$module}.php", 'lang.stub.php', ['locale' => 'zh_CN'] + $vars),
            'lang_en'    => $this->server("resource/lang/en/{$module}.php", 'lang.stub.php', ['locale' => 'en'] + $vars),
            'api'        => $this->repo("admin/src/api/{$kebab}.ts", 'api.stub.php', $vars),
            'page'       => $this->repo("admin/src/views/{$module}/{$kebab}/index.vue", 'page.stub.php', $vars),
            'form'       => $this->repo("admin/src/views/{$module}/{$kebab}/components/{$model}Form.vue", 'form.stub.php', $vars),
            'menu'       => $this->server("database/generated/{$module}-menu.sql", 'menu.stub.php', $vars),
        ];
    }

    /**
     * 所有 stub 共用的上下文（底稿 §3.1），不得增删。
     *
     * @return array<string, mixed>
     */
    private function vars(string $modelSnake, string $modelKebab): array
    {
        $columns = $this->table->columns;
        // 请求里没填中文说明时退回表注释（与 make:crud 的 --comment 缺省行为一致，spec §5.5）
        $tableComment = $this->request->tableComment !== '' ? $this->request->tableComment : $this->table->comment;

        return [
            'module'        => $this->request->moduleName,
            'model'         => $this->request->modelName,
            'modelSnake'    => $modelSnake,
            'modelKebab'    => $modelKebab,
            'tableName'     => $this->table->name,
            // Eloquent 的连接层会给 Model::$table 再套一次 DB_PREFIX，所以模型模板只能写裸表名，
            // 写物理表名会被双重加前缀（查询 yd_yd_articles）。别处（注释、Repository 的说明）仍用
            // 物理名 $tableName，那是对的：那些地方描述的就是磁盘上那张表。
            'bareTableName' => (new NameConvention($this->tablePrefix))->bareTableName($this->table->name),
            'tableComment'  => $tableComment,
            // table_comment 是自由文本（前端表单，或 SHOW TABLE STATUS 的表注释），会落进
            // 三种完全不同的字面量上下文，转义规则各不相同。统一在这里按落点派生，模板只许
            // 取对应的那个变量——模板里再也不得直接输出 $tableComment（见各 stub 的注释）。
            'tableCommentPhpDoc' => $this->phpDocComment($tableComment),
            'tableCommentHtml'   => $this->htmlComment($tableComment),
            'tableCommentJs'     => $this->jsComment($tableComment),
            'tableCommentSql'    => $this->sqlComment($tableComment),
            'table'         => $this->table,
            'columns'       => $columns,
            'formColumns'   => array_values(array_filter($columns, static fn (ColumnDescriptor $c): bool => $c->inForm)),
            'listColumns'   => array_values(array_filter($columns, static fn (ColumnDescriptor $c): bool => $c->inList)),
            'searchColumns' => array_values(array_filter($columns, static fn (ColumnDescriptor $c): bool => $c->searchable)),
            'hasStatus'     => $this->table->hasStatus(),
            'softDeletes'   => $this->table->hasSoftDeletes(),
            // 裁定（推翻计划原公式 creatorColumn||deptColumn）：dataScoped 只认「有创建人列」。
            // core\base\Repository::$ownerColumn 默认非空的 'created_by'，DataScopeScope 在
            // self 快照下无条件 orWhere($owner, ...)；只有 dept_id、没有 created_by 的表若判成
            // 受控，生成的 Repository 会对一个不存在的列拼 SQL，直接报错。要支持「仅部门」的
            // 数据权限得先改基类语义，超出生成器模板的范围，所以这类表宁可默认不受控（模板里
            // 会写明原因），也不生成一跑就错的代码。
            'dataScoped'    => $this->table->creatorColumn() !== null,
            'creatorColumn' => $this->table->creatorColumn(),
            'deptColumn'    => $this->table->deptColumn(),
            'uniqueColumns' => $this->table->uniqueColumns(),
            'primaryKey'    => $this->table->primaryKey(),
            'inference'     => new TypeInference(),
        ];
    }

    /**
     * PHP 文档注释里的表说明：剥掉「星号加斜杠」（唯一能提前闭合文档注释的序列）与全部换行/控制字符。
     *
     * 不转义、直接剥掉：这个序列在中文说明里没有任何合法用途，而一旦漏出去，注入点就落在
     * `class` 之前的顶层作用域——那是合法 PHP，`php -l`、phpstan、cs-fixer 四道静态门禁
     * 全都拦不住，文件一被 autoload 就执行。换行同理：文档注释是逐行 ` * ` 前缀的，
     * 一个换行就能让后半段脱离注释。
     *
     * 两处细节都是被实测绕过过的，改动前先读完：
     *
     * 1. **必须循环到不动点**，单遍替换挡不住「重组」：`str_replace()` 不回扫自己的替换结果，
     *    所以「两个星号接两个斜杠」删掉中间那一对之后，左边剩的 `*` 与右边剩的 `/` 又贴成了
     *    一个新的闭合序列，原样复现顶层可执行 PHP。每一轮严格缩短字符串，循环必然终止。
     *    同理不能用没有 `+` 量词的正则，它有一模一样的重组问题。
     * 2. **顺序不能颠倒**：先剥换行与控制字符，再剥闭合序列。反过来的话「星号 + 换行 + 斜杠」
     *    在第一步匹配不到，等换行被删掉时两半正好粘成闭合序列，照样漏出去。
     *
     * 控制字符按**字节**剥（正则不加 `/u`）：UTF-8 多字节序列的每个字节都 ≥ 0x80，碰不到
     * \x00-\x1F 这一段，中文不会被拆坏；而加了 `/u` 的正则遇到非法 UTF-8 会整体返回 null，
     * 那样一段编码不干净的表说明就会整个变成空串。
     */
    private function phpDocComment(string $comment): string
    {
        $value = (string) preg_replace('/[\x00-\x1F\x7F]+/', '', $comment);

        while (str_contains($value, '*/')) {
            $value = str_replace('*/', '', $value);
        }

        return $value;
    }

    /**
     * HTML 文本节点与 HTML 属性值里的表说明：htmlspecialchars。
     *
     * 这个落点真正危险的是 `<` 与 `>`（能开出一个新标签），而不是引号——`tableCommentJs`
     * 在这里帮不上忙：它不碰尖括号，反倒会把撇号转成 `\'` 直接显示在页面标题上。
     * 反过来 `tableCommentHtml` 用在 `:title="..."` 那种 Vue 表达式里也不对：那里要的是
     * JS 字符串字面量的转义，实体化只会让标题显示成 `&#039;`。两者不可互换。
     *
     * ENT_QUOTES：单双引号一并实体化，这样同一个变量也能安全地放进 HTML 属性值。
     * ENT_SUBSTITUTE：非法 UTF-8 字节替换成 U+FFFD，而不是让整个函数返回空串——
     * 表说明可能来自 SHOW TABLE STATUS，编码不干净时宁可显示成问号也不能整段消失。
     */
    private function htmlComment(string $comment): string
    {
        return htmlspecialchars($comment, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Vue 模板里的表说明。落点（form.stub.php 的 `:title="form.id ? '编辑X' : '新增X'"`）
     * **同时处于两层上下文**：外层是双引号 HTML 属性，内层是单引号 JS 字符串字面量。
     *
     * 所以要按两层依次转义，而且顺序是「先 JS 后 HTML」——正好和解析顺序相反：Vue 编译模板时
     * 先把属性值做一次 HTML 解码，再把解码结果当 JS 表达式解析，所以内层的转义必须先加，
     * 才能在解码之后原样留在 JS 那一层。只做内层是不够的：表说明里一个双引号就会提前闭合属性，
     * 后面的内容变成挂在 `<el-dialog>` 上的裸属性（例如写成 `" @vue:mounted="…`）。
     *
     * 第一层内部的顺序同样不能反：先处理 `'` 的话，转义 `\` 那一步会把刚加上的反斜杠又转义一遍，
     * 等于没转义。一个普通的中文注释写成「用户's 列表」就会打断这个表达式，不需要恶意输入。
     *
     * ENT_SUBSTITUTE 不能漏：非法 UTF-8 字节要替换成 U+FFFD，而不是让整段表说明变成空串——
     * 表说明可能直接来自 SHOW TABLE STATUS，编码不干净时宁可显示成问号也不能整段消失。
     */
    private function jsComment(string $comment): string
    {
        $escaped = str_replace(["\r", "\n"], '', $comment);
        $escaped = str_replace('\\', '\\\\', $escaped);
        $escaped = str_replace("'", "\\'", $escaped);

        return htmlspecialchars($escaped, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * SQL 字符串字面量里的表说明：先转义 `\` 再转义 `'`（同样不能反过来）。
     *
     * MySQL 默认不开 NO_BACKSLASH_ESCAPES，`\` 是转义符，所以只把 `'` 翻倍是不够的：
     * 一个以 `\` 结尾的说明会产出 `'x\'`，把后面的字段一路吃进字符串里，整条 INSERT 错位。
     * 这份 SQL 是设计上要人手工执行的（spec §12），错位不会有任何人替它兜底。
     */
    private function sqlComment(string $comment): string
    {
        return str_replace(['\\', "'"], ['\\\\', "''"], $comment);
    }

    /**
     * @param array<string, mixed> $vars
     * @return array{path: string, base: 'server'|'repo', stub: string, vars: array<string, mixed>}
     */
    private function server(string $relative, string $stub, array $vars): array
    {
        return ['path' => $relative, 'base' => 'server', 'stub' => $stub, 'vars' => $vars];
    }

    /**
     * @param array<string, mixed> $vars
     * @return array{path: string, base: 'server'|'repo', stub: string, vars: array<string, mixed>}
     */
    private function repo(string $relative, string $stub, array $vars): array
    {
        return ['path' => $relative, 'base' => 'repo', 'stub' => $stub, 'vars' => $vars];
    }
}
