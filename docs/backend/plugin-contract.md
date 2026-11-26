# 元点插件规范 1.0

插件发布包的根目录包含 `plugin.json`。磁盘插件放在 `server/plugins/<code>/`。

- `code` 长 2 至 64 字符，采用 kebab-case，以小写字母开头。目录名、构建产物和插件依赖都引用这一标识。
- `name` 是显示名，`version` 是插件版本，`kind` 取 `app` 或 `plugin`。
- `psr4` 将 `Plugin\\<Name>\\` 映射到插件内的 `app/`。入口类通过此映射加载。
- `hosts` 可声明支持的产品，取值为 `framework-webman`、`saas`、`shop`。字段省略时，由宿主继续校验插件实际使用的能力。
- `depends` 使用插件代码，`core.min` 和 `core.max` 可声明兼容的宿主核心版本闭区间。
- `lifecycle` 指向实现 `LifecycleHook` 的类。Saas 插件可以额外实现 `TenantLifecycleHook`，在单个租户启停时处理租户数据。
- `routes` 按宿主分组指向插件控制器目录。控制器使用 HTTP 方法属性声明路径。会员登录接口额外使用 `#[PluginApiAuth]` 并声明 `api_auth`。无 HTTP 入口时可省略 `routes`。
- `console` 显式列出插件命令类。工作进程可加载已声明命令，命令负责校验当前状态和可处理的任务。

## 数据和发布

`database/install.sql` 包含新安装的完整表结构。含建表语句的 ZIP 同时提供清理用的 `database/uninstall.sql`。已有安装按版本执行 `database/updates/vX.Y.Z.sql`。如升级支持回退，在 `database/updates/down/` 放同名 SQL。清除业务数据须明确触发 `database/uninstall.sql`。

插件在 `src/<端>/` 有 Web 前端源码时，发布包须提供 `dist/<端>/manifest.json`。清单包含 `schema`、`host`、`code`、`version`、`hostSdk`、`externals` 和页面或组件入口。宿主校验端、SDK 版本和使用的模块。Uniapp 插件按宿主构建流程编入小程序分包。

## 宿主差异

| 宿主 | 主要路由 | 运行状态 | 插件前端 |
| --- | --- | --- | --- |
| framework-webman | `/adminapi/<code>/…`、`/api/<code>/…` | 站点安装和启停 | `admin` |
| Saas | `/platformapi/<code>/…`、`/tenantapi/<code>/…`、`/api/<code>/…` | 平台安装后按租户权益生效 | `tenant`、`platform`、`pc` 和 Uniapp |
| Shop | 控制器声明原有的 `/adminapi/…` 和 `/api/…` 业务地址 | 站点安装和启停 | `admin` 和 Uniapp |

Shop 只有声明 `#[AvailableWhenDisabled]` 的动作可以在停用后继续服务。已有业务的发货、退款和历史查询依然需要原有认证、权限和数据归属校验。

framework-webman 和 Shop 的后台插件共用 `@yd/admin-host@1` SDK。后台构建会生成 import map。安装向导会生成资源签名使用的 `APP_KEY`。

站点运行中通过 worker 指纹确认重载生效。停机操作完成后，下一次启动按照已保存的插件状态加载。

## 维护共享内核

三个宿主通过 Composer 使用 `yuandianxitong/plugin-kernel`，各自的 `server/composer.lock` 固定安装版本。单独检出任一宿主后，在 `server/` 执行 `composer install` 即可通过公开 HTTPS 下载，无需 GitHub 账号或 SSH 密钥。[插件内核仓库](https://github.com/yuandianxitong/plugin-kernel)

在插件内核仓库维护源码并打 Git 标签发版。各宿主在 `server/` 执行 `composer update yuandianxitong/plugin-kernel` 更新锁文件，再运行插件回归测试。
