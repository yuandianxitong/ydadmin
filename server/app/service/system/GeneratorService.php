<?php

declare(strict_types=1);

namespace app\service\system;

use app\repository\system\SchemaRepository;
use core\base\Service;
use core\exception\BusinessException;
use core\exception\ValidationException;
use core\generator\ArtifactWriter;
use core\generator\ColumnDescriptor;
use core\generator\GeneratorRequest;
use core\generator\ModuleBlueprint;
use core\generator\TableDefinition;
use core\generator\TemplateRenderer;
use core\generator\TypeInference;
use DI\Attribute\Inject;

/**
 * 代码生成器编排层（spec §5.3）：选表、取字段两个只读步骤，以及 preview() / generate() 两个写步骤。
 *
 * 写步骤的三条安全线都落在本类而不是控制器：make:crud 命令不经过 HTTP 管道，两个入口必须继承同一套
 * 判定——生产禁用（assertWritesEnabled）、名称白名单（assertNames）、表名白名单（tableMeta）。
 */
class GeneratorService extends Service
{
    /**
     * 名称白名单（spec §9.1）。module_name 与 model_name 会变成文件路径与 PHP 命名空间，
     * 而前端对它们不做任何校验（自由文本）。GeneratorController::generatorRules() 里内联了
     * 同样的两条正则与下面的保留字清单；红线 Test16 对两条路径各测一遍，防止只改一处。
     */
    private const MODULE_PATTERN = '/^[a-z][a-z0-9_]{0,30}$/';

    private const MODEL_PATTERN = '/^[A-Z][A-Za-z0-9]{0,40}$/';

    /**
     * 保留模块名。生成的语言包落在 resource/lang/{locale}/{module}.php，模块名撞上仓库现有的
     * 语言分组，这个产物就会被判「已存在」而跳过（生成器只创建新文件，spec 决策 3），于是生成
     * 代码里所有 lang() 都取不到翻译、原样回显 key——不报错、也没有任何线索指向原因，只能在
     * 入口挡住。清单 = resource/lang/zh_CN 下的现有分组 + 生成器自己的 generator。
     *
     * 前端模块名的默认值恰好就是 business（generator/index.vue 里硬编码），用户不填直接下一步
     * 就会踩中，所以这条不是理论风险。
     */
    private const RESERVED_MODULES = ['admin_log', 'auth', 'business', 'messages', 'validation', 'generator', 'apidoc'];

    #[Inject]
    protected SchemaRepository $schemaRepository;

    #[Inject]
    protected TypeInference $typeInference;

    /**
     * @return list<array{name: string, comment: string, engine: string, rows: int}>
     */
    public function getTables(): array
    {
        return $this->schemaRepository->listTables();
    }

    /**
     * 十二字段的 ColumnDescriptor::toArray() 列表（契约 §4.2）。表不存在时抛业务异常。
     *
     * 与 preview()/generate() 共用 columnsOf()，「查表 + 推断 + 盖开关」只有一处实现：
     * 前端第二步看到的字段开关，就是后续渲染真正用的那一套。
     *
     * @return list<array<string, mixed>>
     */
    public function getColumns(string $table): array
    {
        $meta = $this->tableMeta($table);

        return array_map(
            static fn (ColumnDescriptor $column): array => $column->toArray(),
            $this->columnsOf($meta['name'], [])
        );
    }

    /**
     * 预览：渲染但不落盘。返回顺序即底稿 §4 的产物顺序，前端拿 Object.keys(data)[0] 作默认页签。
     *
     * @return array<string, array{path: string, content: string}>
     */
    public function preview(GeneratorRequest $request): array
    {
        $this->assertWritesEnabled();

        $result = [];
        foreach ($this->buildArtifacts($request) as $key => $artifact) {
            $result[$key] = ['path' => $this->displayPath($artifact['base'], $artifact['path']), 'content' => $artifact['content']];
        }

        return $result;
    }

