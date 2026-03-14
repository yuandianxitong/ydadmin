<?php
declare(strict_types=1);

namespace app\service\system;

use core\base\Service;
use think\facade\Db;

class CodeGeneratorService extends Service
{
    /**
     * 获取所有数据表
     */
    public function getTables(): array
    {
        $tables = Db::query('SHOW TABLE STATUS');
        $result = [];
        foreach ($tables as $table) {
            $result[] = [
                'name'    => $table['Name'],
                'comment' => $table['Comment'] ?: $table['Name'],
                'engine'  => $table['Engine'],
                'rows'    => $table['Rows'],
            ];
        }
        return $result;
    }

    /**
     * 获取表字段信息
     */
    public function getTableColumns(string $tableName): array
    {
        $columns = Db::query("SHOW FULL COLUMNS FROM `{$tableName}`");
        $result = [];
        foreach ($columns as $col) {
            $result[] = [
                'name'       => $col['Field'],
                'type'       => $this->parseColumnType($col['Type']),
                'raw_type'   => $col['Type'],
                'nullable'   => $col['Null'] === 'YES',
                'default'    => $col['Default'],
                'comment'    => $col['Comment'] ?: $col['Field'],
                'key'        => $col['Key'],
                'extra'      => $col['Extra'],
                'form_type'  => $this->guessFormType($col['Field'], $col['Type']),
                'searchable' => $this->isSearchable($col['Field'], $col['Type']),
                'in_list'    => $this->shouldShowInList($col['Field']),
                'in_form'    => $this->shouldShowInForm($col['Field']),
            ];
        }
        return $result;
    }

    /**
     * 生成代码
     */
    public function generate(array $config): array
    {
        $tableName   = $config['table_name'];
        $moduleName  = $config['module_name'];
        $modelName   = $config['model_name'];
        $columns     = $config['columns'] ?? [];
        $tableComment = $config['table_comment'] ?? $modelName;

        if (empty($columns)) {
            $columns = $this->getTableColumns($tableName);
        }

        $hasStatus = $this->hasColumn($columns, 'status');
        $kebab = $this->toKebab($modelName);

        $files = [];

        // 1. Model
        $files['model'] = [
            'path'    => "app/model/{$moduleName}/{$modelName}.php",
            'content' => $this->generateModel($tableName, $moduleName, $modelName, $columns),
        ];

        // 2. Repository
        $files['repository'] = [
            'path'    => "app/repository/{$moduleName}/{$modelName}Repository.php",
            'content' => $this->generateRepository($moduleName, $modelName, $columns),
        ];

        // 3. Service
        $files['service'] = [
            'path'    => "app/service/{$moduleName}/{$modelName}Service.php",
            'content' => $this->generateService($moduleName, $modelName, $columns, $hasStatus),
        ];

        // 4. Controller
        $files['controller'] = [
            'path'    => "app/adminapi/controller/v1/{$moduleName}/{$modelName}Controller.php",
            'content' => $this->generateController($moduleName, $modelName, $tableComment, $columns, $hasStatus),
        ];

        // 5. Validate
        $files['validate'] = [
            'path'    => "app/adminapi/validate/v1/{$moduleName}/{$modelName}Validate.php",
            'content' => $this->generateValidate($moduleName, $modelName, $columns),
        ];

        // 6. Route
        $files['route'] = [
            'path'    => "app/adminapi/route/{$moduleName}.php",
            'content' => $this->generateRoute($moduleName, $modelName, $hasStatus),
        ];

        // 7. 前端API
        $files['api'] = [
            'path'    => "admin/src/api/{$kebab}.ts",
            'content' => $this->generateFrontendApi($moduleName, $modelName, $hasStatus, $columns),
        ];

        // 8. 前端列表页
        $files['page'] = [
            'path'    => "admin/src/views/{$moduleName}/{$kebab}/index.vue",
            'content' => $this->generateFrontendPage($moduleName, $modelName, $columns, $tableComment, $hasStatus),
        ];

        // 9. 前端表单组件
        $files['form'] = [
            'path'    => "admin/src/views/{$moduleName}/{$kebab}/components/{$modelName}Form.vue",
            'content' => $this->generateFrontendForm($moduleName, $modelName, $columns, $tableComment),
        ];

        // 10. Migration
        $timestamp = date('YmdHis');
        $files['migration'] = [
            'path'    => "database/migrations/{$timestamp}_create_{$tableName}_table.php",
            'content' => $this->generateMigration($tableName, $columns),
        ];

        return $files;
    }

    /**
     * 写入生成的文件
     */
    public function writeFiles(array $files): array
    {
        $written = [];
        $basePath = root_path();

        foreach ($files as $key => $file) {
            // 路由走追加逻辑
            if ($key === 'route') {
                $result = $this->appendRoute($file['path'], $file['content']);
                $written[] = $result;
                continue;
            }

            // 前端文件路径需要往上一级（server 同级的 admin）
            $fullPath = $basePath . $file['path'];
            if (str_starts_with($file['path'], 'admin/')) {
                $fullPath = dirname($basePath) . '/' . $file['path'];
            }

            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            if (file_exists($fullPath)) {
                $written[] = ['path' => $file['path'], 'status' => 'skipped', 'reason' => '文件已存在'];
                continue;
            }

            file_put_contents($fullPath, $file['content']);
            $written[] = ['path' => $file['path'], 'status' => 'created'];
        }

        return $written;
    }

