# 路由系统

## 概述

本项目采用 **动态路由** 机制：登录后从后端接口获取菜单数据，在前端转换为 `vue-router` 的路由记录并动态挂载。静态路由仅包含登录页和错误页。

## 静态路由

定义在 `src/router/routes.config.ts` 中，应用启动即注册，无需权限：

```ts
// 常量路由：无需权限的基础页面
export const constantRoutes: Array<RouteRecordRaw> = [
    { path: '/:pathMatch(.*)*', component: () => import('@/views/error/404.vue') },
    { path: '/403', component: () => import('@/views/error/403.vue') },
    { path: '/500', component: () => import('@/views/error/500.vue') },
    { path: '/login', component: () => import('@/views/login/index.vue') }
]

// 动态路由的父容器，登录后挂载子路由
export const INDEX_ROUTE: RouteRecordRaw = {
    path: '/',
    component: LAYOUT,
    name: 'INDEX_ROUTE'
}
```

## 动态路由机制

### 流程概览

```
用户登录 → 获取 Token
    → permission.guard 拦截
    → 调用 userStore.getUserInfo() 获取用户信息和菜单
    → filterAsyncRoutes() 将菜单数据转换为 RouteRecordRaw[]
    → router.addRoute() 动态挂载到 INDEX_ROUTE 下
    → 导航到目标页面
```

### 菜单数据转换

后端返回的菜单结构（`MenuInfo`）通过 `filterAsyncRoutes()` 递归转换：

```ts
// src/router/index.ts
export function filterAsyncRoutes(routes: MenuInfo[], firstRoute = true): RouteRecordRaw[] {
    return routes
        .map((route) => {
            const routeRecord = createRouteRecord(route, firstRoute)
            if (routeRecord && route.children?.length) {
                routeRecord.children = filterAsyncRoutes(route.children, false)
            }
            return routeRecord
        })
        .filter(Boolean)
}
```

### 页面组件自动映射

使用 Vite 的 `import.meta.glob` 动态导入 `views/` 下的所有 `.vue` 文件：

```ts
const modules = import.meta.glob('../views/**/*.vue')

export function loadRouteView(component: string) {
    const key = Object.keys(modules).find((key) => key.includes(`/${component}.vue`))
    if (key) return modules[key]
    throw new Error(`Component not found: ${component}`)
}
```

后端菜单中 `component` 字段的值（如 `system/admin/index`）会自动映射到 `src/views/system/admin/index.vue`。

## 路由守卫

守卫注册在 `src/router/guards/` 目录下，按职责拆分：

### 1. permission.guard.ts（权限守卫）

核心守卫，负责登录鉴权和动态路由挂载：

- **白名单放行**：`/login` 和 `/403` 无需 Token 直接放行
- **Token 检查**：无 Token 重定向至登录页，并携带 `redirect` 参数
- **动态路由加载**：首次进入时拉取用户信息和菜单，生成并挂载动态路由
- **进度条**：使用 NProgress 展示页面加载进度
- **页面标题**：根据路由 `meta.title` 动态设置 `document.title`

### 2. auth.guard.ts（初始化守卫）

负责在非登录页首次访问时拉取后端全局配置（`appStore.getConfig()`），并根据配置设置 favicon 等。

## 路由元信息（meta）

每个动态路由会携带以下 meta 字段：

| 字段 | 类型 | 说明 |
|------|------|------|
| `title` | `string` | 页面标题（显示在标签页和面包屑） |
| `icon` | `string` | 菜单图标 |
| `hidden` | `boolean` | 是否在菜单中隐藏 |
| `keepAlive` | `boolean` | 是否缓存页面组件 |
| `perms` | `string` | 权限标识 |
| `type` | `number` | 菜单类型（1=目录, 2=菜单, 3=按钮） |
| `activeMenu` | `string` | 高亮的菜单路径（用于详情页等隐藏菜单） |

## 路由重置

退出登录时需要清理动态路由，避免残留：

```ts
export function resetRouter(): void {
    const userStore = useUserStore()
    // 移除动态挂载的父路由（连带移除子路由）
    if (router.hasRoute(INDEX_ROUTE_NAME)) {
        router.removeRoute(INDEX_ROUTE_NAME)
    }
    userStore.isRoutesInited = false
}
```

## 新增页面步骤

1. 在 `src/views/` 下创建页面组件（如 `src/views/product/list/index.vue`）
2. 在后端管理后台添加对应菜单项，`component` 填写 `product/list/index`
3. 登录后系统自动加载路由，无需手动修改前端路由配置
