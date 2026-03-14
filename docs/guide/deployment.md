# 部署指南

## Docker 部署（推荐）

### 前置条件

- Docker 20.10+
- Docker Compose 2.0+

### 步骤

```bash
# 1. 克隆项目
git clone https://github.com/yuandianxitong/ydadmin.git
cd ydadmin

# 2. 配置环境变量
cp server/.env.example server/.env
# 编辑 .env 文件，修改数据库密码、JWT 密钥等

# 3. 启动服务
docker-compose up -d

# 4. 初始化数据库
docker-compose exec php php think migrate:run
docker-compose exec php php think seed:run

# 5. 构建前端
cd admin && npm install && npm run build
```

## 宝塔面板部署

### 环境要求

- PHP 8.0+（开启 pdo_mysql、mbstring、curl、fileinfo 扩展）
- MySQL 5.7+ 或 8.0+
- Nginx 或 Apache
- Node.js 18+
- Composer 2.0+

### 步骤

#### 1. 上传源码

将项目代码上传到服务器，如 `/www/wwwroot/ydadmin`

#### 2. 配置网站

在宝塔面板中创建网站，网站目录指向 `server/public/`

Nginx 配置添加伪静态规则：

```nginx
location / {
    if (!-e $request_filename) {
        rewrite ^(.*)$ /index.php/$1 last;
    }
}

location ~ \.php$ {
    fastcgi_pass unix:/tmp/php-cgi-80.sock;
    fastcgi_index index.php;
    include fastcgi.conf;
    fastcgi_param PATH_INFO $fastcgi_path_info;
}
```

#### 3. 安装后端

```bash
cd /www/wwwroot/ydadmin/server
composer install --no-dev --optimize-autoloader
cp .env.example .env
# 编辑 .env 配置数据库

php think migrate:run
php think seed:run
```

#### 4. 构建前端

```bash
cd /www/wwwroot/ydadmin/admin
npm install
npm run build
```

将 `admin/dist/` 目录内容部署到管理后台域名指向的目录。

#### 5. 目录权限

确保以下目录有写权限：

```bash
chmod -R 755 server/runtime
chmod -R 755 server/public/storage
```

## 手动部署

### 1. 安装依赖

```bash
# 后端
cd server && composer install

# 前端
cd admin && npm install

# 移动端
cd uniapp && pnpm install
```

### 2. 初始化

使用初始化脚本一键配置：

```bash
./setup.sh
```

或手动执行：

```bash
cd server
cp .env.example .env
php think migrate:run
php think seed:run
```

### 3. 构建部署

```bash
# 构建管理后台
cd admin && npm run build

# 构建微信小程序
cd uniapp && pnpm build:mp-weixin

# 构建 H5
cd uniapp && pnpm build:h5
```

## 生产环境建议

- 关闭调试模式：`.env` 中设置 `APP_DEBUG = false`
- 使用 HTTPS
- 配置 Redis 作为缓存和 Session 驱动
- 配置 CDN 加速静态资源
- 设置定时任务：`* * * * * php /path/to/think cron:run`
- 设置日志轮转，避免日志文件过大
- 首次登录后修改默认管理员密码