    /**
     * 生成：buildArtifacts() 再落盘，没有第二条渲染路径（spec §5.3）。
     * 「预览即所得」（spec §11.3）靠的就是两个端点渲染的是同一份字节。
     *
     * @return array{files: list<array{path: string, status: string, reason?: string}>}
     */
    public function generate(GeneratorRequest $request): array
    {
        $this->assertWritesEnabled();

        // 第一阶段：十个产物全部渲染成功才往下走；任一渲染抛异常则整批不落盘
        $artifacts = $this->buildArtifacts($request);

        $toWrite = [];
        foreach ($artifacts as $key => $artifact) {
            $toWrite[$key] = ['path' => $this->absolutePath($artifact['base'], $artifact['path']), 'content' => $artifact['content']];
        }

        // 第二阶段：落盘，控制器最后写，单文件失败不中断整批，已写的不回滚
        $statuses = (new ArtifactWriter())->write($toWrite);

        $files = [];
        foreach ($statuses as $key => $status) {
            $file = ['path' => $this->displayPath($artifacts[$key]['base'], $artifacts[$key]['path']), 'status' => $status['status']];
            if ($status['reason'] !== '') {
                $file['reason'] = $status['reason'];
            }
            $files[] = $file;
        }

        return [
            'files' => $files,
        ];
    }

    /**
     * 产物的磁盘绝对路径（make:crud 的 --force 专用）。preview() 返回的 path 是给前端展示用的
     * 相对路径（displayPath()，相对仓库根，见该方法注释），不能直接拿去 is_file()/unlink()——
     * CLI 的工作目录是 server/，"server/app/..." 这种相对路径会被解析成 "server/server/app/..."，
     * 悄悄找不到任何已存在的文件，--force 就变成了一个不起作用的开关。这里复用 buildArtifacts()
     * 与 absolutePath()（跟 generate() 落盘用的是同一份），只是换一种形态返回，preview()/generate()
     * 的返回值签名不受影响。
     *
     * @return array<string, string> key => 绝对路径
     */
    public function targetPaths(GeneratorRequest $request): array
    {
        $this->assertWritesEnabled();

        $result = [];
        foreach ($this->buildArtifacts($request) as $key => $artifact) {
            $result[$key] = $this->absolutePath($artifact['base'], $artifact['path']);
        }

        return $result;
    }

    /**
     * 产物落盘的基准：后端在 server/，前端在仓库根。做成 protected 方法而不是常量或属性，
     * 是为了让测试用子类把产物打到 runtime 下的临时目录（spec §11.2「产物写在测试临时目录里」）——
     * Service 是容器单例，不允许为此加可变属性。
     */
    protected function serverRoot(): string
    {
        return base_path();
    }

    protected function repoRoot(): string
    {
        return dirname(base_path());
    }

    /**
     * 唯一的渲染路径（spec §5.3）：preview() 与 generate() 都只经过这里。
     * 十个产物全部渲染成功才返回，任一渲染抛异常就整批失败、一个字节都不落盘。
     *
     * @return array<string, array{base: string, path: string, content: string}>
     */
    private function buildArtifacts(GeneratorRequest $request): array
    {
        $this->assertNames($request);

        // 表前缀由这一层读配置传进去：core\generator 保持纯逻辑、不碰 config()，配置读取是编排层的职责。
        // 生成的 Model::$table 必须是裸表名——Eloquent 的连接层会再套一次前缀，写物理表名会被加两次。
        $blueprint = new ModuleBlueprint(
            $this->describeTable($request),
            $request,
            (string) config('database.connections.mysql.prefix', ''),
        );
        // stub 目录跟着代码走，不跟着可被测试覆写的落盘基准走
        $renderer = new TemplateRenderer(base_path('core/generator/stubs'));

        $artifacts = [];
        foreach ($blueprint->artifacts() as $key => $artifact) {
            try {
                $content = $renderer->render($artifact['stub'], $artifact['vars']);
            } catch (\Throwable $e) {
                throw new BusinessException(lang('generator.render_failed') . "：{$key}（{$e->getMessage()}）", 400, $e);
            }
            // 只留权威形态（基准 + 相对路径）；绝对路径与展示路径都在用到的地方现算，不存第二份
            $artifacts[$key] = [
                'base'    => $artifact['base'],
                'path'    => $artifact['path'],
                'content' => $content,
            ];
        }

        return $artifacts;
    }

