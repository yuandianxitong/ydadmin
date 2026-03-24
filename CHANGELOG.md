# Changelog

本项目遵循 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/) 格式，版本号遵循 [语义化版本](https://semver.org/lang/zh-CN/)。

## [Unreleased]

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
