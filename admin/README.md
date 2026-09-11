# Admin 管理后台

基于 Vue 3 + TypeScript + Element Plus 的管理后台系统

## 技术栈

- **Vue 3** - 现代化前端框架
- **TypeScript** - 类型安全的JavaScript
- **Element Plus** - UI组件库
- **Pinia** - 状态管理
- **Vue Router** - 路由管理
- **Vite** - 构建工具
- **UnoCSS** - 原子化CSS

## 功能特色

### 🔐 完整的权限系统
- JWT认证机制
- RBAC权限控制
- 页面级权限控制
- 按钮级权限控制
- 动态菜单路由

### 📋 核心管理功能
- **用户管理** - 管理员账号管理、角色分配、密码重置
- **角色管理** - 角色权限管理、权限分配
- **菜单管理** - 系统菜单配置、树形结构管理
- **系统配置** - 系统参数配置、分组管理
- **操作日志** - 操作记录、日志分析、数据导出

### 🎨 用户体验
- 响应式设计
- 暗色模式支持
- 多语言国际化
- 表单验证
- 加载状态提示
- 批量操作支持

## 项目结构

```
admin/
├── src/
│   ├── api/                 # API接口层
│   │   ├── auth.ts         # 认证接口
│   │   ├── admin.ts        # 管理员接口  
│   │   ├── role.ts         # 角色接口
│   │   ├── menu.ts         # 菜单接口
│   │   ├── config.ts       # 配置接口
│   │   └── log.ts          # 日志接口
│   ├── components/         # 通用组件
│   ├── directives/         # 自定义指令
│   │   └── perms.ts        # 权限指令
│   ├── pages/              # 页面组件
│   │   ├── login/          # 登录页面
│   │   └── system/         # 系统管理
│   │       ├── admin/      # 用户管理
│   │       ├── role/       # 角色管理
│   │       ├── menu/       # 菜单管理
│   │       ├── config/     # 配置管理
│   │       └── log/        # 日志管理
│   ├── store/              # 状态管理
│   │   └── modules/
│   │       └── auth.ts     # 认证状态
│   ├── types/              # 类型定义
│   │   └── api.d.ts        # API类型
│   ├── utils/              # 工具函数
│   │   └── request.ts      # 请求封装
│   └── main.ts             # 应用入口
├── .env.development        # 开发环境配置
└── package.json
```

## 开发指南

### 环境要求
- Node.js >= 18
- pnpm >= 9

### 安装依赖
```bash
pnpm install
```

### 开发运行
```bash
pnpm dev
```

### 构建生产
```bash
pnpm build
```

### 类型检查
```bash
pnpm type-check
```

### 代码格式化
```bash
pnpm lint
```

## 环境配置

复制 `.env.development` 文件并根据实际情况修改：

```bash
# 接口地址 - 修改为你的后端API地址
VITE_APP_API_URL = http://localhost:8080/adminapi

# 应用标题
VITE_APP_TITLE = 管理后台

# 是否开启调试
VITE_APP_DEBUG = true
```

## 接口对接

系统对接 ThinkPHP 后端 API：

- **认证接口**: `/adminapi/auth/*`
- **管理员接口**: `/adminapi/system/admin/*` 
- **角色接口**: `/adminapi/system/role/*`
- **菜单接口**: `/adminapi/system/menu/*`
- **配置接口**: `/adminapi/system/config/*`
- **日志接口**: `/adminapi/system/log/*`

## 权限配置

系统使用以下权限标识：

```javascript
// 管理员管理
'system.admin.create'   // 创建管理员
'system.admin.update'   // 更新管理员
'system.admin.delete'   // 删除管理员
'system.admin.view'     // 查看管理员

// 角色管理
'system.role.create'    // 创建角色
'system.role.update'    // 更新角色
'system.role.delete'    // 删除角色
'system.role.assign'    // 分配权限

// 菜单管理
'system.menu.create'    // 创建菜单
'system.menu.update'    // 更新菜单
'system.menu.delete'    // 删除菜单

// 配置管理
'system.config.create'  // 创建配置
'system.config.update'  // 更新配置
'system.config.delete'  // 删除配置

// 日志管理
'system.log.view'       // 查看日志
'system.log.delete'     // 删除日志
'system.log.export'     // 导出日志
```

## 使用说明

### 登录系统
默认账号密码：`admin` / `123456`

### 权限控制

#### 在模板中使用权限指令
```vue
<!-- 按钮权限控制 -->
<el-button v-permission="'system.admin.create'">新增用户</el-button>

<!-- 多权限控制（或关系） -->
<el-button v-permission="['system.admin.create', 'system.admin.update']">操作</el-button>
```

#### 在脚本中使用权限判断
```typescript
import { useAuthStore } from '@/store'

const authStore = useAuthStore()

// 单权限判断
const canCreate = authStore.hasPermission('system.admin.create')

// 多权限判断（或关系）
const canOperate = authStore.hasAnyPermission(['system.admin.create', 'system.admin.update'])

// 多权限判断（与关系）
const canAll = authStore.hasAllPermissions(['system.admin.create', 'system.admin.update'])
```

## 开发规范

### 命名规范
- **文件命名**: kebab-case (`user-list.vue`)
- **组件命名**: PascalCase (`UserList`)
- **变量命名**: camelCase (`isLoading`)
- **常量命名**: SCREAMING_SNAKE_CASE (`API_BASE_URL`)

### API调用规范
```typescript
// 使用统一的API调用
import { adminApi } from '@/api/admin'

// 带类型的API调用
const response = await adminApi.getAdminList({
  page: 1,
  limit: 20,
  keyword: 'admin'
})
```

### 错误处理
系统已统一处理HTTP错误：
- `401` - 自动跳转登录页
- `403` - 提示权限不足
- `422` - 显示参数验证错误

## 部署说明

### 构建
```bash
pnpm build
```

### Nginx配置示例
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/dist;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location /adminapi {
        proxy_pass http://your-backend-api;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

## 常见问题

### Q: 如何添加新的功能模块？
A: 
1. 在 `src/pages/` 下创建模块目录
2. 在 `src/api/` 下创建对应的API文件
3. 在后端添加对应的权限配置
4. 在菜单管理中添加菜单项

### Q: 如何自定义主题？
A: 修改 `src/styles/` 下的变量文件，或使用Element Plus的主题定制功能

### Q: 接口请求失败怎么办？
A: 
1. 检查 `.env.development` 中的API地址配置
2. 确认后端服务正常运行
3. 检查浏览器控制台的网络请求详情

## 更新日志

### v1.0.0 (2024-01-15)
- 🎉 初始版本发布
- ✨ 完整的RBAC权限系统
- ✨ 用户、角色、菜单、配置、日志管理
- ✨ JWT认证和权限控制
- ✨ 响应式设计和现代化UI

## License

MIT License