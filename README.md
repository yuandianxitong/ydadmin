<p align="center">
  <img src="https://erp.dev007.cn/oss/logo.png" alt="元点Admin" width="120">
</p>

<h1 align="center">元点Admin — 开源通用后台管理系统</h1>

<p align="center">
  基于 ThinkPHP 8 + Vue 3 + TypeScript + Element Plus + UniApp 的前后端分离管理系统
</p>

<p align="center">
  <a href="https://admin.dev007.cn">在线演示</a> · <a href="http://docs.dev007.cn/admin/">文档中心</a> · <a href="https://gitee.com/yuandianxitong/ydadmin/issues">问题反馈</a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.x-blue?logo=php" alt="PHP">
  <img src="https://img.shields.io/badge/ThinkPHP-8-green" alt="ThinkPHP">
  <img src="https://img.shields.io/badge/Vue-3-brightgreen?logo=vue.js" alt="Vue 3">
  <img src="https://img.shields.io/badge/Element%20Plus-latest-409eff" alt="Element Plus">
  <img src="https://img.shields.io/badge/MySQL-5.7%2B-orange?logo=mysql" alt="MySQL">
  <img src="https://img.shields.io/badge/uni--app-Vue%203-brightgreen?logo=vue.js" alt="uni-app">
  <img src="https://img.shields.io/badge/License-MIT-blue" alt="License">
</p>

---

## 系统简介

元点Admin 是一款开箱即用的通用后台管理系统，采用主流的前后端分离架构，后端基于 ThinkPHP 8 提供 RESTful API，前端使用 Vue 3 + Element Plus 构建管理界面，移动端通过 UniApp 实现多端适配（微信小程序 / APP / H5）。

系统内置完善的 RBAC 权限体系、CRUD 代码生成器和多渠道集成能力，适用于企业管理后台、SaaS 平台、电商运营等多种业务场景。开发者可基于此快速搭建业务系统，专注于核心业务逻辑开发。

## 技术栈

| 端 | 技术 |
|---|---|
| 后端 | ThinkPHP 8.0 / PHP 8.0+ / MySQL |
| 前端 | Vue 3 / TypeScript / Element Plus / Vite / Pinia / UnoCSS |
| 移动端 | UniApp / Vue 3 / Wot Design Uni |

## 功能特性

- **RBAC 权限** — 管理员 / 角色 / 权限 / 菜单，支持按钮级权限控制和数据范围
- **系统管理** — 部门、数据字典、文件管理、通知管理、定时任务、系统配置
- **日志审计** — 登录日志、操作日志自动记录
- **内容管理** — 协议、公告、用户反馈
- **应用管理** — 区域管理（省市区三级）、APP 版本管理
- **渠道管理** — 微信公众号（菜单/自动回复）、小程序配置
- **消息系统** — 多通道消息模板（短信/微信/站内信）、发送记录
- **支付集成** — 微信支付 / 支付宝（APP/小程序/H5）
- **代码生成** — 可视化 CRUD 代码生成器，一键生成前后端完整代码
- **API 文档** — 内置 OpenAPI 文档自动生成

## 架构设计

```
请求 → Controller → Service → Repository → Model
                       ↓
                    Listener（事件驱动副作用）
                    Job（异步队列任务）
```

- Controller 接收请求、参数校验，调用 Service
- Service 编排业务逻辑、管理事务、触发事件
- Repository 封装所有数据库查询
- Model 定义 ORM 映射和关联关系
- Listener 处理副作用（日志、通知、缓存清理）
- Controller / Service 基类内置自动依赖注入

## 快速开始

### 环境要求

- PHP >= 8.0（含 PDO、mbstring、fileinfo、curl、openssl、GD、ZipArchive 扩展）
- MySQL >= 5.7
- Node.js >= 16
- Composer

### Docker 部署（推荐）

```bash
git clone https://github.com/yuandianxitong/ydadmin.git
cd ydadmin/docker
cp .env.docker .env
docker-compose up -d
```

启动后访问 `http://localhost/install/` 完成安装向导。

安装完成后访问 `http://localhost/admin/` 进入管理后台。

> 默认管理员账号：`admin`，密码：`admin123456`

### 手动安装

```bash
# 克隆项目
git clone https://github.com/yuandianxitong/ydadmin.git
cd ydadmin

# 安装后端依赖
cd server
composer install
```

将 Web 服务器（Nginx / Apache）的站点根目录指向 `server/public/`，然后浏览器访问：

```
http://your-domain/install/
```

按照安装向导完成系统初始化（环境检测 → 数据库配置 → 管理员账号 → 自动建表和导入初始数据）。

安装完成后，管理后台已预编译在 `server/public/admin/` 目录下，直接访问：

```
http://your-domain/admin/
```

> 默认管理员账号：`admin`，密码：`admin123456`

### 二次开发

如需修改前端界面，进入 `admin/` 目录进行开发：

```bash
cd admin
npm install
npm run dev          # 本地开发服务器（热更新）
npm run build        # 构建生产版本
```

构建产物会输出到 `server/public/admin/`，上传至服务器即可生效。

移动端开发：

```bash
cd uniapp
pnpm install
pnpm dev:h5          # H5 开发
pnpm dev:mp-weixin   # 微信小程序开发
```

## 代码生成

```bash
# CLI 方式
cd server
php think make:crud table_name --module=模块名 --model=模型名

# 或通过管理后台「开发工具 → 代码生成器」可视化操作
```

自动生成：Model、Repository、Service、Controller、Validate、Route、前端 API、列表页、表单组件。

## 项目结构

```
├── admin/                 # 前端（Vue 3）
│   ├── src/
│   │   ├── api/           # API 接口
│   │   ├── views/         # 页面组件
│   │   ├── store/         # 状态管理
│   │   ├── router/        # 路由（动态生成）
│   │   └── utils/         # 工具函数
│   └── ...
├── server/                # 后端（ThinkPHP 8）
│   ├── app/
│   │   ├── adminapi/      # 管理端 API（Controller / Validate / Route）
│   │   ├── api/           # C端 API
│   │   ├── model/         # 模型层
│   │   ├── repository/    # 数据访问层
│   │   ├── service/       # 业务逻辑层
│   │   ├── listener/      # 事件监听器
│   │   └── event.php      # 事件注册
│   ├── core/              # 框架核心（基类 / 认证 / 支付 / 存储）
│   └── public/
│       └── install/       # 安装程序
├── uniapp/                # 移动端（UniApp）
├── .github/               # CI/CD
├── LICENSE
└── README.md
```

## 开源协议

[MIT License](LICENSE)

## 链接

- 在线演示: [https://admin.dev007.cn](https://admin.dev007.cn)
- 文档中心: [http://docs.dev007.cn/admin/](http://docs.dev007.cn/admin/)
- GitHub: [https://github.com/yuandianxitong/ydadmin](https://github.com/yuandianxitong/ydadmin)
- Gitee: [https://gitee.com/yuandianxitong/ydadmin](https://gitee.com/yuandianxitong/ydadmin)
