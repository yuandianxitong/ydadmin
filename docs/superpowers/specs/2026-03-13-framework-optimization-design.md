# Dev007 Framework 全面优化设计方案

## 项目定位

开源通用软件系统管理后台框架，面向独立开发者和中型团队，采用单应用架构设计。

**核心差异化（优先级排序）：**
1. 移动端一体化 — 管理后台 + UniApp C 端应用基座
2. 代码生成器 — 一键生成后端 + 管理前端完整模块
3. 开发者体验（DX）— 文档完善、上手快、流程顺
4. 插件/模块生态 — 可插拔功能模块，按需组合

**技术栈（保持不变）：** ThinkPHP 8 + Vue 3 + TypeScript + Element Plus + UniApp + Wot UI

---

## 方案：三轨并行

| 轨道 | 内容 | 目标 |
|------|------|------|
| **架构轨道** | 现有架构优化 + 插件系统 + 代码生成器增强 | 技术底座 |
| **移动轨道** | UniApp 通用应用层组件 + C 端 API | 差异化卖点 |
| **DX 轨道** | 文档体系 + 数据库迁移 + 测试覆盖 + DevOps | 开源可持续性 |

---

## 实施阶段

### Phase 1：基础打通（优先）
- 数据库迁移 + Seeder（21 张表）
- UniApp 请求层 + 登录模块 + 用户中心模块
- 后端 C 端认证 API（`/api/v1/auth/*`、`/api/v1/user/*`）
- 代码生成器增加迁移文件生成
- `setup.sh` 初始化脚本

### Phase 2：核心能力
- UniApp 支付模块 + 消息模块 + 反馈模块
- 后端 C 端支付/消息/反馈 API + C 端 API 限流中间件
- 插件系统基础补全（BasePlugin、CLI 命令、目录约定对齐）
- Admin 前端组件补充（ImportData、Region、SearchForm、TableColumnSetting）
- 错误码分类体系
- 测试框架搭建 + 认证流程测试
- VitePress 文档站骨架 + 快速开始 + 架构文档

### Phase 3：生态完善
- 内置模块开发（公告管理、地区数据、协议管理、版本管理、数据导入）
- 代码生成器 `--as-plugin` 模式
- 完整文档体系
- CI/CD（GitHub Actions）
- 补充测试覆盖（CRUD、支付、核心工具类）
- 官方插件开发（按需求优先级逐个推进）

---

## 一、架构轨道

### 1.1 现有架构优化点

**后端：**
- **数据库迁移体系**：为现有 21 张表补齐迁移文件 + Seeder，`php think migrate:run` 一键建库
- **错误码分类体系**：现有 `ExceptionHandle.php` 已有统一 JSON 响应格式（code/message/data/timestamp），需补充的是系统化的错误码分类，便于前端和移动端统一处理：
  - `1xxx` 认证错误（token 过期、无权限等）
  - `2xxx` 参数验证错误
  - `3xxx` 业务逻辑错误
  - `4xxx` 支付相关错误
  - `5xxx` 系统错误
- **请求验证增强**：Validate 层增加场景化验证规范，代码生成器自动生成 `scene('create')` / `scene('update')`

**前端：**
- API 层类型安全：生成器自动生成完整的 Request/Response TypeScript 接口
- 错误边界完善：路由级和 API 级错误处理统一规范
- CRUD Store 工厂函数：后期优化项，待更多 CRUD 模块积累后再提取

### 1.2 插件系统（目录约定式 → 渐进演进）

**现状：** `core/plugin/PluginManager.php` 和 `PluginLoader.php` 已有基础骨架（扫描、安装、卸载、启用、禁用），但存在未完成的部分：
- `BasePlugin` 抽象类（PluginManager 引用但不存在）
- CLI 命令类（`ListCommand`、`InstallCommand` 等，PluginLoader 注册但不存在）
- 目录约定需对齐现有代码（PluginManager 使用 `plugins/{name}/backend/` 子目录）

**需要先补全基础，再扩展功能。**

