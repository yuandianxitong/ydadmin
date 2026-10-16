# Core 核心目录规范

`core/` 提供跨业务模块复用的框架能力、技术适配器和公共契约。业务流程继续遵循
Controller → Service → Repository → Model，具体业务规则放在 `app/`。

## 目录定义

一级目录按能力划分，接口、值对象和实现归属于同一个能力。当前定义 24 个一级目录：

| 目录 | 职责与归属 |
|---|---|
| `apidoc` | 路由与规则反射、OpenAPI 组装、TypeScript 导出；读取响应契约 |
| `auth` | Token、RBAC、权限注解、权限检查接口与实现、超管保护 |
| `base` | Controller、Service、Repository、Model 的公共基类 |
| `config` | 配置读取接口、一致快照接口与快照值对象；数据库实现由应用层提供 |
| `context` | 请求上下文和执行身份；不在单例属性里保存请求态 |
| `cron` | 调度表达式、命令执行和执行结果；具体任务管理留在应用层 |
| `database` | SQL 脚本、数据库安装辅助、开发库检查和 SQL LIKE 转义 |
| `datascope` | 数据范围计算、快照和 ORM 查询作用域 |
| `exception` | 跨模块通用业务异常；渠道专有异常留在渠道模块 |
| `generator` | 命名规则、表结构推导、模板、产物写入和增量合并 |
| `http` | HTTP 请求辅助和接口幂等声明；业务幂等持久化留在应用层 |
| `install` | 安装、升级、环境检查、环境文件以及超管初始化接口 |
| `message` | 消息契约、模板、渠道和投递失败分类；SMTP 适配器放 `message/mail` |
| `observability` | 通用进程心跳；业务监控聚合留在应用层 |
| `payment` | 支付网关、金额、请求与结果 DTO、配置及渠道异常 |
| `query` | 查询参数归一化、分页和排序规则；具体筛选 SQL 留在仓储 |
| `queue` | 队列投递入口和消费者、可靠投递接口；Outbox 持久化留在应用层 |
| `realtime` | 连接注册与实时消息发布；具体通知业务留在应用层 |
| `response` | API 响应、资源字段契约、响应注解与通用响应 Schema |
| `sms` | 短信接口、配置驱动解析和供应商驱动 |
| `storage` | 文件存储接口、供应商驱动和上传扩展名安全规则 |
| `support` | 无业务归属的脱敏、路径防护、载荷指纹和引导函数 |
| `validation` | 验证器、翻译器和通用输入规则 |
| `wechat` | 微信配置、凭据、HTTP 客户端及开放接口适配 |

单文件目录可以存在：上下文、心跳等具有明确生命周期或技术边界，不按文件数量强行合并。
新增 `helper`、`common`、`utils` 等与现有职责重叠的目录，或为单个类单独新增一级目录，
应先调整本规范及架构检查中的依赖定义。

只有出现明确分类时才增加子目录，例如 `payment/dto`、`storage/driver`、
`message/channel` 和 `generator/stubs`。不为每个能力统一套上 service、interface、dto 层。

## 依赖方向

- 核心不得依赖 `app`。跨边界需求通过所属能力的接口表达，由 `config/container.php` 绑定实现。
- 基类组合上下文、数据权限、验证、查询和响应能力；这些能力不得反向依赖基类。
- 生成器的命名和模板规则留在 `generator`，通用路径防护复用 `support`。
- `apidoc → response`；响应契约不得反向依赖文档生成工具。
- 消息渠道依赖短信、微信等适配器；SMTP 当前服务于消息投递，和消息异常契约保持同一模块。
- 配置契约不依赖应用层仓储，驱动按一次操作读取配置快照。
- `support` 的工具不依赖上层模块。唯一引导例外是 `support/lang.php` 委托验证模块的翻译器；
  该文件由 Composer files 加载，不代表其他 support 工具可以依赖验证模块。

运行 `composer check:core` 检查目录、命名空间和静态依赖（use 导入与完整限定名称，
不分析动态拼接的类名）。允许的跨模块依赖列在
`scripts/check-core-architecture.php`，已接入 `composer check:fast` 和 `composer check`。
模板文件不是运行中的核心代码，检查会跳过 `generator/stubs`；生成结果继续接受现有检查与黄金测试。

## 本次收拢与迁移

| 原位置 | 新位置 |
|---|---|
| `permission/*` | `auth/*`，权限注解仍叫 `Permission` |
| `auth/Permission.php` | `auth/PermissionChecker.php`，避免与权限注解同名 |
| `contract/ConfigValueReader.php`、`contract/ConfigSnapshotProvider.php` | `config/` |
| `contract/SuperAdminInitializer.php` | `install/` |
| `helper/SensitiveDataMasker.php` | `support/` |
| `helper/UploadExtensionGuard.php` | `storage/` |
| `support/Like.php` | `database/` |
| `generator/PathGuard.php` | `support/` |
| `module/NameConvention.php` | `generator/` |
| `apidoc/EnvelopeSchemas.php` | `response/` |
| `mail/*` | `message/mail/` |
| `idempotency/Idempotent.php` | `http/` |
| `idempotency/Fingerprint.php` | `support/PayloadFingerprint.php`，HTTP 与队列共用 |
| `task/PrivateTaskFiles.php` | `app/service/operations/`，属于业务任务文件管理 |
| `diy/*` | `app/service/diy/`，属于页面装修业务规则 |

对应 PHP 命名空间与目录同步变化，仓库内调用方、容器、生成模板和测试同步更新。
外部扩展若直接引用旧类名，需要按表迁移。部署后重启常驻进程，使其加载新的类与容器绑定。

## 生成器基线

生成器只产出业务代码、语言文件和菜单 SQL，不再生成独立模块描述 JSON。
增量更新的选项与原始生成内容保存在 `generator-state/{module}/{model_snake}/baseline.json`。
旧基线中已废弃的产物项不会参与预览或更新，下次成功写入基线时清理；其他产物仍执行校验、备份与冲突保护。
导入快照若包含 generator-state 基线，会沿用原始说明与字段选项，本次请求显式选项优先。
纯源码快照使用本次请求选项，不从业务代码推断原始选项。
