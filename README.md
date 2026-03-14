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

系统内置完善的 RBAC 权限体系、CRUD 代码生成器、插件化扩展机制和多渠道集成能力，适用于企业管理后台、SaaS 平台、电商运营等多种业务场景。开发者可基于此快速搭建业务系统，专注于核心业务逻辑开发。

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
- **插件系统** — 插件生命周期管理，后台可视化安装/卸载/启用/禁用，支持 ZIP 上传
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

### 安装

```bash
# 克隆项目
git clone https://github.com/yuandianxitong/ydadmin.git
cd ydadmin

# 后端依赖
cd server
composer install

# 前端依赖
cd ../admin
npm install
```

### 初始化

访问 `http://your-domain/install` 进入安装向导，按步骤完成：

1. 环境检测
2. 数据库配置
3. 管理员账号设置
4. 自动创建数据表和初始数据

### 开发

```bash
# 前端开发服务器
cd admin
npm run dev

# 后端（配置 Nginx/Apache 指向 server/public）
```

### 构建

```bash
cd admin
npm run build
```

## 代码生成

```bash
# CLI 方式
cd server
php think make:crud table_name --module=模块名 --model=模型名

# 或通过管理后台「开发工具 → 代码生成器」可视化操作
```

自动生成：Model、Repository、Service、Controller、Validate、Route、前端 API、列表页、表单组件。

## 插件开发

```bash
# 生成插件骨架
php think make:crud table_name --module=模块名 --model=模型名 --as-plugin

# CLI 管理
php think plugin:list
php think plugin:install plugin_name
php think plugin:enable plugin_name
```

或在管理后台「开发工具 → 插件管理」进行可视化管理。

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
│   ├── core/              # 框架核心（基类 / 插件系统 / 中间件）
│   ├── plugins/           # 插件目录
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