**后端插件目录约定（对齐现有 PluginManager）：**
```
server/plugins/
  └── payment/
      ├── plugin.json              # 插件元信息（名称、版本、依赖、启用状态）
      ├── install.php              # 安装脚本
      ├── uninstall.php            # 卸载脚本
      └── backend/                 # 后端代码（PluginManager 约定路径）
          ├── controller/
          ├── service/
          ├── repository/
          ├── model/
          ├── listener/
          ├── migration/
          ├── config/
          │   └── routes.php       # 插件路由
          └── event.php            # 插件事件注册

admin/src/plugins/
  └── payment/
      ├── plugin.json              # 前端插件配置（菜单、路由、权限）
      ├── api/
      ├── pages/
      ├── components/
      └── store/

uniapp/src/plugins/
  └── payment/
      ├── plugin.json
      ├── api/
      ├── pages/
      └── components/
```

**Phase 2 实施内容：**
1. 实现 `BasePlugin` 抽象类
2. 实现 CLI 命令（`plugin:list`、`plugin:install`、`plugin:uninstall`、`plugin:enable`、`plugin:disable`）
3. 完善 PluginManager 的路由、事件、迁移自动注册
4. 插件遵循主框架分层架构（Controller → Service → Repository → Model）
5. 后期演进：加入 composer 包名字段支持包分发；管理后台增加插件管理页面

### 1.3 代码生成器增强

```bash
php think make:crud articles --module=content --model=Article
php think make:crud articles --module=content --model=Article --as-plugin  # Phase 3
```

**Phase 1 新增：**
- 数据库迁移文件生成
- 场景化验证规则（create/update）
- 更完整的 TypeScript Request/Response 类型定义

**Phase 3 新增：**
- `--as-plugin` 参数：生成到 `plugins/` 目录而非 `app/`
- 基础单元测试模板

注意：代码生成器只生成后端 + Admin 前端，不生成 UniApp 端。

---

## 二、移动轨道 — UniApp 通用应用层组件

### 2.1 核心定位

UniApp 不是管理后台的移动版，而是面向 C 端用户的通用应用基座。开发者拿到后可快速搭建商城、家政、预约类小程序等。

### 2.2 API 分层

```
adminapi/v1/  → 管理后台专用（Admin 前端调用）
api/v1/       → C 端用户接口（UniApp、未来 PC 端调用）
```

两套 API 完全独立，各有各的 Controller，共享同一套 Service → Repository → Model 层。

**共享 Service 层的约定：**
- 管理端和 C 端对同一实体的操作，在 Service 中使用不同方法（如 `getUserProfile()` 供 C 端、`getAdminUserDetail()` 供管理端）
- Controller 层负责字段过滤和权限检查，Service 层保持业务逻辑中立
- 避免在 Service 中判断调用来源

### 2.3 技术选型

- UI 框架：Wot UI（wot-design-uni）
- 自定义组件前缀：`d-`，与 Wot UI 的 `wd-` 区分
- 仅封装 Wot UI 不覆盖的业务级组件

### 2.4 项目结构

