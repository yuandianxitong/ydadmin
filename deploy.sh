#!/bin/bash
set -e

echo "=============================="
echo "  元点Admin Docker 部署脚本"
echo "=============================="

# 检查 Docker
if ! command -v docker &> /dev/null; then
    echo "错误: 请先安装 Docker"
    exit 1
fi

if ! command -v docker-compose &> /dev/null && ! docker compose version &> /dev/null; then
    echo "错误: 请先安装 Docker Compose"
    exit 1
fi

# 判断使用 docker-compose 还是 docker compose
COMPOSE_CMD="docker compose"
if ! docker compose version &> /dev/null 2>&1; then
    COMPOSE_CMD="docker-compose"
fi

# 环境变量文件
if [ ! -f .env ]; then
    echo ">> 复制 .env.docker 为 .env ..."
    cp .env.docker .env
fi

# 构建前端
echo ">> 构建前端资源 ..."
if command -v pnpm &> /dev/null; then
    cd admin && pnpm install && pnpm run build && cd ..
elif command -v npm &> /dev/null; then
    cd admin && npm install && npm run build && cd ..
else
    echo "错误: 请先安装 Node.js 和 pnpm/npm"
    exit 1
fi

# 后端依赖
echo ">> 安装后端依赖 ..."
if command -v composer &> /dev/null; then
    cd server && composer install --no-dev --optimize-autoloader && cd ..
else
    echo ">> 将在 Docker 容器内安装 Composer 依赖 ..."
fi

# 生成后端 .env
if [ ! -f server/.env ]; then
    echo ">> 生成后端 .env 配置 ..."
    cp server/.env.example server/.env
    # 替换数据库连接为 Docker 容器配置
    sed -i.bak 's/HOST = 127.0.0.1/HOST = mysql/' server/.env
    sed -i.bak 's/NAME = dev007/NAME = '${MYSQL_DATABASE:-dev007}'/' server/.env
    sed -i.bak 's/USER = root/USER = '${MYSQL_USER:-yuandian}'/' server/.env
    sed -i.bak 's/PASS = root/PASS = '${MYSQL_PASSWORD:-yuandian123}'/' server/.env
    rm -f server/.env.bak
fi

# 启动 Docker
echo ">> 启动 Docker 容器 ..."
$COMPOSE_CMD up -d --build

echo ""
echo "=============================="
echo "  部署完成!"
echo "  访问地址: http://localhost:${NGINX_PORT:-80}"
echo "=============================="
echo ""
echo "提示: 首次部署请访问 /install 进行数据库初始化"
echo "MySQL 数据库信息:"
echo "  Host: mysql (容器内) / localhost:${MYSQL_PORT:-3306} (宿主机)"
echo "  Database: ${MYSQL_DATABASE:-dev007}"
echo "  User: ${MYSQL_USER:-yuandian}"
echo "  Password: ${MYSQL_PASSWORD:-yuandian123}"
