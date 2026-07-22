# Changelog

本项目遵循 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/) 格式，版本号遵循 [语义化版本](https://semver.org/lang/zh-CN/)。

## [Unreleased]

## [1.6.0] - 2026-07-22

### Added

#### 后端 (server)
- 新增 `php think yd:update` 数据库升级命令：按 `database/updates/vX.Y.Z/` 语义化版本顺序执行未应用的增量脚本，自动套用数据表前缀，已应用版本记入 `system_upgrades` 表（幂等、可断点续跑），支持 `--dry-run` 预览与 `--baseline` 首次基线；升级脚本新增可选 `update.php` 钩子（`callable(\PDO,string)`）
- 新增 `core/database/SqlRunner`：前缀改写 / 多语句安全拆分 / 占位符替换的 SQL 执行器，安装程序与 `yd:update` 升级共用，确保有无表前缀的库行为一致
- 新增 `yd:ai` / `yd:login` / `yd:ai:doctor` AI 代码生成 CLI：通过自然语言描述需求生成符合元点框架分层规范的代码，生成前展示 diff 预览并需确认后才写入，支持匿名反馈信号 opt-in 上报以改进生成质量
- 新增「开发工具 - AI 工作台」菜单及 `ai.studio.generate`（页面访问/生成）、`ai.studio.apply`（写入应用）权限点安装数据，为 admin 后台 AI Studio 页面提供菜单与权限基础

### Changed

#### 后端 (server)
- 框架发行版数据库升级机制统一为 `yd:update`，`schema.sql` 新增 `system_upgrades` 表；CI 测试库改用 `schema.sql` 导入，代码生成器不再产出迁移文件

### Removed

#### 后端 (server)
- 彻底移除 `topthink/think-migration` 依赖，删除 `server/database/migrations/`、`server/database/seeds/` 目录：框架不再使用迁移/填充文件，安装走 `schema.sql`/`init.sql`、升级走 `php think yd:update`（用户二开如需迁移工具可自行引入）

### Fixed

#### 后端 (server)
- 修正 `failed_jobs` 表排序规则漂移：其余表已在 v1.5.3 统一为 `utf8mb4_0900_ai_ci`，该表当时遗漏仍为 `utf8mb4_unicode_ci`，通过 `updates/v1.6.0` 补齐（仅影响执行过 v1.4.0+v1.5.3 的老实例）

#### 项目根
- 新增框架自带 MCP 配置：`.mcp.json`（Claude Code）与 `.cursor/mcp.json`（Cursor，`${workspaceFolder}` 自动定位），下载框架后在支持 MCP 的 AI 编码工具中打开即可使用元点 AI MCP（`yd-mcp-server`），无需手工配置项目路径

## [1.5.3] - 2026-04-10

### Changed

#### 后端 (server)
- 数据库全部表的字符集排序规则从 `utf8mb4_unicode_ci` 升级为 `utf8mb4_0900_ai_ci`（需 MySQL 8.0+）

### Fixed

#### Admin 前端 (admin)
- 修复请求错误冒泡到 ErrorBoundary 的问题
- 修复菜单父级选项类型错误

## [1.5.2] - 2026-04-08

### Fixed

#### 后端 (server)
- 修复安装时启用数据库表前缀（如 `PREFIX=yd_`）后 admin 登录接口崩溃的问题（"Typed property AdminService::\$adminRepository must not be accessed before initialization"）。根因是 ThinkPHP-ORM 4.x 下 `protected $table` 会绕过 `database.prefix`：
  - 31 个 Model 全部从 `protected $table = 'xxx'` 迁移到 `protected $name = 'xxx'`，让 ThinkPHP 自动应用前缀
  - 8 个 Repository 的 `Db::table('literal')` 改为 `Db::name()`；`NotificationRepository` 的 `whereExists` 子查询和原生 `INSERT INTO` 改用 `(new NotificationRead())->getTable()` 拼装物理表名
  - `CodeGeneratorService` 模板从 `$table` 改为 `$name`，新增 `stripTablePrefix()` 在生成 Model 时剥离物理表前缀，未来更换前缀也无需修改 Model
- `core\base\Service` / `core\base\Controller` 的 `resolveDependencies()` 在 `APP_DEBUG=true` 下记录被吞掉的注入异常，避免下次类似问题再次表现为误导性的 typed property 错误

### Removed

#### 后端 (server)
- 移除 3 个全工程零引用的早期遗留死代码：
  - `core/auth/Role.php`：8 处 `Db::table()` 字面量 + 引用了 schema 里不存在的 `user_roles` 表，已被 `RoleRepository` 替代
  - `core/database/Connection.php`：`Db` facade 的薄封装
  - `core/database/Migration.php`：`think\migration\Migrator` 的薄封装

## [1.5.1] - 2026-04-07

### Added

#### 后端 (server)
- 新增 `MenuChangedListener`，统一处理菜单相关操作后的缓存失效

### Changed

#### 后端 (server)
- `createMenu`/`updateMenu`/`deleteMenu`/`batchDeleteMenu`/`batchSort` 成功后触发 `menu.changed` 事件
- `MenuRepository` 移除缓存失效副作用，迁移至 `MenuChangedListener`
- 移除 `MenuRepository` 中无调用方的 `deleteWithChildren` 和已废弃的 `collectChildrenIds` 方法

#### Admin 前端 (admin)
- 菜单表单改造为单列布局，dialog 宽度收窄至 600px，组件路径输入框添加前后缀 UI 提示

### Fixed

#### Admin 前端 (admin)
- 修复菜单新增/编辑/删除后列表缓存未失效、页面不刷新的问题
- 修复 `IconSelect` SVG 图标 glob 路径错误导致 SVG tab 一直空白的问题
- 修复 `IconSelect` `popoverWidth` prop 传入百分比时 popover 撑满视口的问题

## [1.5.0] - 2026-04-07

### Added

#### 后端 (server)
- `core\base\Service`：新增 `extractPagination()`、`findOrFail()`、`runInTransaction()` 三个基类方法
- `core\base\Repository`：新增 `buildPagination()` 统一分页响应构造
- `core\base\Model`：新增默认 `getStatusTextAttr()` 实现
- 新增 `AbstractLedgerLogRepository` 账目流水抽象基类（BalanceLog/PointsLog 复用）
- `MessageService::sendToUser()`：封装按用户 ID 发送模板消息
- `RoleService::batchDeleteRole()`、`DictionaryService::batchDeleteDictionary()`：批量删除带事务
- `AdminService::batchDeleteAdmin()`：批量删除带事务
- 代码生成器生成的 Model 自动包含 `$append = ['status_text']` 声明
- CronJobService 命令白名单 + Console::call 进程内调用替代 exec
- 新增语言键：wechat_open_platform_not_configured / wechat_auth_failed / cron_command_empty / cron_command_not_allowed

#### Admin 前端 (admin)
- 新增 `hooks/useListPage.ts`：列表页通用 composable（分页/搜索/删除/批删/状态切换）
- 新增 `hooks/useFormDialog.ts`：表单弹窗通用 composable
- 新增 `utils/createCrudApi.ts`：标准 CRUD API 工厂
- 新增 `styles/crud-layout.scss`：全局列表页布局样式
- 新增 `constants/options.ts`：状态选项 hooks（useStatusOptions）
- 错误页面白名单含 404/500，loadRouteView 找不到组件时降级到 404

#### 移动端 (uniapp)
- 新增 `hooks/useCountdown.ts`：SMS 倒计时 composable
- 新增 `hooks/usePagingList.ts`：自动注册 onShow + onPullDownRefresh
- 新增 `hooks/useMessageList.ts`：消息列表共享逻辑 + messageCache 跨页面传递
- 新增 `utils/time.ts`：日期格式化工具（formatDate/formatDateTime/formatRelativeTime）
- 新增 `utils/platform.ts::getStatusBarHeight()`：状态栏高度封装
- 新增 `components/d-ledger-list/`：账目/积分流水列表通用组件
- 全局样式新增 .d-submit-btn / .d-section-card / .d-section-title

### Changed

#### 后端 (server)
- 分层架构合规：UserService 多处直接 Model 操作迁至 Repository
- User Model 删除静态查询方法（findByMobile/findByOpenid/findByMiniOpenid），逻辑迁至 UserRepository
- AdminLoginLog/AdminOperationLog 静态 record 方法迁至 Repository
- SystemConfig 静态查询逐步迁移至 Repository（保留 core 层使用的静态方法，避免反向依赖）
- MenuRepository.getAllChildrenIds 改为"一次全量查询 + BFS"消除 N+1
- NotificationRepository.markAsRead/markAllAsRead 改为批量 SQL 消除 N+1
- AlipayDriver create() 返回结构改为嵌套 data 字段，与 WechatPayDriver 对齐
- AdminController/MenuService.batchDelete 加事务包裹
- Listener 全部改为构造函数 DI（FeedbackCreated/UserRegister/PaymentSuccess/MessagePush/AdminLoginSuccess/AdminLoginFailed）
- DashboardService 删除 getRelativeTime，统一使用 DateHelper::diffForHumans
- ArticleCategoryService/DepartmentService 删除 buildTree，统一使用 ArrayHelper::toTree
- upload 路由添加 admin_log 中间件
- DashboardService 删除重复字段 newAdmins/newRoles/newMenus
- Admin Model 移除冲突的 setPasswordAttr，密码 hash 统一在 Service 层
- AuthController.wechatWebLogin 下沉至 UserService，使用 Guzzle 替代 file_get_contents
- 8 个 Service 应用 extractPagination 消除分页参数解构样板
- 5 处 findOrFail 替换样板代码
- UserManageService.adjustBalance/adjustPoints 改用 runInTransaction
- UserRepository.updateLastLogin 用 Db::raw 实现 login_count 原子自增

#### Admin 前端 (admin)
- 22 个列表页迁移到 useListPage（admin/role/department/cron-job/notification/dictionary/log×2/permission/file/announcement/agreement/article/article-category/feedback/region/version/auto-reply/balance-log/points-log/user/message-log/message-template）
- 15 个 Form 组件迁移到 useFormDialog
- v-has-perm 改用 removeChild 完全从 DOM 移除元素
- API 类型定义集中到 types/api.d.ts（UserItem/BalanceLogItem/PointsLogItem）
- usePaging 修复 res.data.list 数据解构对齐
- settings.store 清理 @ts-ignore，改用 $patch
- i18n 补全 feedback/article/article-category/announcement 等模块，合并 userMgmt.common.* 到顶层 common.*
- 首页快速导航重新排版

#### 移动端 (uniapp)
- LoginResult 类型字段从 user 改为 user_info（与后端对齐）
- user.store 新增 register 方法封装注册流程
- balance.vue/points.vue 接入 d-ledger-list
- register.vue 接入 useCountdown，移除手动 timer 管理
- announcement-list 接入 usePagingList 自动注册生命周期
- my/index.vue 头部高度通过 createSelectorQuery 动态测量替代硬编码
- settings.vue 接入真实的 useVersionCheck，删除假"检查更新"实现
- wechat-oauth 存储改用 uni.getStorageSync 跨端兼容
- upload.ts BASE_URL 与 request.ts 对齐（H5 DEV 代理）
- d-wechat-login 移除废弃的 uni.getUserProfile 调用
- 安全区域适配修复（加 env(safe-area-inset-bottom)）
- usePaging 新增 hasLoaded 状态供空状态组件防闪烁

#### PC 网站 (pc)
- 余额充值流程支持支付宝 PC page 支付（DOMParser 解析表单 + 新窗口手动提交）

### Fixed

#### 后端 (server)
- 修复 CodeGeneratorService.getTableColumns SQL 注入风险（白名单校验）
- 修复 FileService.deleteFile 物理文件删除失败未记录日志
- 统一 SystemConfigService 异常类为 BusinessException

#### Admin 前端 (admin)
- 修复 file/index.vue 模板语法错误导致页面无法加载

#### 移动端 (uniapp)
- 修复 balance.vue 支付字段名 payment_data 类型
- 修复 usePaging 初始 loading=true 导致 getList 阻塞（改为 hasLoaded 方案）
- 修复 useMessageList 从 modules 子包移到 hooks 主包（修复主包不能引用子包的限制）

### Removed

#### Admin 前端 (admin)
- 移除 ThemePicker 组件及 theme/apply.ts 遗留代码

#### 移动端 (uniapp)
- 移除 profile.vue 孤儿页面

## [1.4.0] - 2026-04-05

### Added
- 新增 `CacheableRepository` Trait，Repository 层声明式缓存抽象
- 新增 Redis 队列异步处理（操作日志、消息通知）
- 新增 `log:archive` 命令，定期清理过期管理员日志
- 新增前端 `useDebounceRequest` Hook，搜索请求防抖
- 新增前端 GET 请求去重机制

### Changed
- 缓存驱动由 file 切换为 Redis
- 字典数据增加 7200s Redis 缓存
- 菜单树增加 3600s Redis 缓存
- SystemConfigRepository / Permission 缓存迁移到标签化管理
- 操作日志由同步写 DB 改为异步队列
- 消息通知由同步 API 调用改为异步队列
- 余额/积分日志改用 eager loading，消除 enrichListWithNames 额外查询
- AdminRepository.getDetailWithPermissions 改用 eager loading 消除 N+1
- 前端删除 Auth Guard 冗余 menuApi.getAdminRoutes() fallback 请求

### Fixed
- 修复 admin_login_logs 缺少 (admin_id, login_time) 复合索引

## [1.3.0] - 2026-04-01

### Added
- API 文档支持后台管理 API / 前端应用 API 切换，C 端 11 个控制器添加 OpenAPI 注解
- UniApp 注册页新增手机短信验证码（与 PC 端注册流程统一）

### Changed
- UniApp 微信快捷登录按钮改为圆形绿色微信图标
- UniApp 引入 iconify 图标系统（@iconify-json/ri + presetIcons）
- 重构 Controller/Service/Repository 分层，消除架构违规（Controller 不再直接调用 Model，Service 不再绕过 Repository）
- Menu/Permission Model 查询逻辑迁移至 Repository 层
- AdminLogMiddleware 改用 Repository 记录操作日志
- 统一 Repository 调用风格（`$this->getModel()::` → `$this->model->`）
- `RequestCodeEnum` 更新为与后端一致的 HTTP 状态码（200/400/401/403/500）
- 超级管理员权限标识前后端对齐（后端注入 `'*'` 通配符）
- 事务 catch 类型统一为 `\Throwable`（RoleService、AdminService、DictionaryService）
- MessageLog Model 改为继承 `core\base\Model`
- `ConfigInfo` TypeScript 类型定义与实际 API 响应字段对齐
- `app.store.getConfig()` 返回值统一为 config 数据对象

### Fixed
- 修复开放平台配置保存成功但刷新后值为空（`system_configs` 缺少 `wechat_open` 组初始数据）
- 修复 `batchUpdateConfigs()` 对不存在的配置键静默跳过，改为抛出异常提示具体键名
- 修复异常类在 PHP 8.4 下隐式 nullable 参数弃用警告（BusinessException、ApiException、PermissionException）
- 修复 `server/public/static/fonts` 未纳入 git 版本控制
- 补充英文语言包缺失的 `config_group_wechat_open` 翻译
- 修复 Upload 组件响应码检查（`code == 1` → `code == 200`），错误消息字段（`msg` → `message`）
- 修复上传路由重复定义（移除 `common.php` 中多余的 upload 路由组）
- 修复超级管理员 `v-hasPerm` 指令不生效（后端未向前端发送 `'*'` 权限标识）
- 修复 21 个 Model 缺少 `$append` 声明导致访问器字段不出现在 API 响应
- 修复日志 Model 缺少 `$updateTime = false`（AdminLoginLog、AdminOperationLog）
- 修复静默吞掉异常的 catch 块（FileService、CodeGeneratorService、UploadController），改为 Log::warning
- 修复 `api-doc` 页面硬编码 localStorage key 获取 token
- 修复 `workbench` 页面 `v-for` + `v-if` 同元素（Vue 3 不允许）
- 修复 Vite 开发模式新页面首次访问触发依赖重优化整页刷新（改用动态解析组件样式路径）
- 修复 Dashboard stats 接口报 "Undefined array key 'login_result'"（AdminLoginLog 访问器加 isset 防御）
- 修复多个 Model 访问器在字段缺省时抛出 "Undefined array key" 警告（Admin、Role、Menu、Dictionary、DictionaryItem、Permission、BalanceLog、PointsLog）

### Removed
- 移除重复的消息模块视图（`views/message/`，保留 `views/system/message/`）
- 移除未使用的路由 guard 文件（`router/guards/init.ts`）
- 移除死代码：`getWorkbench()`、`getGlobalConfigs()`、`ThemePicker/demo.vue`
- 注册 3 个孤立事件到 `event.php`（`announcement.created`、`article.created`、`user.notification.created`）

## [1.2.1] - 2026-03-28

### Added
- 新增系统版本配置文件 `server/config/version.php`
- 新增数据库升级目录 `server/database/updates/` 及通用升级指南
- 新增 v1.2.1 数据库升级脚本（权限系统修复）

### Changed
- `CLAUDE.md` 新增发版数据规范章节

### Fixed
- 修正菜单权限命名不一致（type=2 菜单添加 `.list` 后缀）
- 补充缺失的 type=3 按钮菜单权限
- 为超级管理员角色分配新增按钮权限

### Removed
- 移除 `server/public/install/data/fix_permissions.sql`（内容已合并至 init.sql 和 updates/v1.2.1）

## [1.2.0] - 2026-03-24

### Added
- 仪表盘新增「最近活动」和「活跃用户排行」数据端点（`/adminapi/dashboard/recent-activities`、`/adminapi/dashboard/active-ranking`）
- DashboardRepository 新增用户统计、排行榜、最近活动查询方法
- DashboardService 新增用户注册/活跃统计、最近活动聚合、活跃排行逻辑

### Changed
- 仪表盘前端整体重设计：渐变玻璃态风格（gradient glassmorphism）
- KPI 卡片调整为冷色系渐变配色（左亮右暗）
- 移除系统信息卡片，快捷导航扩展为 4×2 网格布局
- 简化仪表盘整体布局，放大关键数字排版
- 移除仪表盘区域背景色覆盖
- 更新仪表盘相关 TypeScript 类型定义与 API 函数
- 更新仪表盘 i18n 多语言翻译

### Fixed
- 修复仪表盘中 `appStore` 属性名引用错误

## [1.1.0] - 2026-03-23

### Added
- 微信支付多端适配：小程序 JSAPI、公众号 JSAPI、H5 MWEB、APP、PC Native 五种支付方式自动路由
- 客户端平台识别：`X-Client-Type` 请求头（miniapp/wechat_h5/h5/app/pc），后端白名单校验
- 多 AppID 支付配置：按平台自动选择小程序/公众号/开放平台/移动应用 AppID
- JSAPI/APP 支付参数二次签名：`buildJsapiParams()`、`buildAppParams()` 方法
- 微信平台证书自动下载与缓存（无需手动配置 cert_path）
- 小程序微信快捷登录 + 手机号绑定（`wechatQuickLogin`、`wechatBindPhone` 接口）
- H5 公众号 OAuth 静默授权获取 oa_openid（`wechat-oauth.ts`）
- H5 微信浏览器 WeixinJSBridge 调起支付
- PC 端充值二维码展示 + 轮询支付状态（qrcode 库）
- 用户表新增 `oa_openid` 字段，支付订单表新增 `client_type` 字段
- 注册成功后自动登录（token + userInfo 同步写入 store）
- `notify_url` 支持相对路径，运行时自动补全域名

### Changed
- `PaymentManager::getWechatConfig()` 改为 public，新增多端 appid 配置加载
- `WechatPayDriver::create()` 支持动态 appid 参数
- `WechatPayDriver::query()` 使用 URI 模板避免订单号大写被 normalize 转义
- `PaymentService::createOrder()` 存储 client_type 到订单记录
- `UserController::recharge()` 根据客户端类型自动路由支付方式
- `PaymentController::query()` 不再强制要求 channel 参数，自动从订单记录获取
- `WechatController::oauthCallback()` 支持 SPA 重定向模式和 JSON 模式
- `OfficialAccountService::getUserByCode()` 返回 unionid 字段

### Fixed
- 修复微信支付未启用时返回 500 错误（改为友好提示）
- 修复微信支付 V3 SDK certs 参数为空导致初始化失败
- 修复微信 WXSS 编译错误（UnoCSS presetUno → presetWeapp）
- 修复发现页 tabs 四周边距不合理及多余 scroll-view
- 修复 H5 微信 OAuth 死循环（前端直接处理 code 参数）
- 修复 el-tree-select `value` 属性 TS 类型错误（改用 `node-key`）
- 修复 el-tag type 属性 TS 联合类型不匹配
- 修复微信支付查询订单号大写被转为 kebab-case（W → -w）
- 修复 ORDER_NOT_EXIST 轮询报错暴露给用户（静默返回 pending）

## [1.0.0] - 2026-03-20

### Added

#### Admin 后台管理
- 基于 Vue 3 + TypeScript + Element Plus + Vite + Pinia 的管理后台
- 动态路由系统，通过后端菜单数据自动生成
- 用户管理、角色权限、菜单管理
- 文章管理（分类、标签、封面、富文本编辑）
- 公告管理、反馈管理、协议管理
- 系统配置（站点设置、上传配置、支付配置等）
- 余额记录、积分记录管理
- 控制台仪表盘（统计卡片、登录趋势图表）
- 操作日志、登录日志
- 代码生成器（自动生成 CRUD 全栈代码）

#### Server 后端服务
- 基于 ThinkPHP 8 + PHP 8.0+ 的 RESTful API 服务
- 分层架构：Controller → Service → Repository → Model + Listener + Job
- 自动依赖注入（DI）
- JWT 认证与 RBAC 权限控制
- 支付系统（微信支付、支付宝）
- 余额/积分体系
- 消息通知系统（站内信、短信）
- 事件驱动的副作用处理（Listener 机制）
- 文件上传（本地、阿里云 OSS、腾讯云 COS、七牛云）
- 安装向导（含演示数据与动态 URL 替换）
- 开放平台（OAuth 第三方登录）

#### PC 前台网站
- 基于 Nuxt 3 (SPA) + Naive UI + UnoCSS 的前台网站
- 文章列表与详情（分类筛选、标签、阅读量）
- 用户中心（个人资料、密码修改、余额充值、积分明细）
- 登录注册（密码、短信、微信扫码）
- 全局错误页面（404/500）

#### UniApp 移动端
- 基于 uni-app + Vue 3 + wot-design-uni 的移动端应用
- 首页（轮播图、公告栏、功能入口、最新文章）
- 发现页（文章分类筛选、下拉刷新、上拉加载）
- 消息中心
- 个人中心（资料编辑、余额、积分）
- 文章详情（富文本渲染、标签展示）
- 反馈、公告、协议页面
