# 前端架构

## 技术栈概述

| 技术 | 版本 | 说明 |
|------|------|------|
| Vue 3 | 3.x | 组合式 API（Composition API） |
| TypeScript | 5.x | 类型安全 |
| Element Plus | 2.x | UI 组件库 |
| Vite | 5.x | 构建工具，支持热更新和快速打包 |
| Pinia | 2.x | 状态管理 |
| UnoCSS | 最新 | 原子化 CSS 引擎 |
| vue-i18n | 9.x | 国际化（中/英文） |
| Axios | 最新 | HTTP 请求 |
| vue-router | 4.x | 路由管理，支持动态路由 |

## 项目结构

```
admin/src/
├── api/                # API 接口定义，按模块划分
│   ├── auth.ts         # 认证相关接口
│   ├── admin.ts        # 管理员管理
│   ├── menu.ts         # 菜单接口
│   └── system/         # 系统设置相关
├── assets/             # 静态资源（图片、SVG 图标）
├── components/         # 公共组件
│   ├── SearchForm/     # 可折叠搜索表单
│   ├── StatusTag/      # 状态标签
│   ├── ImportData/     # 数据导入
│   ├── DragSort/       # 拖拽排序
│   ├── Watermark/      # 页面水印
│   └── ...
├── constants/          # 常量定义（缓存键、页面路径、请求配置等）
├── directives/         # 自定义指令（权限、复制）
├── hooks/              # 组合式函数（分页、锁函数、字典等）
├── layout/             # 页面布局组件
├── locales/            # 国际化语言包（zh-CN、en-US）
├── router/             # 路由配置与守卫
│   ├── index.ts        # 路由实例与动态路由工具函数
│   ├── routes.config.ts # 静态路由定义
│   └── guards/         # 路由守卫（鉴权、权限、初始化）
├── store/modules/      # Pinia Store
│   ├── app.store.ts    # 全局配置
│   ├── user.store.ts   # 用户信息与权限
│   ├── settings.store.ts # 主题与布局设置
│   └── multipleTabs.store.ts # 多标签页
├── styles/             # 全局样式（SCSS）
├── theme/              # 主题 Token 与切换逻辑
├── types/              # TypeScript 类型定义
├── utils/              # 工具函数（请求、缓存、验证等）
└── views/              # 页面组件（按功能模块组织）
```

## 构建和开发命令

```bash
cd admin

# 安装依赖
npm install

# 启动开发服务器（默认端口 5174）
npm run dev

# 构建生产版本
npm run build

# TypeScript 类型检查
npm run type-check

# ESLint 代码检查和自动修复
npm run lint
```

## 环境变量配置

环境变量文件位于 `admin/` 根目录，Vite 约定以 `VITE_` 前缀的变量才会暴露给前端代码。

### `.env.development`（开发环境）

```dotenv
NODE_ENV = development
VITE_APP_API_URL = http://127.0.0.1:8005
VITE_APP_PORT = 5174
VITE_APP_TITLE = 管理后台
VITE_APP_DEBUG = true
VITE_APP_UPLOAD_URL = http://127.0.0.1:8005/adminapi/upload
```

### `.env.production`（生产环境）

```dotenv
NODE_ENV = production
VITE_APP_API_URL = https://your-domain.com
VITE_APP_TITLE = 管理后台
VITE_APP_DEBUG = false
```

### 在代码中使用

```ts
// 读取环境变量
const apiUrl = import.meta.env.VITE_APP_API_URL
const isDev = import.meta.env.DEV  // Vite 内置布尔值
```

## Vite 配置要点

- **路径别名**：`@` 映射到 `src/` 目录
- **自动导入**：通过 `unplugin-auto-import` 和 `unplugin-vue-components` 自动导入 Vue API 和 Element Plus 组件
- **UnoCSS**：通过 Vite 插件集成，扫描 `src/` 下的 Vue/TS 文件
- **代理**：开发环境通过 Vite proxy 将 API 请求转发到后端服务