```
uniapp/src/
├── api/                      # C 端 API 请求层
│   ├── request.ts            # 请求封装（token、拦截器、错误处理）
│   ├── auth.ts               # 登录、注册、微信登录、短信验证码
│   ├── user.ts               # 用户信息、修改资料
│   ├── payment.ts            # 支付相关
│   ├── upload.ts             # 文件上传
│   ├── message.ts            # 消息通知
│   └── config.ts             # 应用配置
│
├── components/               # 自定义业务组件
│   ├── d-payment-popup/      # 支付方式选择弹窗
│   ├── d-wechat-login/       # 微信小程序授权登录
│   ├── d-phone-login/        # 手机号快捷登录
│   ├── d-agreement-check/    # 用户协议勾选
│   ├── d-pay-result/         # 支付结果展示
│   ├── d-avatar-upload/      # 头像上传（裁剪 + 上传）
│   ├── d-list-loader/        # 列表加载（下拉刷新 + 触底加载）
│   ├── d-price/              # 价格显示（¥格式化、划线价）
│   ├── d-empty/              # 空状态
│   ├── d-page/               # 页面容器（安全区 + 导航栏适配）
│   └── d-image-preview/      # 图片预览
│
├── modules/                  # 通用业务模块（开箱即用）
│   ├── login/                # 登录模块
│   │   ├── pages/
│   │   │   ├── login.vue             # 登录主页（多方式切换）
│   │   │   └── register.vue
│   │   └── composables/
│   │       └── useLogin.ts
│   │
│   ├── user/                 # 用户中心模块
│   │   ├── pages/
│   │   │   ├── profile.vue
│   │   │   ├── edit-profile.vue
│   │   │   ├── change-password.vue
│   │   │   └── settings.vue
│   │   └── composables/
│   │       └── useUser.ts
│   │
│   ├── payment/              # 支付模块
│   │   ├── utils/
│   │   │   ├── wechat-pay.ts
│   │   │   └── alipay.ts
│   │   ├── pages/
│   │   │   └── pay-result.vue
│   │   └── composables/
│   │       └── usePayment.ts
│   │
│   ├── message/              # 消息中心
│   │   └── pages/
│   │       ├── message-list.vue
│   │       └── message-detail.vue
│   │
│   ├── feedback/             # 意见反馈
│   │   └── pages/
│   │       └── feedback.vue
│   │
│   └── webview/              # 通用 WebView
│       └── pages/
│           └── webview.vue
│
├── store/
│   ├── app.store.ts
│   └── user.store.ts
│
├── hooks/
│   ├── useAuth.ts            # 登录态守卫
│   ├── usePaging.ts          # 分页加载
│   ├── useUpload.ts          # 上传封装
│   └── useShare.ts           # 小程序分享
│
├── utils/
│   ├── request.ts
│   ├── auth.ts
│   ├── platform.ts           # 平台判断 + 条件编译
│   ├── navigate.ts
│   └── validate.ts
│
├── styles/
│   ├── variables.scss        # 主题变量（覆盖 Wot UI）
│   └── common.scss
│
├── static/
├── pages/
│   └── index/index.vue
├── pages.json
└── manifest.json
```

### 2.5 后端 C 端 API 补充

| 接口模块 | 路径 | 说明 |
|---------|------|------|
| 认证 | `/api/v1/auth/*` | 登录、注册、微信登录、短信验证码、Token 刷新 |
| 用户 | `/api/v1/user/*` | 个人信息、修改资料、修改密码 |
| 上传 | `/api/v1/upload/*` | 头像、图片上传 |
| 支付 | `/api/v1/payment/*` | 创建订单、查询状态、回调通知 |
| 消息 | `/api/v1/message/*` | 消息列表、标记已读、未读数 |
| 反馈 | `/api/v1/feedback/*` | 提交反馈 |
| 配置 | `/api/v1/config/*` | 应用配置（启动页、协议、版本号） |

**C 端 API 限流策略：**
- 登录/注册接口：同一 IP 每分钟 10 次
- 短信验证码：同一手机号每分钟 1 次，每天 10 次
- 通用接口：同一用户每分钟 60 次
- 复用现有 `RateLimitMiddleware`，在 C 端路由组中配置独立限流规则

### 2.6 消息推送策略

C 端消息通知不适合轮询，采用以下方案：
- **小程序端**：使用微信订阅消息（需用户授权），进入消息页时拉取未读列表
- **App 端**：集成 uni-push（个推），支持离线推送
- **H5 端**：SSE（Server-Sent Events）推送未读数变更，页面内列表仍用接口拉取
- 后端通过 Listener 统一分发：消息创建事件 → 根据用户设备类型选择推送通道

---

## 三、DX 轨道

### 3.1 文档体系（VitePress）

```
docs/
├── guide/                    # 使用指南
│   ├── introduction.md       # 项目介绍、特性、演示
│   ├── quick-start.md        # 5 分钟快速开始
│   ├── directory-structure.md
│   ├── configuration.md
│   └── deployment.md         # Docker / 宝塔 / 手动部署
│
├── backend/                  # 后端开发文档
│   ├── architecture.md       # 分层架构详解
│   ├── controller.md
│   ├── service.md
│   ├── repository.md
│   ├── model.md
│   ├── event-listener.md
│   ├── middleware.md
│   ├── permission.md
│   ├── code-generator.md
│   └── api-convention.md     # API 规范（含错误码分类）
│
├── frontend/                 # 前端开发文档
│   ├── architecture.md
│   ├── router.md
│   ├── permission.md
│   ├── request.md
│   ├── components.md
│   ├── hooks.md
│   ├── store.md
│   └── theme.md
│
├── mobile/                   # 移动端文档
│   ├── getting-started.md
│   ├── modules.md
│   ├── components.md
│   ├── payment.md
│   └── wechat-login.md
│
├── plugin/                   # 插件开发文档
│   ├── introduction.md
│   ├── create-plugin.md
│   └── plugin-api.md
│
├── changelog.md
└── contributing.md
```

