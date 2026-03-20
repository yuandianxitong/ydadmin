# Changelog

本项目遵循 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/) 格式，版本号遵循 [语义化版本](https://semver.org/lang/zh-CN/)。

## [Unreleased]

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
