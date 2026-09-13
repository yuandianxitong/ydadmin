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
    public function __construct(
        public readonly TableDefinition $table,
        public readonly GeneratorRequest $request,
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

        return [
            'module'        => $this->request->moduleName,
            'model'         => $this->request->modelName,
            'modelSnake'    => $modelSnake,
            'modelKebab'    => $modelKebab,
            'tableName'     => $this->table->name,
            // 请求里没填中文说明时退回表注释（与 make:crud 的 --comment 缺省行为一致，spec §5.5）
            'tableComment'  => $this->request->tableComment !== '' ? $this->request->tableComment : $this->table->comment,
            'table'         => $this->table,
            'columns'       => $columns,
            'formColumns'   => array_values(array_filter($columns, static fn (ColumnDescriptor $c): bool => $c->inForm)),
            'listColumns'   => array_values(array_filter($columns, static fn (ColumnDescriptor $c): bool => $c->inList)),
            'searchColumns' => array_values(array_filter($columns, static fn (ColumnDescriptor $c): bool => $c->searchable)),
            'hasStatus'     => $this->table->hasStatus(),
            'softDeletes'   => $this->table->hasSoftDeletes(),
            // spec §7.3：created_by 或 dept_id 任一存在即受控；两列都没有则 false 且显式 creatorColumn = null
            'dataScoped'    => $this->table->creatorColumn() !== null || $this->table->deptColumn() !== null,
            'creatorColumn' => $this->table->creatorColumn(),
            'deptColumn'    => $this->table->deptColumn(),
            'uniqueColumns' => $this->table->uniqueColumns(),
            'primaryKey'    => $this->table->primaryKey(),
            'inference'     => new TypeInference(),
        ];
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
