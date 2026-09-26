<p align="center">
  <img src="https://www.dev007.cn/oss/logo.png" alt="元点Admin" width="120">
</p>

<h1 align="center">元点Admin — 开源通用后台管理系统</h1>

<p align="center">
  基于 webman + Vue 3 + TypeScript + Element Plus + Nuxt + UniApp 的前后端分离管理系统
</p>

<p align="center">
  <a href="https://admin.dev007.cn">在线演示</a> · <a href="http://docs.dev007.cn/admin/">文档中心</a> · <a href="https://gitee.com/yuandianxitong/ydadmin/issues">问题反馈</a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.4-blue?logo=php" alt="PHP">
  <img src="https://img.shields.io/badge/webman-2.x-blue" alt="webman">
  <img src="https://img.shields.io/badge/Vue-3-brightgreen?logo=vue.js" alt="Vue 3">
  <img src="https://img.shields.io/badge/Element%20Plus-latest-409eff" alt="Element Plus">
  <img src="https://img.shields.io/badge/MySQL-8.0%2B-orange?logo=mysql" alt="MySQL">
  <img src="https://img.shields.io/badge/uni--app-Vue%203-brightgreen?logo=vue.js" alt="uni-app">
  <img src="https://img.shields.io/badge/License-MIT-blue" alt="License">
</p>

---

## 系统简介

