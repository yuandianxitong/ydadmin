# 状态管理

## 概述

本项目使用 **Pinia** 进行状态管理，Store 文件位于 `src/store/modules/` 目录。共有 4 个核心 Store：

| Store | 文件 | 职责 |
|-------|------|------|
| `appStore` | `app.store.ts` | 全局配置、移动端检测、侧边栏状态 |
| `userStore` | `user.store.ts` | 用户信息、Token、权限、菜单 |
| `settingStore` | `settings.store.ts` | 主题、布局偏好设置 |
| `tabsStore` | `multipleTabs.store.ts` | 多标签页管理 |

## appStore -- 全局配置

管理从后端获取的系统配置，以及一些全局 UI 状态。

### State

| 字段 | 类型 | 说明 |
|------|------|------|
| `config` | `Record<string, any>` | 后端系统配置（站点名、logo、域名等） |
| `isMobile` | `boolean` | 是否移动端 |
| `isCollapsed` | `boolean` | 侧边栏是否折叠 |
| `isRouteShow` | `boolean` | 路由视图是否显示（用于页面刷新） |
| `isLoadingConfig` | `boolean` | 配置是否正在加载中（防止重复请求） |

### 核心方法

```ts
import useAppStore from '@/store/modules/app.store'

const appStore = useAppStore()

// 获取后端配置（带请求锁，防止重复调用）
await appStore.getConfig()

// 拼接图片完整地址
const fullUrl = appStore.getImageUrl('/storage/uploads/avatar.png')

// 折叠/展开侧边栏
appStore.toggleCollapsed()

// 刷新当前页面（通过移除再恢复路由视图实现）
appStore.refreshView()

// 设置是否移动端
appStore.setMobile(window.innerWidth < 768)
```

### getImageUrl 处理逻辑

```
输入 URL
  ├── 以 http 开头 → 直接返回
  ├── 开发环境 → 返回相对路径（由 Vite proxy 转发）
  └── 生产环境 → 拼接 oss_domain 或 site_url 前缀
```

## userStore -- 用户信息和权限

采用 Setup Store 风格（组合式 API），管理用户认证的完整生命周期。

### State

| 字段 | 类型 | 说明 |
|------|------|------|
| `token` | `Ref<string>` | JWT Token |
| `userInfo` | `Ref<AdminInfo>` | 用户信息对象 |
| `permissions` | `Ref<string[]>` | 权限标识列表 |
| `menus` | `Ref<MenuInfo[]>` | 原始菜单数据 |
| `routes` | `Ref<RouteRecordRaw[]>` | 已转换的动态路由 |
| `isRoutesInited` | `Ref<boolean>` | 动态路由是否已初始化 |

### Computed

| 字段 | 说明 |
|------|------|
| `getAdminName` | 用户名 |
| `getAdminNickname` | 昵称（优先）或用户名 |
| `getAdminAvatar` | 头像 URL |
| `getAdminRoles` | 角色信息列表 |
| `isLoggedIn` | 是否已登录 |

### 核心方法

```ts
import { useUserStore } from '@/store'

const userStore = useUserStore()

// 登录
await userStore.login({
    username: 'admin',
    password: '123456',
    captcha: 'abcd',
    captcha_key: 'key123'
})

// 获取用户信息和菜单（通常由路由守卫自动调用）
await userStore.getUserInfo()

// 登出
await userStore.logout()

// 刷新 Token
await userStore.refreshToken()

// 权限检查
userStore.hasPermission('admin.edit')        // 单个权限
userStore.hasAnyPermission(['a.edit', 'a.add']) // 满足任一
userStore.hasAllPermissions(['a.edit', 'a.audit']) // 满足全部
```

## settingStore -- 主题与布局设置

管理 UI 偏好设置，所有设置自动持久化到本地缓存。

### State

| 字段 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `theme` | `string` | -- | 主色调 |
| `themeMode` | `'system' \| 'light' \| 'dark'` | -- | 主题模式 |
| `darkTheme` | `string` | -- | 暗色主题色值 |
| `sideTheme` | `string` | -- | 侧边栏主题 |
| `sideWidth` | `number` | -- | 侧边栏宽度 |
| `showCrumb` | `boolean` | -- | 是否显示面包屑 |
| `showLogo` | `boolean` | -- | 是否显示 Logo |
| `openMultipleTabs` | `boolean` | -- | 是否启用多标签页 |
| `isUniqueOpened` | `boolean` | -- | 菜单是否只保持一个展开 |

### 核心方法

```ts
import useSettingStore from '@/store/modules/settings.store'

const settingStore = useSettingStore()

// 修改单项设置（自动持久化）
settingStore.setSetting({ key: 'showLogo', value: true })

// 应用主题模式
settingStore.applyThemeMode()

// 恢复默认设置
settingStore.resetTheme()
```

## tabsStore -- 多标签页

管理页面标签页的增删和状态持久化，通常通过 `useMultipleTabs` Hook 间接使用。

## 使用示例

### 完整的列表页

```vue
<script setup lang="ts">
import { reactive } from 'vue'
import useAppStore from '@/store/modules/app.store'
import { useUserStore } from '@/store'
import { usePaging } from '@/hooks/usePaging'
import { adminApi } from '@/api/admin'

const appStore = useAppStore()
const userStore = useUserStore()

// 搜索参数
const queryParams = reactive({ username: '', status: '' })

// 分页数据
const { pager, getLists, resetPage } = usePaging({
    fetchFun: adminApi.list,
    params: queryParams
})

getLists()

// 权限控制
const canEdit = computed(() => userStore.hasPermission('admin.edit'))
</script>

<template>
    <div>
        <SearchForm v-model="queryParams" @search="resetPage" @reset="resetPage">
            <el-form-item label="用户名">
                <el-input v-model="queryParams.username" />
            </el-form-item>
        </SearchForm>

        <el-table v-loading="pager.loading" :data="pager.lists">
            <el-table-column prop="username" label="用户名" />
            <el-table-column label="头像">
                <template #default="{ row }">
                    <el-avatar :src="appStore.getImageUrl(row.avatar)" />
                </template>
            </el-table-column>
            <el-table-column prop="status" label="状态">
                <template #default="{ row }">
                    <StatusTag :status="row.status" />
                </template>
            </el-table-column>
        </el-table>
    </div>
</template>
```