    /**
     * 追加路由到路由文件
     */
    protected function appendRoute(string $routeFile, string $routeContent): array
    {
        $basePath = root_path();
        $fullPath = $basePath . $routeFile;

        if (!file_exists($fullPath)) {
            // 创建新的路由文件
            $moduleName = basename($routeFile, '.php');
            $newFile = "<?php\nuse think\\facade\\Route;\n\n"
                . "Route::group('{$moduleName}', function () {\n\n"
                . $routeContent . "\n\n"
                . "})->middleware(['admin_auth', 'admin_permission', 'admin_log']);\n";
            file_put_contents($fullPath, $newFile);
            return ['path' => $routeFile, 'status' => 'created'];
        }

        $existing = file_get_contents($fullPath);

        // 检查路由是否已存在（通过 Controller 名称判断）
        if (preg_match('/Controller\/index/', $routeContent, $m)) {
            $ctrlName = $m[0] ?? '';
            if ($ctrlName && str_contains($existing, $ctrlName)) {
                return ['path' => $routeFile, 'status' => 'skipped', 'reason' => '路由已存在'];
            }
        }

        // 在最后一个 })->middleware 之前插入新路由
        $pattern = '/(\n\}\)->middleware\(\[.*?\]\);)/s';
        if (preg_match($pattern, $existing)) {
            $replacement = "\n\n" . $routeContent . '$1';
            $newContent = preg_replace($pattern, $replacement, $existing, 1);
            file_put_contents($fullPath, $newContent);
            return ['path' => $routeFile, 'status' => 'appended'];
        }

        return ['path' => $routeFile, 'status' => 'skipped', 'reason' => '无法定位插入位置，请手动添加'];
    }

    // ========== 后端代码生成 ==========

    protected function generateModel(string $table, string $module, string $model, array $columns): string
    {
        $fillable = [];
        $typeMap = [];
        $hasStatus = false;

        foreach ($columns as $col) {
            $name = $col['name'];
            if (in_array($name, ['id', 'created_at', 'updated_at', 'deleted_at'])) continue;
            $fillable[] = "'{$name}'";

            if ($name === 'status') $hasStatus = true;

            $phpType = $this->toPhpType($col['type']);
            if ($phpType !== 'string') {
                $typeMap[] = "        '{$name}' => '{$phpType}',";
            }
        }

        $fillableStr = $this->wrapArray($fillable, 8);
        $typeStr = $typeMap ? "\n" . implode("\n", $typeMap) . "\n    " : '';

        $statusAccessor = '';
        if ($hasStatus) {
            $statusAccessor = <<<'PHP'


    /**
     * 状态文本
     */
    public function getStatusTextAttr($value, $data): string
    {
        return $this->getStatusText($data['status'] ?? 0, [1 => '正常', 0 => '禁用']);
    }
PHP;
        }

        return <<<PHP
<?php
declare(strict_types=1);

namespace app\\model\\{$module};

use core\\base\\Model;

class {$model} extends Model
{
    protected \$table = '{$table}';

    protected \$fillable = [{$fillableStr}];

    protected \$type = [{$typeStr}];{$statusAccessor}
}
PHP;
    }

    protected function generateRepository(string $module, string $model, array $columns): string
    {
        return <<<PHP
<?php
declare(strict_types=1);

namespace app\\repository\\{$module};

use app\\model\\{$module}\\{$model};
use core\\base\\Repository;
use think\\Model as ThinkModel;

class {$model}Repository extends Repository
{
    protected function getModel(): ThinkModel
    {
        return new {$model}();
    }

    /**
     * 分页列表
     */
    public function getPageList(array \$where = [], int \$page = 1, int \$limit = 15): array
    {
        \$query = \$this->model->where(\$where);

        \$total = \$query->count();
        \$list = \$query->page(\$page, \$limit)
            ->order('id desc')
            ->select()
            ->toArray();

        return [
            'list'       => \$list,
            'pagination' => [
                'current_page' => \$page,
                'per_page'     => \$limit,
                'total'        => \$total,
                'last_page'    => (int)ceil(\$total / \$limit),
            ],
        ];
    }
}
PHP;
    }

    protected function generateService(string $module, string $model, array $columns, bool $hasStatus): string
    {
        $searchFields = [];
        foreach ($columns as $col) {
            if (!empty($col['searchable']) && !in_array($col['name'], ['id', 'status'])) {
                $searchFields[] = $col['name'];
            }
        }
        $repoVar = lcfirst($model) . 'Repo';

        // keyword 搜索条件
        $keywordSearch = '';
        if (!empty($searchFields)) {
            $searchFieldStr = implode('|', $searchFields);
            $keywordSearch = <<<PHP

        if (!empty(\$params['keyword'])) {
            \$where[] = ['{$searchFieldStr}', 'like', '%' . \$params['keyword'] . '%'];
        }
PHP;
        }

        // status 搜索条件
        $statusSearch = '';
        if ($hasStatus) {
            $statusSearch = <<<PHP

        if (isset(\$params['status']) && \$params['status'] !== '') {
            \$where[] = ['status', '=', (int)\$params['status']];
        }
PHP;
        }

        // create / update 字段
        $createFields = [];
        $updateFields = [];
        foreach ($columns as $col) {
            if (empty($col['in_form'])) continue;
            $name = $col['name'];
            $default = $this->getDefaultValue($col);
            $createFields[] = "            '{$name}' => \$data['{$name}'] ?? {$default},";
            $updateFields[] = "            '{$name}' => \$data['{$name}'] ?? null,";
        }
        $createStr = implode("\n", $createFields);
        $updateStr = implode("\n", $updateFields);

        // status 方法
        $statusMethod = '';
        if ($hasStatus) {
            $statusMethod = <<<PHP


    /**
     * 更新状态
     */
    public function updateStatus(int \$id, int \$status): bool
    {
        \$record = \$this->{$repoVar}->find(\$id);
        if (!\$record) {
            throw new BusinessException(lang('business.record_not_found'));
        }
        return \$this->{$repoVar}->update(\$id, ['status' => \$status]);
    }
PHP;
        }

        return <<<PHP
<?php
declare(strict_types=1);

namespace app\\service\\{$module};

use app\\repository\\{$module}\\{$model}Repository;
use core\\base\\Service;
use core\\exception\\BusinessException;

class {$model}Service extends Service
{
    protected {$model}Repository \${$repoVar};

    /**
     * 获取列表
     */
    public function getList(array \$params): array
    {
        \$where = [];
{$keywordSearch}{$statusSearch}

        \$page  = (int)(\$params['page'] ?? 1);
        \$limit = (int)(\$params['limit'] ?? 15);

        return \$this->{$repoVar}->getPageList(\$where, \$page, \$limit);
    }

    /**
     * 获取详情
     */
    public function getDetail(int \$id): array
    {
        \$result = \$this->{$repoVar}->find(\$id);
        if (!\$result) {
            throw new BusinessException(lang('business.record_not_found'));
        }
        return \$result;
    }

    /**
     * 创建
     */
    public function create(array \$data): array
    {
        return \$this->{$repoVar}->create([
{$createStr}
        ]);
    }

    /**
     * 更新
     */
    public function update(int \$id, array \$data): bool
    {
        \$record = \$this->{$repoVar}->find(\$id);
        if (!\$record) {
            throw new BusinessException(lang('business.record_not_found'));
        }

        \$updateData = array_filter([
{$updateStr}
        ], fn(\$v) => \$v !== null);

        return \$this->{$repoVar}->update(\$id, \$updateData);
    }

    /**
     * 删除
     */
    public function delete(int \$id): bool
    {
        \$record = \$this->{$repoVar}->find(\$id);
        if (!\$record) {
            throw new BusinessException(lang('business.record_not_found'));
        }
        return \$this->{$repoVar}->delete(\$id);
    }{$statusMethod}
}
PHP;
    }

