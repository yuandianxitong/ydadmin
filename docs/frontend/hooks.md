# Composables / Hooks

## 概述

组合式函数（Hooks）位于 `src/hooks/` 目录，封装了分页、防重复提交、字典数据、路由监听和多标签页等通用逻辑。

## usePaging -- 分页数据

最常用的 Hook，封装了分页列表请求的完整逻辑，包括加载状态、翻页、重置等。

### 参数

| 参数 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `fetchFun` | `(params) => Promise` | -- | **必传**，分页接口请求函数 |
| `params` | `Record<string, any>` | `{}` | 动态搜索参数（会随搜索条件变化） |
| `fixedParams` | `Record<string, any>` | `{}` | 固定参数（如固定的类型筛选） |
| `page` | `number` | `1` | 初始页码 |
| `size` | `number` | `15` | 每页条数 |
| `firstLoading` | `boolean` | `false` | 初始是否显示 loading |

### 返回值

| 字段 | 类型 | 说明 |
|------|------|------|
| `pager` | `reactive` 对象 | 包含 `page`、`size`、`loading`、`count`、`lists`、`extend` |
| `getLists` | `() => Promise` | 请求当前页数据 |
| `resetPage` | `() => void` | 重置到第一页并重新请求 |
| `resetParams` | `() => void` | 重置搜索参数为初始值并重新请求 |

### 使用示例

```vue
<script setup lang="ts">
import { reactive } from 'vue'
import { usePaging } from '@/hooks/usePaging'
import { adminApi } from '@/api/admin'

// 搜索参数
const queryParams = reactive({
    username: '',
    status: ''
})

// 初始化分页
const { pager, getLists, resetPage, resetParams } = usePaging({
    fetchFun: adminApi.list,
    params: queryParams
})

// 首次加载
getLists()
</script>

<template>
    <SearchForm v-model="queryParams" @search="resetPage" @reset="resetParams" />

    <el-table v-loading="pager.loading" :data="pager.lists">
        <el-table-column prop="username" label="用户名" />
        <el-table-column prop="status" label="状态" />
    </el-table>

    <Pagination
        v-model="pager.page"
        :page-size="pager.size"
        :total="pager.count"
        @change="getLists"
    />
</template>
```

## useLockFn -- 防重复提交

将异步函数包装为"带锁"版本，在上一次调用完成前自动屏蔽后续调用，防止表单重复提交。

### 返回值

| 字段 | 类型 | 说明 |
|------|------|------|
| `isLock` | `Ref<boolean>` | 当前是否锁定中 |
| `lockFn` | `Function` | 包装后的安全函数 |

### 使用示例

```vue
<script setup lang="ts">
import { useLockFn } from '@/hooks/useLockFn'
import { adminApi } from '@/api/admin'

const { isLock, lockFn: handleSubmit } = useLockFn(async () => {
    await adminApi.add(formData)
    ElMessage.success('添加成功')
})
</script>

<template>
    <el-button type="primary" :loading="isLock" @click="handleSubmit">
        提交
    </el-button>
</template>
```

## useDictOptions -- 字典数据批量加载

批量请求多个字典/选项数据源，统一管理加载状态，支持自定义数据转换。

### 参数

传入一个对象，每个 key 对应一个数据源配置：

| 字段 | 类型 | 说明 |
|------|------|------|
| `api` | `Function` | 请求函数 |
| `params` | `Record<string, any>` | 请求参数（可选） |
| `transformData` | `(data) => SelectOption[]` | 数据转换函数（可选） |

### 返回值

| 字段 | 类型 | 说明 |
|------|------|------|
| `optionsData` | `reactive` 对象 | 各字典数据，key 与传入参数一致 |
| `refresh` | `() => Promise` | 重新拉取所有字典数据 |

### 使用示例

```vue
<script setup lang="ts">
import { useDictOptions } from '@/hooks/useDictOptions'
import { roleApi } from '@/api/role'
import { departmentApi } from '@/api/department'

const { optionsData } = useDictOptions({
    roleList: {
        api: roleApi.all,
        transformData: (res) => res.data.map((item: any) => ({
            label: item.name,
            value: item.id
        }))
    },
    deptList: {
        api: departmentApi.all
    }
})
</script>

<template>
    <el-select v-model="form.role_id">
        <el-option
            v-for="item in optionsData.roleList"
            :key="item.value"
            :label="item.label"
            :value="item.value"
        />
    </el-select>
</template>
```

## useWatchRoute -- 路由变化监听

监听路由变化并在变化时立即回调，`immediate: true` 确保首次进入页面也会触发。

### 使用示例

```ts
import { useWatchRoute } from '@/hooks/useWatchRoute'

useWatchRoute((route) => {
    console.log('当前路由：', route.path)
    // 根据路由参数加载数据等
})
```

## useMultipleTabs -- 多标签页管理

封装多标签页的增、删、切换逻辑，与 `tabsStore` 和路由协同。

### 返回值

| 字段 | 类型 | 说明 |
|------|------|------|
| `tabsLists` | `computed` | 当前所有标签列表 |
| `currentTab` | `computed` | 当前激活标签的 fullPath |
| `addTab` | `Function` | 新增标签 |
| `removeTab` | `Function` | 关闭指定标签 |
| `removeOtherTab` | `Function` | 关闭其他标签 |
| `removeAllTab` | `Function` | 关闭全部标签 |

### 使用示例

```ts
import useMultipleTabs from '@/hooks/useMultipleTabs'

const { tabsLists, currentTab, removeTab, removeOtherTab } = useMultipleTabs()
```
