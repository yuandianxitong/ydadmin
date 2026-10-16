# 服务端开发与交付规范

核心层职责、目录归属与依赖方向见 [core/README.md](core/README.md)。

## 验证规则

控制器动作显式调用 `$this->validate($input, $this->{action}Rules(), $messages)`。
`{action}Rules()` 必须无参数，允许委托给共享规则类；文档生成器通过容器解析控制器后调用该方法。
只在实际重复时抽取共享规则，通用结构规则放 `core/validation/CommonRules.php`，业务模块规则放 `app/validation/{module}/`。
共享规则对象保持无请求状态。配置驱动规则可以读取注入的无状态服务。

- 新建必填字段使用 `required`；局部更新必填字段使用 `sometimes|required`；允许传 null 才使用 `nullable`。
- `validate()` 的返回值交给 Service；创建人、所属部门等系统字段由服务端赋值。
- 对嵌套对象显式声明允许的键，例如 `settings => sometimes|array:theme` 与 `settings.theme => sometimes|string`。
  只有 `array` 规则不能保证对象内部字段都经过验证。
- 记录归属、授权、状态转换等业务约束在 Service 执行；涉及并发的唯一性依赖数据库约束兜底。
- `CommonRules::ids()` 和 `binaryStatus()` 保持现有批量 ID、二值状态规则语义。

## 列表查询

```php
$params = $this->validate(
    QueryInput::normalize((array) $request->get(), array_keys($this->indexRules())),
    $this->indexRules()
);
[$page, $limit] = $this->pageParams($request);
return $this->paginate($this->articleService->getArticleList($params, $page, $limit));
```

`indexRules()` 合并 `CommonRules::pagination()` 与当前模块自己的过滤字段。
`QueryInput::normalize()` 只处理显式声明的可选字段：字符串 trim、空字符串/null 视为未筛选，保留 `0` 和非法数组供验证器判断。
日期区间先用 `date` 规则校验，再调用 `QueryInput::assertDateRanges()`；允许单侧区间，双侧禁止结束早于开始。

`Pagination` 统一 `page/limit` 和 `page_no/page_size` 的优先级，保持最小值 1、每页上限 100。
新接入的列表对非法分页或筛选类型返回既有 `code=422` 错误结构。
Repository 继续从 `query()` 起手；`applyOrder()` 委托 `SortOrder` 校验多字段排序，列名只能来自仓储 `$sortable`。
筛选条件的 SQL 仍由模块 Repository 明确定义。

当前已接入列表过滤校验：管理端文章、公告、字典；生成器产生的新模块默认接入。
其他现有控制器已共用分页解析，可逐模块补充自己的 `indexRules()`。

## 响应字段与类型

资源类继承 `core\response\JsonResource`，`schema()` 声明 OpenAPI data 对象结构。
`Resource::make()` 按 properties 保留顶层输出字段，`Resource::page()` 对分页列表应用同一规则。
字段类型由模型 casts 或业务转换保证；这两个方法不执行类型转换，也不递归裁剪任意 JSON 业务字段。
嵌套敏感对象应使用独立资源转换后再组合。

```php
#[ResponseSchema(ArticleResource::class, paginated: true)]
public function index(Request $request): Response
{
    // 收参、验证、调用 Service 后：
    return $this->paginate(ArticleResource::page($result));
}
```

`#[ResponseSchema]` 只声明响应文档，不触发输入验证；输入仍统一使用动作规则方法。
列表、详情、新建可复用资源声明；字段是否必有与字段是否可为空分别由 `required` 与 `nullable` 表达。
扩展返回字段时同步扩展资源，避免数据库新增字段自动进入响应。
现有文章、公告及新生成模块已接入；未声明的接口保留原有通用文档，类型导出不猜测其业务字段。

在具有对应数据库配置的环境导出实际路由契约：

```sh
php webman api:export --type=admin --output=runtime/contracts
php webman api:export --type=api --output=runtime/contracts
```

每次输出 `openapi-{type}.json` 与 `{type}.d.ts`。
TypeScript 的 `ApiOperations` 按 `HTTP方法 路径` 索引，包含 `path`、`query`、`body`、`success`、`response`：

```ts
type AnnouncementPage = ApiOperations['GET /adminapi/announcement/list']['success']['data'];
```

明确声明的接口有精确 success 类型，未声明的 success 为 unknown。
body 表示请求体的形状，是否存在请求体仍以 OpenAPI 的 requestBody 声明为准。
文档中的不支持规则继续保留在 `x-doc-warnings`，不能将有损推导当作完整业务验证契约。
影响规则的系统配置变更后，文档按数据库配置版本自动重建；修改控制器源码或路由后 reload，再导出契约。

## 测试与门禁

```sh
composer test:unit         # 不读取 .env，不访问 MySQL/Redis
composer test:integration  # 现有依赖基础设施的 Unit 与 HTTP Feature
composer test:redline      # 权限、隔离、敏感数据等红线回归
composer test:contract     # 隔离环境中的响应与文档契约回归
composer test              # 顺序执行全部三个测试套件
composer check:core        # 核心能力目录、命名空间与依赖方向
composer check:fast        # 格式、静态分析、分层纪律、纯单元测试
composer check             # 上述静态检查和全部测试，发布前必须通过
```

纯测试配置为 `phpunit.unit.xml`，只加载 Composer autoload。
直接运行默认配置的 `vendor/bin/phpunit` 仍覆盖三个套件，但会启动基础设施；离线单元测试请使用 `composer test:unit`。
新纯逻辑测试放 `tests/Pure/`；已有可独立运行的测试通过显式文件清单纳入，同一清单从集成配置排除。
基础设施测试仍使用 `tests/bootstrap.php`：数据库为 `.env` 的 DB_NAME 加 `_test`，Redis DB 15 专供测试并在启动时清空。
CI 使用独立 MySQL/Redis 服务；本地必须确认专用测试资源没有业务数据。

`composer contract` 保留原有对已启动服务的手工 HTTP 验收：会临时创建账号、修改并恢复部分配置，依赖完整运行环境。
它不进入自动发布门禁；自动门禁由隔离环境中的契约及功能测试覆盖。

`scripts/release.sh` 在任何实际打包前执行 `composer check`，`--skip-build` 只跳过前端构建。
`--list` 是只读包清单预览，不执行完整门禁。
GitHub Actions 的 `.github/workflows/server.yml` 执行同一套检查。
集成回归包含生成器产物、全新安装、版本升级、事务与权限测试；黄金文件更新后必须审阅差异并以普通模式重跑。