    protected function generateController(string $module, string $model, string $comment, array $columns, bool $hasStatus): string
    {
        $serviceVar = lcfirst($model) . 'Service';
        $kebab = $this->toKebab($model);

        // OpenAPI 参数
        $listParams = [];
        $searchFields = [];
        foreach ($columns as $col) {
            if (!empty($col['searchable']) && !in_array($col['name'], ['id', 'status'])) {
                $searchFields[] = $col['name'];
            }
        }
        $listParams[] = "            new OA\\Parameter(name: 'page', in: 'query', description: '页码', schema: new OA\\Schema(type: 'integer', default: 1)),";
        $listParams[] = "            new OA\\Parameter(name: 'limit', in: 'query', description: '每页数量', schema: new OA\\Schema(type: 'integer', default: 15)),";
        if (!empty($searchFields)) {
            $listParams[] = "            new OA\\Parameter(name: 'keyword', in: 'query', description: '关键词搜索', schema: new OA\\Schema(type: 'string')),";
        }
        if ($hasStatus) {
            $listParams[] = "            new OA\\Parameter(name: 'status', in: 'query', description: '状态(0禁用 1启用)', schema: new OA\\Schema(type: 'integer')),";
        }
        $listParamsStr = implode("\n", $listParams);

        // store requestBody properties
        $storeProps = [];
        $storeRequired = [];
        foreach ($columns as $col) {
            if (empty($col['in_form'])) continue;
            $name = $col['name'];
            $colComment = $col['comment'];
            $oaType = $this->toOAType($col['type']);
            $extra = '';
            if ($name === 'status') $extra = ", enum: [0, 1]";
            $storeProps[] = "                    new OA\\Property(property: '{$name}', type: '{$oaType}', description: '{$colComment}'{$extra}),";
            if (!$col['nullable'] && !in_array($name, ['status', 'sort'])) {
                $storeRequired[] = "'{$name}'";
            }
        }
        $storePropsStr = implode("\n", $storeProps);
        $storeRequiredStr = $storeRequired ? "required: [" . implode(', ', $storeRequired) . "],\n                " : '';

        // status 方法
        $statusMethod = '';
        if ($hasStatus) {
            $statusMethod = <<<PHP


    #[Permission('{$module}.{$kebab}.update')]
    #[OA\Put(
        path: '/{$module}/{$kebab}/{id}/status',
        summary: '更新{$comment}状态',
        security: [['bearerAuth' => []]],
        tags: ['{$comment}'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'status', type: 'integer', description: '状态(0禁用 1启用)', enum: [0, 1]),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: '更新成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function status(): Response
    {
        \$id = (int) \$this->request->param('id');
        \$status = (int) \$this->request->post('status', 0);
        \$this->{$serviceVar}->updateStatus(\$id, \$status);
        return \$this->success('状态更新成功');
    }
PHP;
        }

        return <<<PHP
<?php
declare(strict_types=1);

namespace app\\adminapi\\controller\\v1\\{$module};

use app\\service\\{$module}\\{$model}Service;
use app\\adminapi\\validate\\v1\\{$module}\\{$model}Validate;
use core\\base\\Controller;
use core\\attribute\\Permission;
use think\\Response;
use OpenApi\\Attributes as OA;

#[OA\\Tag(name: '{$comment}', description: '{$comment}管理')]
class {$model}Controller extends Controller
{
    protected {$model}Service \${$serviceVar};

    #[Permission('{$module}.{$kebab}.list')]
    #[OA\\Get(
        path: '/{$module}/{$kebab}',
        summary: '{$comment}列表',
        security: [['bearerAuth' => []]],
        tags: ['{$comment}'],
        parameters: [
{$listParamsStr}
        ],
        responses: [
            new OA\\Response(response: 200, description: '获取成功', content: new OA\\JsonContent(ref: '#/components/schemas/PaginatedResponse'))
        ]
    )]
    public function index(): Response
    {
        \$params = \$this->getRequestData();
        \$result = \$this->{$serviceVar}->getList(\$params);
        return \$this->paginate(\$result);
    }