元点Admin 是一款**免费商用**、开箱即用的通用后台管理系统。后端基于 [webman](https://www.workerman.net/webman) 常驻内存运行，一条命令同时拉起 HTTP、定时任务、队列与 WebSocket；管理端使用 Vue 3 + Element Plus，PC 端使用 Nuxt 3，移动端通过 UniApp 适配微信小程序 / APP / H5。基于 MIT 协议开源，个人和企业均可免费使用，无需授权费用。

系统内置 RBAC 权限、数据权限、CRUD 代码生成和多渠道能力，适用于企业管理后台、SaaS 平台、电商运营等场景。2.x 与 1.x（ThinkPHP）的数据库不兼容，不提供自动迁移。

## 演示体验

| 端 | 地址 | 账号 |
|---|---|---|
| 管理后台 | [https://admin.dev007.cn/admin/](https://admin.dev007.cn/admin/) | admin / admin888 |
| PC 前台 | [https://admin.dev007.cn/pc/](https://admin.dev007.cn/pc/) | — |
| 手机端 | [https://admin.dev007.cn/mobile/](https://admin.dev007.cn/mobile/) | — |

## 技术栈

| 端 | 技术 |
|---|---|
| 后端 | webman / PHP 8.4 / MySQL 8 / Redis |
| 管理后台 | Vue 3 / TypeScript / Element Plus / Vite / Pinia / UnoCSS |
| PC 端 | Nuxt 3 |
| 移动端 | UniApp / Vue 3 / uview-plus |

## 功能特性

- **RBAC 权限** — 管理员 / 角色 / 菜单，支持按钮级权限和数据范围
- **系统管理** — 部门、数据字典、文件管理、通知、定时任务、系统配置
- **日志审计** — 登录日志、操作日志（队列异步写入）
- **会员** — 注册登录、短信验证码、余额与积分
- **内容管理** — 文章、协议、公告、用户反馈
- **装修** — 移动端页面装修、底部导航、主题
- **应用管理** — 区域（省市区）、APP 版本
- **渠道管理** — 微信公众号（菜单 / 自动回复）、小程序、开放平台
- **消息系统** — 站内信、短信、公众号与小程序模板消息，队列异步发送
- **支付集成** — 微信支付 / 支付宝（PC、H5、APP、公众号、小程序）
- **实时通知** — WebSocket 推送、在线管理员、强制下线
- **代码生成** — 可视化 CRUD 代码生成器，一键生成前后端代码
- **API 文档** — 内置 OpenAPI 文档

## 架构设计

```
请求 → Controller → Service → Repository → Model
                       ↓
                    Listener（事件驱动副作用）
                    Queue（队列进程，随 start 一起拉起）
```

- Controller 接收请求、校验参数，只调用 Service
- Service 编排业务逻辑、管理事务、触发事件
- Repository 封装所有数据库查询
- Model 定义 ORM 映射和关联关系
- Listener 处理副作用（日志、通知、缓存清理）
- 依赖通过容器注入

## 快速开始

### 环境要求

- PHP >= 8.4（pdo_mysql、redis、pcntl、posix）
- MySQL >= 8.0
- Redis >= 5.0
- Composer 2
- Node.js >= 20、pnpm（仅前端开发需要）

### Docker 部署（推荐）

```bash
git clone https://github.com/yuandianxitong/ydadmin.git
cd ydadmin/server
composer install
cd ../docker
cp .env.example .env
```

在 `docker/.env` 里填写 `MYSQL_ROOT_PASSWORD`、`MYSQL_PASSWORD`、`REDIS_PASSWORD`，然后：

```bash
docker compose up -d --build
```

浏览器打开 `http://localhost/install/` 完成安装向导。装完执行：

```bash
docker compose restart webman
```

管理后台：`http://localhost/admin/`。超级管理员用户名默认 `admin`，密码在安装时设置。

### 常驻进程

`php start.php start` 会同时拉起 HTTP、定时任务、队列和 WebSocket。消息发送与操作日志由队列进程处理，不需要再单独启动队列命令。

生产环境使用：

```bash
php start.php start -d
```

### 手动安装

```bash
git clone https://github.com/yuandianxitong/ydadmin.git
cd ydadmin/server
composer install
cp .env.example .env
php start.php start
```

浏览器打开 `http://127.0.0.1:8000/install/`，按向导完成初始化。需要示例文章时，勾选「导入演示数据」。装完执行：

```bash
php start.php restart
```

管理后台：`http://127.0.0.1:8000/admin/`。

也可以用命令行安装：

```bash
php webman install -n --db-host=127.0.0.1 --db-name=ydadmin --db-user=root --db-password=... --username=admin --password=...
php start.php restart
```

示例文章加上 `--with-demo`。

已安装的系统升级：

```bash
cd server
php webman yd:update
```

## 二次开发

管理后台：

```bash
cd admin
pnpm install
pnpm dev             # 接口代理到 http://127.0.0.1:8000
pnpm build           # 构建到 server/public/admin/
```

PC 端：

```bash
cd pc
pnpm install
pnpm dev
pnpm generate        # 构建到 server/public/pc/
```

移动端：

```bash
cd uniapp
pnpm install
pnpm dev:h5          # H5
pnpm dev:mp-weixin   # 微信小程序
```

## 代码生成

```bash
cd server
php webman make:crud table_name --module=模块名 --model=模型名
php start.php reload
```

也可以在管理后台「开发工具 → 代码生成器」里操作。

自动生成：Model、Repository、Service、Controller、路由、语言包、前端 API、列表页、表单组件。生成后需要 `reload`，新路由才会生效。

## 项目结构

```
├── admin/                 # 管理后台（Vue 3）
│   └── src/
│       ├── api/           # API 接口
│       ├── views/         # 页面
│       ├── store/         # 状态
│       └── router/        # 路由
├── pc/                    # PC 端（Nuxt 3）
├── uniapp/                # 移动端（UniApp）
├── server/                # 后端（webman）
│   ├── app/
│   │   ├── controller/    # 控制器
│   │   ├── service/       # 业务
│   │   ├── repository/    # 数据访问
│   │   ├── model/         # 模型
│   │   ├── adminapi/      # 管理端接口
│   │   ├── api/           # 用户端接口
│   │   └── queue/         # 队列消费
│   ├── core/              # 框架核心（认证 / 支付 / 存储 / 安装）
│   └── public/            # 安装向导、构建后的 admin / pc
├── docker/                # nginx + webman + MySQL + Redis
├── LICENSE
└── README.md
```

## 系统截图

### 管理后台

| | |
|---|---|
| ![登录页](https://docs.dev007.cn/admin/demo/admin01.png) | ![控制台](https://docs.dev007.cn/admin/demo/admin02.png) |
| ![系统管理](https://docs.dev007.cn/admin/demo/admin03.png) | ![更多功能](https://docs.dev007.cn/admin/demo/admin04.png) |

### PC 端

| | |
|---|---|
| ![PC首页](https://docs.dev007.cn/admin/demo/pc01.png) | ![PC详情](https://docs.dev007.cn/admin/demo/pc02.png) |

### 移动端

| | | | |
|---|---|---|---|
| ![移动端首页](https://docs.dev007.cn/admin/demo/mobile01.png) | ![移动端功能](https://docs.dev007.cn/admin/demo/mobile02.png) | ![移动端详情](https://docs.dev007.cn/admin/demo/mobile03.png) | ![移动端个人中心](https://docs.dev007.cn/admin/demo/mobile04.png) |

## 开源协议

[MIT License](LICENSE)

## 联系我们

<p align="center">
  <img src="https://www.dev007.cn/support.png" alt="联系我们" width="800">
</p>

## 链接

- 在线演示: [https://admin.dev007.cn](https://admin.dev007.cn)
- 文档中心: [http://docs.dev007.cn/admin/](http://docs.dev007.cn/admin/)
- GitHub: [https://github.com/yuandianxitong/ydadmin](https://github.com/yuandianxitong/ydadmin)
- Gitee: [https://gitee.com/yuandianxitong/ydadmin](https://gitee.com/yuandianxitong/ydadmin)