文档初期以中文为主，后续根据社区反馈考虑英文翻译。

### 3.2 数据库迁移体系

为现有 21 张表编写迁移文件（按外键依赖排序）+ Seeder（管理员、默认角色、基础菜单、默认权限、字典、系统配置）。

开发者 clone 后执行：
```bash
php think migrate:run && php think seed:run
```

插件迁移文件放在各插件 `backend/migration/` 下，由 PluginManager 统一调度。

### 3.3 测试覆盖

**优先级：**
1. 认证流程（登录、Token、权限拦截）
2. CRUD 基本流程
3. 支付流程
4. 核心工具类（TokenManager、StorageManager）

```
server/tests/
├── TestCase.php
├── Feature/                  # HTTP 接口级功能测试
│   ├── Auth/
│   ├── System/
│   └── Api/
└── Unit/                     # 单元测试
    ├── Service/
    ├── Repository/
    └── Core/
```

前端初期不做组件测试，后期引入 Vitest 对 hooks 和 store 做单元测试。

### 3.4 DevOps

**GitHub Actions CI：**
- PHP-CS-Fixer 代码规范
- PHPStan 静态分析
- 后端 Feature + Unit 测试
- 前端 TypeScript 类型检查
- 前端 ESLint + 构建验证

**Docker 完善：** 开发环境（hot reload）、测试环境（独立 DB）、生产环境优化

**初始化脚本：**
- `setup.sh`：面向本地开发环境 — .env 复制、composer install、npm install、迁移、种子、JWT 密钥生成
- `deploy.sh`（已有）：面向 Docker 生产环境部署

两者互补，`setup.sh` 用于首次 clone 后的本地开发环境搭建。

---

## 四、通用模块/组件扩充

### 4.1 内置到框架的模块

| 模块 | 说明 |
|------|------|
| 数据导入 | Excel/CSV 导入，字段映射、校验、错误回显 |
| 系统公告管理 | 管理员发布公告，推送到前端/移动端 |
| 地区数据管理 | 省市区三级联动数据 + 管理界面 |
| 协议/富文本管理 | 用户协议、隐私政策等可配置内容页 |
| 版本管理 | App 版本号、强制更新、更新日志 |

### 4.2 官方插件（远期规划，独立于本设计的实施范围）

以下为远期规划方向，具体实施时需独立设计：

| 插件 | 适用场景 |
|------|---------|
| 敏感词/内容过滤 | UGC 类应用 |
| 会员/用户等级 | 商城、社区 |
| 优惠券 | 商城、O2O |
| 订单管理 | 商城、预约、服务类 |
| 预约/排班 | 家政、医疗、教育 |
| CMS 内容管理 | 资讯站、企业官网 |
| 任务/工单 | 内部管理系统 |

### 4.3 Admin 前端组件补充

| 组件 | 说明 | 优先级 |
|------|------|--------|
| ImportData | Excel/CSV 导入（与 ExportData 对应） | 高 |
| Region | 省市区级联选择器 | 高 |
| SearchForm | 可收展搜索表单 | 高 |
| TableColumnSetting | 表格列自定义显隐排序 | 高 |
| DragSort | 拖拽排序 | 中 |
| Watermark | 页面水印 | 中 |
| DetailDesc | 详情描述列表 | 中 |
| StatusTag | 状态标签（统一颜色映射） | 中 |

### 4.4 分层策略

```
内置模块（框架自带）
├── 现有 13 个系统模块
├── + 数据导入导出
├── + 公告管理
├── + 地区数据
├── + 协议/内容页管理
└── + 版本管理

官方插件（plugins/ 目录，远期按需开发）
├── 敏感词/内容过滤
├── 会员/积分
├── 优惠券
├── 订单管理
├── 预约排班
├── CMS
└── 任务工单
```
