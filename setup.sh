#!/bin/bash
set -e

echo "========================================="
echo "  Dev007 Framework - 开发环境初始化"
echo "========================================="

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

check_command() {
    if ! command -v "$1" &> /dev/null; then
        echo -e "${RED}✗ $1 未安装，请先安装 $1${NC}"
        exit 1
    fi
    echo -e "${GREEN}✓ $1 已安装${NC}"
}

echo ""
echo "检查环境依赖..."
check_command php
check_command composer
check_command node
check_command pnpm

# 后端初始化
echo ""
echo -e "${YELLOW}[1/6] 初始化后端...${NC}"
cd server

if [ ! -f .env ]; then
    cp .env.example .env
    echo -e "${GREEN}✓ 已复制 .env.example → .env${NC}"
    echo -e "${YELLOW}  请编辑 server/.env 配置数据库连接信息${NC}"
else
    echo -e "${GREEN}✓ .env 已存在，跳过${NC}"
fi

echo ""
echo -e "${YELLOW}[2/6] 安装后端依赖...${NC}"
composer install --no-interaction

echo ""
echo -e "${YELLOW}[3/6] 生成 JWT 密钥...${NC}"
if grep -q "^JWT_SECRET=$" .env 2>/dev/null || grep -q "^JWT_SECRET=your_jwt_secret" .env 2>/dev/null || grep -q "^JWT_SECRET = change-me-jwt-secret" .env 2>/dev/null; then
    JWT_SECRET=$(openssl rand -base64 32)
    if [[ "$OSTYPE" == "darwin"* ]]; then
        sed -i '' "s|^JWT_SECRET.*|JWT_SECRET = ${JWT_SECRET}|" .env
    else
        sed -i "s|^JWT_SECRET.*|JWT_SECRET = ${JWT_SECRET}|" .env
    fi
    echo -e "${GREEN}✓ JWT 密钥已生成${NC}"
else
    echo -e "${GREEN}✓ JWT 密钥已存在，跳过${NC}"
fi

echo ""
echo -e "${YELLOW}[4/6] 数据库迁移 + 种子数据...${NC}"
echo "请确保已在 .env 中配置正确的数据库连接信息"
read -p "是否执行数据库迁移？(y/n) " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    php think migrate:run
    php think seed:run -s DatabaseSeeder
    echo -e "${GREEN}✓ 数据库初始化完成${NC}"
else
    echo -e "${YELLOW}⚠ 跳过数据库迁移，请稍后手动执行：${NC}"
    echo "  cd server && php think migrate:run && php think seed:run -s DatabaseSeeder"
fi

cd ..

echo ""
echo -e "${YELLOW}[5/6] 安装管理后台前端依赖...${NC}"
cd admin && npm install && cd ..

echo ""
echo -e "${YELLOW}[6/6] 安装移动端依赖...${NC}"
cd uniapp && pnpm install && cd ..

echo ""
echo "========================================="
echo -e "${GREEN}  ✓ 初始化完成！${NC}"
echo "========================================="
echo ""
echo "启动开发服务器："
echo "  后端:   cd server && php think run"
echo "  前端:   cd admin && npm run dev"
echo "  移动端: cd uniapp && pnpm run dev:h5"
echo ""
echo "默认管理员账号: admin / admin123456"
