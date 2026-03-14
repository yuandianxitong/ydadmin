# 配置说明

## 后端配置

### 环境变量

后端配置文件位于 `server/.env`，从 `.env.example` 复制后修改：

```bash
cp server/.env.example server/.env
```

主要配置项：

```ini
# 应用配置
APP_DEBUG = true
DEFAULT_TIMEZONE = Asia/Shanghai

# 数据库
DB_TYPE = mysql
DB_HOST = 127.0.0.1
DB_NAME = dev007
DB_USER = root
DB_PASSWORD = your_password
DB_PORT = 3306
DB_CHARSET = utf8mb4
DB_PREFIX =

# Redis（可选，用于缓存和队列）
REDIS_HOST = 127.0.0.1
REDIS_PORT = 6379
REDIS_PASSWORD =

# JWT
JWT_SECRET = your_jwt_secret_key
JWT_EXPIRE = 7200

# 文件上传
UPLOAD_MAX_SIZE = 10485760
UPLOAD_ALLOWED_EXT = jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx
```

### 系统配置管理

系统运行时配置存储在 `system_configs` 表中，通过管理后台「系统设置」页面管理。支持的配置类型：

| 类型 | 说明 | 示例 |
|------|------|------|
| `string` | 字符串 | 站点名称、联系方式 |
| `number` | 数字 | 分页大小、超时时间 |
| `boolean` | 布尔值 | 开关类配置 |
| `json` | JSON 对象 | 复杂结构配置 |
| `file` | 文件路径 | Logo、图片配置 |

配置更新后通过 `config.changed` 事件自动清除缓存。

## 前端配置

### Vite 环境变量

前端环境变量文件位于 `admin/` 目录下：

- `.env` — 所有环境共用
- `.env.development` — 开发环境
- `.env.production` — 生产环境

```ini
# API 基础地址
VITE_API_BASE_URL = http://localhost:8000

# 应用标题
VITE_APP_TITLE = Dev007 管理后台
```

### API 代理

开发环境下，`vite.config.ts` 中配置 API 代理避免跨域：

```typescript
server: {
  proxy: {
    '/adminapi': {
      target: 'http://localhost:8000',
      changeOrigin: true,
    },
  },
}
```

## 移动端配置

### UniApp 配置

移动端配置文件：

| 文件 | 说明 |
|------|------|
| `manifest.json` | 应用配置（AppID、权限等） |
| `pages.json` | 路由和页面配置 |
| `vite.config.ts` | 构建配置 |

API 地址配置在 `uniapp/src/utils/request.ts` 中：

```typescript
const BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000'
```

## Docker 配置

项目提供 `docker-compose.yml` 用于容器化部署，包含以下服务：

- **PHP** — PHP-FPM 应用服务
- **Nginx** — Web 服务器
- **MySQL** — 数据库
- **Redis** — 缓存服务

```bash
# 启动所有服务
docker-compose up -d

# 查看服务状态
docker-compose ps

# 查看日志
docker-compose logs -f
```
