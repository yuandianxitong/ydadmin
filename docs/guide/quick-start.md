# 快速开始

## 环境要求

在开始之前，请确保你的开发环境满足以下要求：

| 依赖 | 最低版本 | 推荐版本 |
|---|---|---|
| PHP | 8.0+ | 8.2+ |
| MySQL | 5.7+ | 8.0+ |
| Node.js | 18+ | 20+ |
| Composer | 2.0+ | 最新版 |
| pnpm | 8.0+ | 最新版 |

## 获取源码

```bash
git clone https://github.com/user/dev007-framework.git
cd dev007-framework
```

## 后端安装

### 1. 安装依赖

```bash
cd server
composer install
```

### 2. 配置环境

复制环境配置文件并根据实际情况修改数据库等连接信息：

```bash
cp .env.example .env
```

编辑 `.env` 文件，配置数据库连接：

```ini
DB_TYPE = mysql
DB_HOST = 127.0.0.1
DB_NAME = dev007
DB_USER = root
DB_PASSWORD = your_password
DB_PORT = 3306
DB_CHARSET = utf8mb4
```

### 3. 数据库初始化

创建数据库，然后执行迁移和数据填充：

```bash
# 执行数据库迁移
php think migrate:run

# 填充初始数据（管理员账号、基础配置等）
php think seed:run
```

### 4. 启动后端服务

```bash
php think run
```

后端服务默认运行在 `http://localhost:8000`。

## 管理后台前端安装

```bash
cd admin
npm install
npm run dev
```

管理后台默认运行在 `http://localhost:5173`。

## 移动端安装（UniApp）

```bash
cd uniapp
pnpm install
```

使用 HBuilderX 打开 `uniapp/` 目录运行，或通过命令行：

```bash
# 微信小程序
pnpm dev:mp-weixin

# H5
pnpm dev:h5
```

## 默认账号

系统初始化后，可以使用以下默认账号登录管理后台：

| 角色 | 账号 | 密码 |
|---|---|---|
| 超级管理员 | admin | admin123456 |

::: warning 安全提示
首次登录后请立即修改默认密码。
:::

## 访问地址

| 服务 | 地址 |
|---|---|
| 后端 API | http://localhost:8000 |
| 管理后台 | http://localhost:5173 |
| API 文档 | http://localhost:8000/doc |

## 常见问题

### Composer 安装失败

如果 `composer install` 失败，尝试：

```bash
# 更换为国内镜像
composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/

# 忽略平台要求（不推荐，仅用于排查问题）
composer install --ignore-platform-reqs
```

### 数据库连接失败

- 确认 MySQL 服务已启动
- 确认 `.env` 中的数据库配置正确
- 确认数据库已创建：`CREATE DATABASE dev007 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`

### 前端启动报错

- 确认 Node.js 版本 >= 18
- 删除 `node_modules` 后重新安装：`rm -rf node_modules && npm install`
- 检查 `.env` 或 `vite.config.ts` 中的 API 代理地址是否正确

### 端口被占用

如果默认端口被占用，可以指定其他端口：

```bash
# 后端
php think run -p 8080

# 前端（修改 vite.config.ts 中的 server.port）
npm run dev -- --port 3000
```
