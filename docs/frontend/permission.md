# 权限控制

## 概述

本系统采用 **菜单权限 + 按钮权限** 的双层控制方案：

- **菜单权限**：通过后端返回的菜单数据控制可访问的页面（动态路由）
- **按钮权限**：通过权限标识字符串控制页面内按钮和操作的显隐

权限数据在用户登录后从后端获取，存储在 `userStore.permissions` 中。

## 权限指令

权限指令定义在 `src/directives/perms.ts` 中，提供两种指令：

### v-has-perm（满足任一权限）

传入单个权限标识或权限数组，满足 **任意一个** 即显示元素（OR 关系）：

```vue
<!-- 单个权限 -->
<el-button v-has-perm="'admin.add'">新增管理员</el-button>

<!-- 多个权限，满足任一即可 -->
<el-button v-has-perm="['admin.edit', 'admin.update']">编辑</el-button>
```

### v-has-all-perm（满足所有权限）

传入权限数组，必须 **全部满足** 才显示元素（AND 关系）：

```vue
<!-- 同时拥有 edit 和 audit 权限才显示 -->
<el-button v-has-all-perm="['order.edit', 'order.audit']">编辑并审核</el-button>
```

### 指令工作原理

权限指令通过设置 `display: none` 隐藏元素，并在权限变化时自动响应更新：

```ts
// 核心检查逻辑
function checkPermission(permissions: string | string[], userStore: any): boolean {
    if (Array.isArray(permissions)) {
        return userStore.hasAnyPermission(permissions) // OR 关系
    } else {
        return userStore.hasPermission(permissions)    // 单个权限
    }
}
```

指令在 `mounted` 时注册对 `userStore.permissions` 的 `watch`，确保权限动态变化时 UI 自动更新。

### 向后兼容

系统同时注册了 `v-permission` 和 `v-perms` 作为 `v-has-perm` 的别名，旧代码无需迁移。

## 编程式权限检查

在需要在逻辑中（而非模板中）判断权限时，使用 `userStore` 提供的方法：

### hasPermission(permission)

检查是否拥有 **单个** 权限：

```ts
import { useUserStore } from '@/store'

const userStore = useUserStore()

if (userStore.hasPermission('admin.delete')) {
    // 执行删除操作
}
```

### hasAnyPermission(permissions)

检查是否拥有权限列表中的 **任意一个**：

```ts
if (userStore.hasAnyPermission(['admin.edit', 'admin.update'])) {
    // 显示编辑按钮
}
```

### hasAllPermissions(permissions)

检查是否 **同时拥有** 所有权限：

```ts
if (userStore.hasAllPermissions(['order.edit', 'order.audit'])) {
    // 显示高级操作
}
```

### 超级管理员

当权限列表包含 `*` 或 `super_admin` 时，所有权限检查均返回 `true`：

```ts
function hasPermission(permission: string): boolean {
    if (permissions.value.includes('*') || permissions.value.includes('super_admin')) {
        return true
    }
    return permissions.value.includes(permission)
}
```

## 按钮级权限控制完整示例

```vue
<template>
    <div>
        <!-- 工具栏 -->
        <div class="toolbar">
            <el-button v-has-perm="'article.add'" type="primary" @click="handleAdd">
                新增文章
            </el-button>
            <el-button v-has-perm="'article.export'" @click="handleExport">
                导出
            </el-button>
        </div>

        <!-- 表格操作列 -->
        <el-table :data="list">
            <el-table-column label="操作">
                <template #default="{ row }">
                    <el-button v-has-perm="'article.edit'" link @click="handleEdit(row)">
                        编辑
                    </el-button>
                    <el-button v-has-perm="'article.delete'" link type="danger" @click="handleDelete(row)">
                        删除
                    </el-button>
                </template>
            </el-table-column>
        </el-table>
    </div>
</template>

<script setup lang="ts">
import { useUserStore } from '@/store'

const userStore = useUserStore()

// 编程式判断（例如控制整个区块的显隐）
const showAdvanced = computed(() => userStore.hasAllPermissions(['article.edit', 'article.audit']))
</script>
```

## 权限标识命名约定

权限标识由后端定义，建议采用 `模块.操作` 格式：

| 权限标识 | 说明 |
|----------|------|
| `admin.list` | 查看管理员列表 |
| `admin.add` | 新增管理员 |
| `admin.edit` | 编辑管理员 |
| `admin.delete` | 删除管理员 |
| `admin.status` | 修改管理员状态 |