    /**
     * 生产禁用（spec §4.5 / §9.3）：持有 system.generator.generate 等于拥有往服务器写 PHP 文件的
     * 能力，这是远程写代码的原语。tables / columns 两个只读端点不受影响。
     */
    private function assertWritesEnabled(): void
    {
        if (!(bool) config('app.debug')) {
            throw new BusinessException(lang('generator.disabled_in_production'));
        }
    }

    /**
     * 名称白名单（spec §9.1）。控制器已用同样的正则校验过一次，这里是第二道：
     * make:crud 不经过控制器，而这两个字符串会直接变成文件路径与命名空间。
     */
    private function assertNames(GeneratorRequest $request): void
    {
        $errors = [];
        if (preg_match(self::MODULE_PATTERN, $request->moduleName) !== 1) {
            $errors['module_name'] = lang('generator.invalid_module_name');
        } elseif (in_array($request->moduleName, self::RESERVED_MODULES, true)) {
            $errors['module_name'] = lang('generator.module_name_reserved');
        }
        if (preg_match(self::MODEL_PATTERN, $request->modelName) !== 1) {
            $errors['model_name'] = lang('generator.invalid_model_name');
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    /** 实时查表得到表定义；表注释取 SHOW TABLE STATUS 的值。 */
    private function describeTable(GeneratorRequest $request): TableDefinition
    {
        $meta = $this->tableMeta($request->tableName);

        return new TableDefinition($meta['name'], $meta['comment'], $this->columnsOf($meta['name'], $request->overrides));
    }

    /**
     * 表名白名单（spec §9.2）：与 listTables() 的结果逐字比对，命中才继续。
     * 不命中一律 generator.table_not_found，绝不把请求里的 table 拼进任何语句。
     *
     * @return array{name: string, comment: string, engine: string, rows: int}
     */
    private function tableMeta(string $table): array
    {
        foreach ($this->schemaRepository->listTables() as $row) {
            if ($row['name'] === $table) {
                return $row;
            }
        }

        throw new BusinessException(lang('generator.table_not_found'));
    }

    /**
     * 列定义：实时查表推断，再把客户端回传的四个可编辑字段盖上去（spec §9.4）。
     * 客户端传来的 name / raw_type / nullable / default / key / extra 一律不采信——用它们渲染代码
     * 等于让客户端控制生成内容。overrides 里出现表中不存在的列名时忽略该项（§4.3）。
     *
     * @param array<string, array{form_type: string, searchable: bool, in_list: bool, in_form: bool}> $overrides
     * @return list<ColumnDescriptor>
     */
    private function columnsOf(string $table, array $overrides): array
    {
        $columns = [];
        foreach ($this->schemaRepository->listColumns($table) as $raw) {
            $column = $this->typeInference->describe($raw);
            $override = $overrides[$column->name] ?? null;
            $columns[] = $override === null
                ? $column
                : $column->withOverrides(
                    $override['form_type'],
                    $override['searchable'],
                    $override['in_list'],
                    $override['in_form'],
                );
        }

        return $columns;
    }

    /** 落盘用的绝对路径：由「基准 + 相对路径」现算，不在别处存第二份。 */
    private function absolutePath(string $base, string $path): string
    {
        return ($base === 'server' ? $this->serverRoot() : $this->repoRoot()) . '/' . $path;
    }

    /**
     * 响应里给相对仓库根的路径（server/app/... 与 admin/src/...），同样由基准现算，
     * 既不把服务器绝对路径发给客户端，也不需要为展示单独存一份路径。
     */
    private function displayPath(string $base, string $path): string
    {
        return $base === 'server' ? 'server/' . $path : $path;
    }
}