    #[Permission('{$module}.{$kebab}.list')]
    #[OA\\Get(
        path: '/{$module}/{$kebab}/{id}',
        summary: '{$comment}详情',
        security: [['bearerAuth' => []]],
        tags: ['{$comment}'],
        parameters: [
            new OA\\Parameter(name: 'id', in: 'path', required: true, schema: new OA\\Schema(type: 'integer'))
        ],
        responses: [
            new OA\\Response(response: 200, description: '获取成功', content: new OA\\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function show(): Response
    {
        \$id = (int) \$this->request->param('id');
        \$result = \$this->{$serviceVar}->getDetail(\$id);
        return \$this->success('获取成功', \$result);
    }

    #[Permission('{$module}.{$kebab}.create')]
    #[OA\\Post(
        path: '/{$module}/{$kebab}',
        summary: '创建{$comment}',
        security: [['bearerAuth' => []]],
        tags: ['{$comment}'],
        requestBody: new OA\\RequestBody(
            required: true,
            content: new OA\\JsonContent(
                {$storeRequiredStr}properties: [
{$storePropsStr}
                ]
            )
        ),
        responses: [
            new OA\\Response(response: 200, description: '创建成功', content: new OA\\JsonContent(ref: '#/components/schemas/SuccessResponse')),
            new OA\\Response(response: 400, description: '验证失败', content: new OA\\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]
    public function store(): Response
    {
        \$data = \$this->request->post();
        \$this->validate(\$data, {$model}Validate::class, [], 'create');
        \$result = \$this->{$serviceVar}->create(\$data);
        return \$this->success('创建成功', \$result);
    }

    #[Permission('{$module}.{$kebab}.update')]
    #[OA\\Put(
        path: '/{$module}/{$kebab}/{id}',
        summary: '更新{$comment}',
        security: [['bearerAuth' => []]],
        tags: ['{$comment}'],
        parameters: [
            new OA\\Parameter(name: 'id', in: 'path', required: true, schema: new OA\\Schema(type: 'integer'))
        ],
        requestBody: new OA\\RequestBody(
            content: new OA\\JsonContent(properties: [
{$storePropsStr}
            ])
        ),
        responses: [
            new OA\\Response(response: 200, description: '更新成功', content: new OA\\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function update(): Response
    {
        \$id = (int) \$this->request->param('id');
        \$data = \$this->request->post();
        \$this->validate(\$data, {$model}Validate::class, [], 'update');
        \$this->{$serviceVar}->update(\$id, \$data);
        return \$this->success('更新成功');
    }

    #[Permission('{$module}.{$kebab}.delete')]
    #[OA\\Delete(
        path: '/{$module}/{$kebab}/{id}',
        summary: '删除{$comment}',
        security: [['bearerAuth' => []]],
        tags: ['{$comment}'],
        parameters: [
            new OA\\Parameter(name: 'id', in: 'path', required: true, schema: new OA\\Schema(type: 'integer'))
        ],
        responses: [
            new OA\\Response(response: 200, description: '删除成功', content: new OA\\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function delete(): Response
    {
        \$id = (int) \$this->request->param('id');
        \$this->{$serviceVar}->delete(\$id);
        return \$this->success('删除成功');
    }

    #[Permission('{$module}.{$kebab}.delete')]
    #[OA\\Post(
        path: '/{$module}/{$kebab}/batch-delete',
        summary: '批量删除{$comment}',
        security: [['bearerAuth' => []]],
        tags: ['{$comment}'],
        requestBody: new OA\\RequestBody(
            required: true,
            content: new OA\\JsonContent(
                required: ['ids'],
                properties: [
                    new OA\\Property(property: 'ids', type: 'array', items: new OA\\Items(type: 'integer'), description: 'ID列表'),
                ]
            )
        ),
        responses: [
            new OA\\Response(response: 200, description: '批量删除成功', content: new OA\\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function batchDelete(): Response
    {
        \$ids = \$this->request->post('ids', []);
        if (empty(\$ids)) {
            return \$this->error('请选择要删除的记录');
        }
        foreach (\$ids as \$id) {
            \$this->{$serviceVar}->delete((int)\$id);
        }
        return \$this->success('批量删除成功');
    }{$statusMethod}
}
PHP;
    }

    protected function generateValidate(string $module, string $model, array $columns): string
    {
        $rules = [];
        $messages = [];
        $createScene = [];
        $updateScene = [];

        foreach ($columns as $col) {
            if (empty($col['in_form'])) continue;
            $name = $col['name'];
            $comment = $col['comment'];
            $ruleArr = [];

            if (!$col['nullable'] && !in_array($name, ['status', 'sort'])) {
                $ruleArr[] = 'require';
            }

            switch ($col['type']) {
                case 'int':
                case 'bigint':
                case 'tinyint':
                case 'smallint':
                    $ruleArr[] = 'integer';
                    break;
                case 'decimal':
                case 'float':
                case 'double':
                    $ruleArr[] = 'float';
                    break;
            }

            if (str_contains($col['raw_type'], 'varchar')) {
                preg_match('/\((\d+)\)/', $col['raw_type'], $m);
                if (!empty($m[1])) {
                    $ruleArr[] = "max:{$m[1]}";
                }
            }

            if ($name === 'status') $ruleArr = ['integer', 'in:0,1'];
            if ($name === 'sort') $ruleArr = ['integer', '>=:0'];

            if (!empty($ruleArr)) {
                $ruleStr = implode('|', $ruleArr);
                $rules[] = "        '{$name}' => '{$ruleStr}',";

                if (in_array('require', $ruleArr)) {
                    $messages[] = "        '{$name}.require' => '{$comment}不能为空',";
                }

                $createScene[] = "'{$name}'";
                $updateScene[] = "'{$name}'";
            }
        }

        $rulesStr = implode("\n", $rules);
        $messagesStr = implode("\n", $messages);
        $createStr = implode(', ', $createScene);
        $updateStr = implode(', ', $updateScene);

        return <<<PHP
<?php
declare(strict_types=1);

namespace app\\adminapi\\validate\\v1\\{$module};

use core\\base\\Validate;

class {$model}Validate extends Validate
{
    protected \$rule = [
{$rulesStr}
    ];

    protected \$message = [
{$messagesStr}
    ];

    protected \$scene = [
        'create' => [{$createStr}],
        'update' => [{$updateStr}],
    ];
}
PHP;
    }

    protected function generateRoute(string $module, string $model, bool $hasStatus): string
    {
        $routeName = $this->toKebab($model);
        $ctrl = "v1.{$module}.{$model}Controller";

        $statusRoute = $hasStatus
            ? "\n        Route::put(':id/status', '{$ctrl}/status');"
            : '';

        return <<<PHP
    // {$model} 管理
    Route::group('{$routeName}', function () {
        Route::get('', '{$ctrl}/index');
        Route::post('batch-delete', '{$ctrl}/batchDelete');{$statusRoute}
        Route::get(':id', '{$ctrl}/show');
        Route::post('', '{$ctrl}/store');
        Route::put(':id', '{$ctrl}/update');
        Route::delete(':id', '{$ctrl}/delete');
    });
PHP;
    }

    // ========== 前端代码生成 ==========

    protected function generateFrontendApi(string $module, string $model, bool $hasStatus, array $columns = []): string
    {
        $kebab = $this->toKebab($model);
        $apiName = lcfirst($model) . 'Api';

        $interfaces = !empty($columns) ? $this->generateTypeScriptInterfaces($model, $columns) . "\n" : '';

        $statusApi = '';
        if ($hasStatus) {
            $statusApi = <<<TS

    /**
     * 更新状态
     */
    updateStatus(id: number, data: { status: number }) {
        return myRequest.put(`/adminapi/{$module}/{$kebab}/\${id}/status`, data)
    },
TS;
        }

        return <<<TS
import { myRequest } from '@/utils/request'

{$interfaces}/**
 * {$model} 管理API
 */
export const {$apiName} = {
    /**
     * 获取列表
     */
    getList(params: Record<string, any>) {
        return myRequest.get('/adminapi/{$module}/{$kebab}', { params })
    },

    /**
     * 获取详情
     */
    getDetail(id: number) {
        return myRequest.get(`/adminapi/{$module}/{$kebab}/\${id}`)
    },

    /**
     * 创建
     */
    create(data: Record<string, any>) {
        return myRequest.post('/adminapi/{$module}/{$kebab}', data)
    },

    /**
     * 更新
     */
    update(id: number, data: Record<string, any>) {
        return myRequest.put(`/adminapi/{$module}/{$kebab}/\${id}`, data)
    },

    /**
     * 删除
     */
    delete(id: number) {
        return myRequest.delete(`/adminapi/{$module}/{$kebab}/\${id}`)
    },

    /**
     * 批量删除
     */
    batchDelete(data: { ids: number[] }) {
        return myRequest.post('/adminapi/{$module}/{$kebab}/batch-delete', data)
    },
{$statusApi}}
TS;
    }

    protected function generateFrontendPage(string $module, string $model, array $columns, string $comment, bool $hasStatus): string
    {
        $kebab = $this->toKebab($model);
        $apiName = lcfirst($model) . 'Api';
        $permPrefix = "{$module}.{$kebab}";

        // 表格列
        $tableCols = '';
        foreach ($columns as $col) {
            if (empty($col['in_list'])) continue;
            $name = $col['name'];
            $colComment = $col['comment'];

            if ($name === 'id') {
                $tableCols .= "\n        <el-table-column label=\"ID\" prop=\"id\" width=\"80\" />";
                continue;
            }

            if ($name === 'status' && $hasStatus) {
                $tableCols .= <<<VUE


        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-switch
              v-model="row.status"
              :active-value="1"
              :inactive-value="0"
              @change="handleStatusChange(row)"
              :disabled="!userStore.hasPermission('{$permPrefix}.update')"
            />
          </template>
        </el-table-column>
VUE;
                continue;
            }

            if ($name === 'created_at') {
                $tableCols .= "\n        \n        <el-table-column label=\"创建时间\" prop=\"created_at\" width=\"170\" />";
                continue;
            }

            $width = mb_strlen($colComment) > 6 ? 'min-width="150"' : 'width="120"';
            $overflow = in_array($col['type'], ['text', 'mediumtext', 'longtext']) ? ' show-overflow-tooltip' : '';
            $tableCols .= "\n        \n        <el-table-column label=\"{$colComment}\" prop=\"{$name}\" {$width}{$overflow} />";
        }

        // status 相关片段
        $statusSearchSection = '';
        $statusChangeHandler = '';
        $statusImport = '';
        if ($hasStatus) {
            $statusImport = "\nimport { useUserStore } from '@/store'\n";
            $statusSearchSection = <<<VUE

        <el-form-item label="状态">
          <el-select
            v-model="searchForm.status"
            placeholder="请选择状态"
            clearable
            style="width: 120px"
          >
            <el-option label="正常" :value="1" />
            <el-option label="禁用" :value="0" />
          </el-select>
        </el-form-item>
VUE;
            $statusChangeHandler = <<<VUE

// 用户Store
const userStore = useUserStore()

// 状态变更
const handleStatusChange = async (row: Record<string, any>) => {
  try {
    await {$apiName}.updateStatus(row.id, { status: row.status })
    ElMessage.success('状态更新成功')
  } catch (error) {
    row.status = row.status === 1 ? 0 : 1
    console.error('状态更新失败:', error)
  }
}
VUE;
        }

        return <<<VUE
<template>
  <div class="{$kebab}-container">
    <!-- 搜索区域 -->
    <el-card class="search-card" shadow="never">
      <el-form :model="searchForm" inline class="search-form">
        <el-form-item label="关键词">
          <el-input
            v-model="searchForm.keyword"
            placeholder="请输入关键词"
            clearable
            style="width: 200px"
            @keyup.enter="handleSearch"
          />
        </el-form-item>
{$statusSearchSection}
        <el-form-item>
          <el-button type="primary" @click="handleSearch">
            <el-icon><Search /></el-icon>
            搜索
          </el-button>
          <el-button @click="handleReset">
            <el-icon><Refresh /></el-icon>
            重置
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 操作区域 -->
    <el-card class="table-card" shadow="never">
      <div class="table-header">
        <div class="table-title">{$comment}管理</div>
        <div class="table-actions">
          <el-button
            type="primary"
            @click="handleAdd"
            v-has-perm="['{$permPrefix}.create']"
          >
            <el-icon><Plus /></el-icon>
            新增
          </el-button>
          <el-button
            type="danger"
            :disabled="!multipleSelection.length"
            @click="handleBatchDelete"
            v-has-perm="['{$permPrefix}.delete']"
          >
            <el-icon><Delete /></el-icon>
            批量删除
          </el-button>
        </div>
      </div>

      <!-- 表格 -->
      <el-table
        v-loading="loading"
        :data="tableData"
        @selection-change="handleSelectionChange"
      >
        <el-table-column type="selection" width="55" />{$tableCols}

        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button
              type="primary"
              size="small"
              text
              @click="handleEdit(row)"
              v-has-perm="['{$permPrefix}.update']"
            >
              编辑
            </el-button>
            <el-button
              type="danger"
              size="small"
              text
              @click="handleDelete(row)"
              v-has-perm="['{$permPrefix}.delete']"
            >
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <el-pagination
        v-model:current-page="pagination.page"
        v-model:page-size="pagination.limit"
        :total="pagination.total"
        :page-sizes="[10, 20, 50, 100]"
        layout="total, sizes, prev, pager, next, jumper"
        @size-change="fetchList"
        @current-change="fetchList"
        class="pagination"
      />
    </el-card>

    <!-- 新增/编辑弹窗 -->
    <{$model}Form
      v-model="formVisible"
      :form-data="formData"
      @success="fetchList"
    />
  </div>
</template>

<script setup lang="ts" name="{$model}List">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Refresh, Plus, Delete } from '@element-plus/icons-vue'
import { {$apiName} } from '@/api/{$kebab}'
import {$model}Form from './components/{$model}Form.vue'{$statusImport}

// 搜索表单
const searchForm = reactive({
  keyword: '',
  status: undefined as number | undefined,
  page: 1,
  limit: 20,
})

// 列表数据
const tableData = ref<Record<string, any>[]>([])
const loading = ref(false)

// 分页
const pagination = reactive({
  page: 1,
  limit: 20,
  total: 0,
})

// 表格选择
const multipleSelection = ref<Record<string, any>[]>([])

// 弹窗
const formVisible = ref(false)
const formData = ref<Record<string, any>>({})

// 获取列表
const fetchList = async () => {
  try {
    loading.value = true
    const params = {
      ...searchForm,
      page: pagination.page,
      limit: pagination.limit,
    }
    const response = await {$apiName}.getList(params)
    tableData.value = response.data.list
    pagination.total = response.data.pagination.total
  } catch (error) {
    console.error('获取列表失败:', error)
  } finally {
    loading.value = false
  }
}

// 搜索
const handleSearch = () => {
  pagination.page = 1
  fetchList()
}

// 重置搜索
const handleReset = () => {
  Object.assign(searchForm, {
    keyword: '',
    status: undefined,
  })
  pagination.page = 1
  fetchList()
}

// 表格选择
const handleSelectionChange = (selection: Record<string, any>[]) => {
  multipleSelection.value = selection
}
{$statusChangeHandler}
// 新增
const handleAdd = () => {
  formData.value = { status: 1 }
  formVisible.value = true
}

// 编辑
const handleEdit = (row: Record<string, any>) => {
  formData.value = { ...row }
  formVisible.value = true
}

// 删除
const handleDelete = async (row: Record<string, any>) => {
  try {
    await ElMessageBox.confirm(
      '确定要删除该记录吗？删除后不可恢复！',
      '删除确认',
      { confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning' }
    )
    await {$apiName}.delete(row.id)
    ElMessage.success('删除成功')
    fetchList()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败:', error)
    }
  }
}

// 批量删除
const handleBatchDelete = async () => {
  try {
    await ElMessageBox.confirm(
      `确定要删除选中的\${multipleSelection.value.length}条记录吗？删除后不可恢复！`,
      '批量删除确认',
      { confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning' }
    )
    const ids = multipleSelection.value.map(item => item.id)
    await {$apiName}.batchDelete({ ids })
    ElMessage.success('批量删除成功')
    fetchList()
  } catch (error) {
    if (error !== 'cancel') {
      console.error('批量删除失败:', error)
    }
  }
}

onMounted(() => {
  fetchList()
})
</script>

<style lang="scss" scoped>
.{$kebab}-container {
  .search-card {
    margin-bottom: 16px;

    .search-form {
      margin: 0;
    }
  }

  .table-card {
    .table-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 16px;

      .table-title {
        font-size: 16px;
        font-weight: 600;
        color: var(--el-text-color-primary);
      }

      .table-actions {
        display: flex;
        gap: 8px;
      }
    }

    .pagination {
      margin-top: 16px;
      display: flex;
      justify-content: flex-end;
    }
  }
}
</style>
VUE;
    }

    protected function generateFrontendForm(string $module, string $model, array $columns, string $comment): string
    {
        $kebab = $this->toKebab($model);
        $apiName = lcfirst($model) . 'Api';

        $formItems = '';
        $formDefaults = '';
        $rules = '';
        $watchAssign = '';

        foreach ($columns as $col) {
            if (empty($col['in_form'])) continue;
            $name = $col['name'];
            $colComment = $col['comment'];
            $formType = $col['form_type'];

            // 默认值
            $default = match ($formType) {
                'number', 'switch' => $name === 'status' ? '1' : ($name === 'sort' ? '0' : '0'),
                default => "''",
            };
            $formDefaults .= "  {$name}: {$default},\n";
            $watchAssign .= "        {$name}: newData.{$name} ?? {$default},\n";

            // 验证规则
            if (!$col['nullable'] && !in_array($name, ['status', 'sort'])) {
                $trigger = in_array($formType, ['switch', 'select', 'radio', 'datepicker']) ? 'change' : 'blur';
                $rules .= "  {$name}: [\n    { required: true, message: '请输入{$colComment}', trigger: '{$trigger}' },\n  ],\n";
            }

            // 表单项
            $formItems .= match ($formType) {
                'textarea' => <<<VUE

      <el-form-item label="{$colComment}" prop="{$name}">
        <el-input v-model="form.{$name}" type="textarea" :rows="3" placeholder="请输入{$colComment}" />
      </el-form-item>
VUE,
                'number' => <<<VUE

      <el-form-item label="{$colComment}" prop="{$name}">
        <el-input-number v-model="form.{$name}" :min="0" controls-position="right" />
      </el-form-item>
VUE,
                'switch' => <<<VUE

      <el-form-item label="{$colComment}" prop="{$name}">
        <el-radio-group v-model="form.{$name}">
          <el-radio :value="1">启用</el-radio>
          <el-radio :value="0">禁用</el-radio>
        </el-radio-group>
      </el-form-item>
VUE,
                'datepicker' => <<<VUE

      <el-form-item label="{$colComment}" prop="{$name}">
        <el-date-picker
          v-model="form.{$name}"
          type="datetime"
          placeholder="请选择{$colComment}"
          value-format="YYYY-MM-DD HH:mm:ss"
          style="width: 100%"
        />
      </el-form-item>
VUE,
                'select' => <<<VUE

      <el-form-item label="{$colComment}" prop="{$name}">
        <el-select v-model="form.{$name}" placeholder="请选择{$colComment}" clearable>
          <!-- TODO: 补充选项 -->
          <el-option label="选项1" value="1" />
          <el-option label="选项2" value="2" />
        </el-select>
      </el-form-item>
VUE,
                'image' => <<<VUE

      <el-form-item label="{$colComment}" prop="{$name}">
        <el-input v-model="form.{$name}" placeholder="请输入图片地址">
          <template #append>
            <el-button>上传</el-button>
          </template>
        </el-input>
      </el-form-item>
VUE,
                default => <<<VUE

      <el-form-item label="{$colComment}" prop="{$name}">
        <el-input v-model="form.{$name}" placeholder="请输入{$colComment}" />
      </el-form-item>
VUE,
            };
        }

        return <<<VUE
<template>
  <el-dialog
    v-model="visible"
    :title="isEdit ? '编辑{$comment}' : '新增{$comment}'"
    width="600px"
    :close-on-click-modal="false"
    @close="handleClose"
  >
    <el-form
      ref="formRef"
      :model="form"
      :rules="rules"
      label-width="100px"
    >{$formItems}
    </el-form>

    <template #footer>
      <span class="dialog-footer">
        <el-button @click="handleClose">取消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="handleSubmit">
          确定
        </el-button>
      </span>
    </template>
  </el-dialog>
</template>

<script setup lang="ts" name="{$model}Form">
import { ref, reactive, computed, watch } from 'vue'
import { ElMessage, ElForm } from 'element-plus'
import { {$apiName} } from '@/api/{$kebab}'

interface Props {
  modelValue: boolean
  formData: Record<string, any>
}

interface Emits {
  (e: 'update:modelValue', value: boolean): void
  (e: 'success'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

// 表单引用
const formRef = ref<InstanceType<typeof ElForm>>()

// 弹窗显示状态
const visible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})

// 是否编辑模式
const isEdit = computed(() => !!props.formData?.id)

// 表单数据
const form = reactive({
{$formDefaults}})

// 表单验证规则
const rules = {
{$rules}}

// 提交加载状态
const submitLoading = ref(false)

// 监听表单数据变化
watch(
  () => props.formData,
  (newData) => {
    if (newData) {
      Object.assign(form, {
{$watchAssign}      })
    }
  },
  { deep: true, immediate: true }
)

// 提交表单
const handleSubmit = async () => {
  if (!formRef.value) return

  try {
    await formRef.value.validate()
    submitLoading.value = true

    if (isEdit.value && props.formData.id) {
      await {$apiName}.update(props.formData.id, { ...form })
      ElMessage.success('编辑成功')
    } else {
      await {$apiName}.create({ ...form })
      ElMessage.success('新增成功')
    }

    emit('success')
    handleClose()
  } catch (error) {
    console.error('提交失败:', error)
  } finally {
    submitLoading.value = false
  }
}

// 关闭弹窗
const handleClose = () => {
  formRef.value?.resetFields()
  visible.value = false
}
</script>

<style lang="scss" scoped>
.dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
</style>
VUE;
    }

    protected function generateTypeScriptInterfaces(string $model, array $columns): string
    {
        $createFields = [];
        $updateFields = [];
        $infoFields = [];

        $tsTypeMap = [
            'int' => 'number', 'bigint' => 'number', 'tinyint' => 'number',
            'smallint' => 'number', 'decimal' => 'number', 'float' => 'number',
            'double' => 'number', 'varchar' => 'string', 'char' => 'string',
            'text' => 'string', 'longtext' => 'string', 'mediumtext' => 'string',
            'json' => 'any', 'date' => 'string', 'datetime' => 'string',
            'timestamp' => 'string',
        ];

        foreach ($columns as $col) {
            $name = $col['name'];
            $tsType = $tsTypeMap[$col['type']] ?? 'string';
            $comment = $col['comment'] ?: '';
            $commentStr = $comment ? "  /** {$comment} */\n" : '';

            // Info interface: all fields
            if ($name !== 'deleted_at') {
                $infoFields[] = "{$commentStr}  {$name}: {$tsType}";
            }

            // Create interface: form fields (not id, not timestamps)
            if ($col['in_form']) {
                $optional = $col['nullable'] || $col['default'] !== null ? '?' : '';
                $createFields[] = "{$commentStr}  {$name}{$optional}: {$tsType}";
            }

            // Update interface: form fields, all optional except id
            if ($col['in_form']) {
                $updateFields[] = "{$commentStr}  {$name}?: {$tsType}";
            }
        }

        $createStr = implode("\n", $createFields);
        $updateStr = "  id: number\n" . implode("\n", $updateFields);
        $infoStr = implode("\n", $infoFields);

        return <<<TS
/** {$model} 创建请求 */
export interface {$model}CreateReq {
{$createStr}
}

/** {$model} 更新请求 */
export interface {$model}UpdateReq {
{$updateStr}
}

/** {$model} 详情 */
export interface {$model}Info {
{$infoStr}
}
TS;
    }

    protected function generateMigration(string $table, array $columns): string
    {
        $typeMap = [
            'bigint'     => 'biginteger',
            'int'        => 'integer',
            'tinyint'    => 'boolean',
            'smallint'   => 'smallinteger',
            'varchar'    => 'string',
            'char'       => 'char',
            'text'       => 'text',
            'longtext'   => 'text',
            'mediumtext' => 'text',
            'json'       => 'json',
            'decimal'    => 'decimal',
            'float'      => 'float',
            'double'     => 'float',
            'date'       => 'date',
            'datetime'   => 'datetime',
            'timestamp'  => 'timestamp',
        ];

        $className = str_replace(' ', '', ucwords(str_replace('_', ' ', "create_{$table}_table")));
        $addColumns = [];
        $indexes = [];

        foreach ($columns as $col) {
            $name = $col['name'];
            if ($name === 'id') continue; // Phinx auto-creates id

            $baseType = $col['type'];
            $phinxType = $typeMap[$baseType] ?? 'string';

            $options = [];

            // Parse length from raw_type
            if (preg_match('/\((\d+)\)/', $col['raw_type'], $m)) {
                if ($phinxType === 'string' || $phinxType === 'char') {
                    $options[] = "'limit' => {$m[1]}";
                }
            }

            // Parse precision/scale for decimal
            if (preg_match('/\((\d+),(\d+)\)/', $col['raw_type'], $m)) {
                if ($phinxType === 'decimal' || $phinxType === 'float') {
                    $options[] = "'precision' => {$m[1]}";
                    $options[] = "'scale' => {$m[2]}";
                }
            }

            // Unsigned for biginteger/integer
            if (str_contains($col['raw_type'], 'unsigned') && in_array($phinxType, ['biginteger', 'integer', 'smallinteger'])) {
                $options[] = "'signed' => false";
            }

            if ($col['nullable']) {
                $options[] = "'null' => true";
            }

            if ($col['default'] !== null) {
                $default = is_numeric($col['default']) ? $col['default'] : "'{$col['default']}'";
                $options[] = "'default' => {$default}";
            }

            if (!empty($col['comment'])) {
                $escapedComment = addslashes($col['comment']);
                $options[] = "'comment' => '{$escapedComment}'";
            }

            $optStr = !empty($options) ? '[' . implode(', ', $options) . ']' : '[]';
            $addColumns[] = "            ->addColumn('{$name}', '{$phinxType}', {$optStr})";

            // Add indexes for common patterns
            if ($col['key'] === 'MUL') {
                $indexes[] = "            ->addIndex(['{$name}'])";
            } elseif ($col['key'] === 'UNI') {
                $indexes[] = "            ->addIndex(['{$name}'], ['unique' => true])";
            }
        }

        $columnsStr = implode("\n", $addColumns);
        $indexesStr = !empty($indexes) ? "\n" . implode("\n", $indexes) : '';

        // Get table comment
        $tableComment = '';
        try {
            $tables = \think\facade\Db::query("SHOW TABLE STATUS LIKE '{$table}'");
            $tableComment = $tables[0]['Comment'] ?? '';
        } catch (\Exception $e) {}

        $tableOptions = "'engine' => 'InnoDB', 'collation' => 'utf8mb4_unicode_ci'";
        if ($tableComment) {
            $escapedTableComment = addslashes($tableComment);
            $tableOptions .= ", 'comment' => '{$escapedTableComment}'";
        }

        return <<<PHP
<?php

use think\\migration\\Migrator;

class {$className} extends Migrator
{
    public function up(): void
    {
        \$table = \$this->table('{$table}', [
            {$tableOptions},
        ]);

        \$table
{$columnsStr}{$indexesStr}
            ->create();
    }

    public function down(): void
    {
        \$this->table('{$table}')->drop()->save();
    }
}
PHP;
    }

    // ========== 辅助方法 ==========

    protected function parseColumnType(string $rawType): string
    {
        preg_match('/^(\w+)/', $rawType, $m);
        return strtolower($m[1] ?? 'varchar');
    }

    protected function toPhpType(string $type): string
    {
        return match ($type) {
            'int', 'bigint', 'tinyint', 'smallint', 'mediumint' => 'integer',
            'decimal', 'float', 'double' => 'float',
            'json' => 'json',
            default => 'string',
        };
    }

    protected function toOAType(string $type): string
    {
        return match ($type) {
            'int', 'bigint', 'tinyint', 'smallint', 'mediumint' => 'integer',
            'decimal', 'float', 'double' => 'number',
            default => 'string',
        };
    }

    protected function guessFormType(string $field, string $rawType): string
    {
        if (in_array($field, ['status', 'is_system', 'is_default', 'is_show', 'is_visible'])) return 'switch';
        if (in_array($field, ['sort', 'order', 'level', 'weight'])) return 'number';
        if (str_contains($field, 'price') || str_contains($field, 'amount') || str_contains($field, 'money')) return 'number';
        if (str_contains($field, 'image') || str_contains($field, 'avatar') || str_contains($field, 'logo') || str_contains($field, 'cover')) return 'image';
        if (str_contains($field, 'content') && str_contains($rawType, 'text')) return 'textarea';
        if (str_contains($rawType, 'text')) return 'textarea';
        if (str_contains($rawType, 'decimal') || str_contains($rawType, 'float') || str_contains($rawType, 'double')) return 'number';
        if (str_contains($rawType, 'datetime') || str_contains($rawType, 'timestamp')) return 'datepicker';
        if (str_contains($rawType, 'date')) return 'datepicker';
        if (str_contains($field, 'type') && str_contains($rawType, 'tinyint')) return 'select';
        return 'input';
    }

    protected function isSearchable(string $field, string $type): bool
    {
        $searchable = ['name', 'title', 'username', 'email', 'phone', 'mobile', 'code', 'keyword', 'label', 'nickname'];
        return in_array($field, $searchable) || $field === 'status';
    }

    protected function shouldShowInList(string $field): bool
    {
        $hidden = ['deleted_at', 'updated_at', 'password', 'remember_token', 'content'];
        return !in_array($field, $hidden);
    }

    protected function shouldShowInForm(string $field): bool
    {
        $hidden = ['id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'login_count', 'last_login_ip', 'last_login_time'];
        return !in_array($field, $hidden);
    }

    protected function getDefaultValue(array $col): string
    {
        if ($col['name'] === 'status') return '1';
        if ($col['name'] === 'sort') return '0';
        if ($col['default'] !== null && $col['default'] !== '') return "'" . $col['default'] . "'";
        return match ($col['type']) {
            'int', 'bigint', 'tinyint', 'smallint', 'mediumint' => '0',
            'decimal', 'float', 'double' => "'0.00'",
            default => "''",
        };
    }

    protected function hasColumn(array $columns, string $name): bool
    {
        foreach ($columns as $col) {
            if ($col['name'] === $name) return true;
        }
        return false;
    }

    protected function toKebab(string $name): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $name));
    }

    /**
     * 格式化数组为多行（如 fillable 字段过多时换行显示）
     */
    protected function wrapArray(array $items, int $indent): string
    {
        $line = implode(', ', $items);
        if (strlen($line) <= 80) {
            return $line;
        }

        $pad = str_repeat(' ', $indent);
        return "\n{$pad}" . implode(",\n{$pad}", $items) . ",\n    ";
    }
}
