# API 文档（M2b）

由代码自身推导、不可能与代码分叉的接口文档。数据流：

```
Route::getRoutes()  →  RouteHarvester 按前缀过滤、解析 [控制器, 动作]
                    →  反射读 #[Permission] / #[PermissionSkip]
                    →  RuleReflector 从容器取控制器实例，反射实调 {action}Rules()
                    →  RuleTranslator 把规则串翻译成 OpenAPI schema 片段
                    →  EnvelopeSchemas 套通用信封 → OpenApiDocument 组装
                    →  ApiDocService 进程内按 type 缓存 → ApiDocController 输出
```

判据：**文档宁可少说，不可说谎**——每一处「推不出来」都进 `x-doc-warnings`，不静默丢弃。

## 端点

| 端点 | 用途 |
|---|---|
| `GET /adminapi/system/api-doc/openapi.json?type=admin\|api` | 裸 OpenAPI 3.0 JSON（不套 `{code,message,data,timestamp}` 信封） |
| `GET /adminapi/system/api-doc?type=admin\|api` | 独立 Swagger 页（HTML，CDN 加载 `swagger-ui-dist@5`） |

两条都标 `#[PermissionSkip]` 且公开：前端「Swagger UI」「下载 JSON」按钮走 `window.open`，浏览器导航
带不了 `Authorization` 头。代价被生产闸门兜住——见下条。

`type=admin` 收 `/adminapi`；`type=api` 收 `/api`（本仓库当前无此前缀路由，返回合法空文档：
`paths: {}`，`openapi`/`info`/`servers` 齐全）。

## 生产闸门

`config/route.php` 只在 `config('app.debug') === true` 时注册这两条路由——生产环境这个功能
压根不存在，不是「存在但被拦」。覆盖测试见 `tests/Unit/ApiDoc/ApiDocProductionGateTest.php`。

## 缓存

`app\service\system\ApiDocService::$documentCache`：进程内按 `type` 缓存整份文档一次
（`scripts/check-context-discipline.sh` 已登记）。TP8 每次请求重扫整棵控制器树且无缓存，
是它被列为缺陷的一条。

## 重新生成黄金文件

模板/规则收敛有改动、文档形状随之变化时：

```bash
cd server && YDADMIN_UPDATE_GOLDEN=1 vendor/bin/phpunit --filter ApiDocGoldenTest
git diff server/tests/fixtures/generated-apidoc/openapi-admin.json   # 审一遍
vendor/bin/phpunit --filter ApiDocGoldenTest                          # 去掉环境变量重跑，绿了才提交
```

黄金文件里 `/adminapi/auth/login` 的 `captcha`/`captcha_key` 是否必填，取决于生成时
`login_captcha` 开关的值：`ApiDocGoldenTest` 会在生成前把它钉回种子值 `'1'`（开），
所以黄金文件里这两个字段应为必填——本地重新生成前不需要手工改配置，测试自己保证了这一点。

## 已知限制

- 不推导 `data` 内部的业务字段——Service 返回类型全是无结构 `array`，只描述信封与分页的结构。
- `loginRules()` 按运行时 `login_captcha` 开关的实际值求值（不是「无参包装、恒定必填」的超集
  写法）：开关开时验证码必填，关时可不传，文档如实反映当前配置下的真实规则。
