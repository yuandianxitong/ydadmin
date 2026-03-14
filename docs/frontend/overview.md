# 前端概览

## 技术栈

- **Vue 3** — 渐进式 JavaScript 框架
- **TypeScript** — 类型安全
- **Element Plus** — UI 组件库
- **Vite** — 构建工具
- **Pinia** — 状态管理
- **UnoCSS** — 原子化 CSS 引擎
- **vue-i18n** — 国际化（中文/英文）

## 项目结构

```
admin/
├── src/
│   ├── api/              # API 接口封装（TypeScript）
│   ├── components/       # 公共组件
│   │   ├── SearchForm/   # 可折叠搜索表单
│   │   ├── TableColumnSetting/ # 表格列设置
│   │   ├── ImportData/   # 数据导入组件
│   │   └── Region/       # 地区级联选择器
│   ├── directives/       # 自定义指令（v-has-perm）
│   ├── locales/          # 国际化文件
│   │   ├── zh-CN.ts
│   │   └── en-US.ts
│   ├── router/           # 路由配置（动态路由）
│   ├── store/modules/    # Pinia Store
│   ├── types/            # TypeScript 类型定义
│   ├── utils/            # 工具函数
│   └── views/            # 页面组件
│       ├── system/       # 系统管理模块
│       └── content/      # 内容管理模块
├── vite.config.ts
└── package.json
```

## 开发命令

```bash
cd admin
npm install        # 安装依赖
npm run dev        # 开发服务器
npm run build      # 构建生产版本
npm run type-check # TypeScript 类型检查
npm run lint       # ESLint 检查和修复
```

## 动态路由

路由通过后端接口 `/adminapi/system/config/global` 获取菜单数据动态生成，无需手动配置路由文件。页面组件放在 `src/views/` 下，自动映射到路由。

## 权限控制

```html
<!-- 指令方式：满足任一权限即显示 -->
<el-button v-has-perm="['system.admin.create']">新增</el-button>

<!-- 指令方式：必须满足所有权限 -->
<el-button v-has-all-perm="['module.view', 'module.edit']">操作</el-button>
```

```typescript
// 编程方式
if (userStore.hasPermission('system.admin.update')) { ... }
```

## 页面开发规范

每个 CRUD 模块包含以下文件：

| 文件 | 说明 |
|------|------|
| `api/{module}.ts` | API 接口封装 |
| `views/{category}/{module}/index.vue` | 列表页面 |
| `views/{category}/{module}/components/{Module}Form.vue` | 表单弹窗 |
| `types/api.d.ts` | TypeScript 类型定义 |
| `locales/zh-CN.ts` / `en-US.ts` | 国际化文本 |

### 列表页标准结构

```
搜索卡片（el-card + el-form inline）
  └─ 搜索字段 + 搜索/重置按钮
表格卡片（el-card）
  ├─ 标题栏 + 操作按钮（v-has-perm）
  ├─ 数据表格（el-table + el-table-column）
  └─ 分页组件（el-pagination）
表单弹窗（子组件，v-model 控制显隐）
```

## 内置公共组件

| 组件 | 说明 |
|------|------|
| `SearchForm` | 可折叠的搜索表单，支持展开/收起 |
| `TableColumnSetting` | 表格列显示/隐藏切换 |
| `ImportData` | 拖拽文件导入，支持 xlsx/xls/csv |
| `Region` | 地区级联选择器，支持省市区三级 |
