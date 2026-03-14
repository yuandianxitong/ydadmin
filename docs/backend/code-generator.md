# 代码生成器

代码生成器可根据数据库表结构自动生成完整的 CRUD 代码，严格遵循 `Controller → Service → Repository → Model` 分层架构。

## 命令行使用

```bash
cd server
php think make:crud
```

交互式选择数据表，自动生成前后端完整代码。

## 管理后台使用

在管理后台「开发工具 → 代码生成器」页面可视化操作：

1. 选择数据表
2. 配置字段的表单类型、搜索条件、列表显示等选项
3. 预览生成代码
4. 一键生成

## 生成文件列表

| 文件类型 | 路径 | 说明 |
|----------|------|------|
| Model | `app/model/{module}/{ModelName}.php` | 数据模型 |
| Repository | `app/repository/{module}/{ModelName}Repository.php` | 数据仓库 |
| Service | `app/service/{module}/{ModelName}Service.php` | 业务逻辑 |
| Controller | `app/adminapi/controller/v1/{module}/{ModelName}Controller.php` | 管理端控制器 |
| Validate | `app/adminapi/validate/v1/{module}/{ModelName}Validate.php` | 表单验证 |
| Route | `app/adminapi/route/{module}.php` | 路由配置 |
| API (TS) | `admin/src/api/{module}.ts` | 前端 API 文件 |
| Types (TS) | `admin/src/types/api.d.ts` | TypeScript 类型（追加） |
| List Page | `admin/src/views/{module}/index.vue` | 列表页面 |
| Form Page | `admin/src/views/{module}/components/{ModelName}Form.vue` | 表单弹窗 |

## 字段类型映射

代码生成器根据数据库字段类型自动选择前端组件：

| 数据库类型 | 表单类型 | 前端组件 |
|-----------|---------|---------|
| varchar / char | input | el-input |
| text / longtext | textarea | el-input type="textarea" |
| int / bigint | number | el-input-number |
| tinyint | switch | el-switch |
| decimal / float | number | el-input-number |
| date / datetime | date | el-date-picker |
| enum / set | select | el-select |

## 生成代码示例

### Model 示例

```php
<?php
declare(strict_types=1);

namespace app\model\article;

use core\base\Model;

class Article extends Model
{
    protected $table = 'articles';

    // 状态常量
    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED = 1;
}
```

### Repository 示例

```php
<?php
declare(strict_types=1);

namespace app\repository\article;

use app\model\article\Article;
use core\base\Repository;

class ArticleRepository extends Repository
{
    protected string $modelClass = Article::class;

    public function getSearchList(array $params, int $page, int $limit): array
    {
        $query = $this->model->where('deleted_at', null);

        if (!empty($params['title'])) {
            $query->whereLike('title', '%' . $params['title'] . '%');
        }

        if (isset($params['status'])) {
            $query->where('status', $params['status']);
        }

        return $this->paginate($query, $page, $limit);
    }
}
```

### Service 示例

```php
<?php
declare(strict_types=1);

namespace app\service\article;

use app\repository\article\ArticleRepository;
use core\base\Service;

class ArticleService extends Service
{
    protected ArticleRepository $articleRepository;

    public function getList(array $params): array
    {
        $page = (int) ($params['page_no'] ?? 1);
        $limit = (int) ($params['page_size'] ?? 20);
        return $this->articleRepository->getSearchList($params, $page, $limit);
    }

    public function detail(int $id): ?array
    {
        return $this->articleRepository->find($id);
    }

    public function create(array $data): array
    {
        return $this->articleRepository->create($data);
    }

    public function update(int $id, array $data): bool
    {
        return $this->articleRepository->update($id, $data);
    }

    public function delete(int $id): bool
    {
        return $this->articleRepository->delete($id);
    }
}
```

### Controller 示例

```php
<?php
declare(strict_types=1);

namespace app\adminapi\controller\v1\article;

use app\service\article\ArticleService;
use app\adminapi\validate\v1\article\ArticleValidate;
use core\base\Controller;

class ArticleController extends Controller
{
    protected ArticleService $articleService;

    public function list(): \think\Response
    {
        $params = $this->request->get();
        $result = $this->articleService->getList($params);
        return $this->success('success', $result);
    }

    public function detail(int $id): \think\Response
    {
        $result = $this->articleService->detail($id);
        return $this->success('success', $result);
    }

    public function create(): \think\Response
    {
        $data = $this->validate(ArticleValidate::class, 'create');
        $result = $this->articleService->create($data);
        return $this->success('创建成功', $result);
    }

    public function update(int $id): \think\Response
    {
        $data = $this->validate(ArticleValidate::class, 'update');
        $this->articleService->update($id, $data);
        return $this->success('更新成功');
    }

    public function delete(int $id): \think\Response
    {
        $this->articleService->delete($id);
        return $this->success('删除成功');
    }
}
```

## 注意事项

1. **不要修改生成的基础结构** — 如果需要自定义逻辑，在生成的方法内部添加代码
2. **路由文件会追加** — 多次生成同一模块不会覆盖已有路由，注意检查是否重复
3. **副作用逻辑** — 生成的代码不包含事件触发，如需日志/通知等副作用，需手动添加 `$this->trigger()` 调用并创建 Listener
4. **字段排除** — `id`、`created_at`、`updated_at`、`deleted_at` 字段会自动排除，不出现在表单中
5. **TypeScript 类型** — 生成的类型定义会追加到 `api.d.ts`，注意检查是否已存在同名类型
