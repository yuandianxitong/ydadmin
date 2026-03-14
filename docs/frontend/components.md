# 公共组件

## 概述

公共组件位于 `src/components/` 目录，通过 `unplugin-vue-components` 自动注册，无需手动 import 即可在模板中使用。

## SearchForm -- 可折叠搜索表单

内联搜索表单，当搜索条件超过指定数量时自动折叠，点击"展开"/"收起"切换显示。

### Props

| 属性 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `modelValue` | `Record<string, any>` | -- | 搜索表单数据（v-model） |
| `collapsible` | `boolean` | `true` | 是否支持折叠 |
| `showCount` | `number` | `3` | 折叠时显示的表单项数量 |

### 事件

| 事件名 | 说明 |
|--------|------|
| `search` | 点击搜索按钮 |
| `reset` | 点击重置按钮 |

### 示例

```vue
<SearchForm v-model="queryParams" @search="getLists" @reset="resetParams">
    <el-form-item label="用户名">
        <el-input v-model="queryParams.username" />
    </el-form-item>
    <el-form-item label="状态">
        <el-select v-model="queryParams.status">
            <el-option label="启用" :value="1" />
            <el-option label="禁用" :value="0" />
        </el-select>
    </el-form-item>
</SearchForm>
```

## TableColumnSetting -- 表格列显隐切换

弹出面板，允许用户勾选/取消表格列的显示，支持全选和重置。

### Props

| 属性 | 类型 | 说明 |
|------|------|------|
| `columns` | `ColumnConfig[]` | 列配置数组，包含 `prop` 和 `label` |

### 示例

```vue
<div class="toolbar">
    <TableColumnSetting :columns="columns" @change="handleColumnChange" />
</div>
```

## ImportData -- 数据导入

支持 xlsx/xls/csv 格式的数据导入组件，内置模板下载、拖拽上传和导入结果展示。

### Props

| 属性 | 类型 | 说明 |
|------|------|------|
| `templateUrl` | `string` | 下载模板的 URL |
| `importApi` | `Function` | 执行导入的 API 函数 |

### 示例

```vue
<ImportData
    template-url="/adminapi/admin/import-template"
    :import-api="adminApi.import"
/>
```

## Region -- 地区级联选择器

基于 `el-cascader` 封装的地区选择器，支持二级（省/市）和三级（省/市/区）联动。

### Props

| 属性 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `modelValue` | `(string \| number)[]` | `[]` | 选中的地区编码数组 |
| `level` | `2 \| 3` | `3` | 级联层级 |
| `placeholder` | `string` | `'请选择地区'` | 占位文字 |
| `disabled` | `boolean` | `false` | 是否禁用 |

### 示例

```vue
<Region v-model="formData.region" :level="3" />
```

## DragSort -- 拖拽排序

基于原生 HTML5 Drag API 的拖拽排序组件，支持自定义列表项渲染。

### Props

| 属性 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `modelValue` | `any[]` | -- | 列表数据（v-model） |
| `rowKey` | `string` | `'id'` | 列表项唯一键 |
| `showHandle` | `boolean` | `true` | 是否显示拖拽手柄图标 |

### 示例

```vue
<DragSort v-model="list" @change="handleSortChange">
    <template #default="{ item }">
        <span>{{ item.name }}</span>
    </template>
</DragSort>
```

## Watermark -- 页面水印

在包裹区域上覆盖 Canvas 生成的文字水印层，常用于后台管理页面的信息安全保护。

### Props

| 属性 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `text` | `string` | `''` | 水印文字 |
| `fontSize` | `number` | `16` | 字体大小 |
| `color` | `string` | `'rgba(0,0,0,0.08)'` | 水印颜色 |
| `rotate` | `number` | `-22` | 旋转角度 |
| `gap` | `number` | `100` | 水印间距 |

### 示例

```vue
<Watermark :text="userStore.getAdminName">
    <router-view />
</Watermark>
```

## DetailDesc -- 详情描述列表

网格布局的详情展示组件，适用于数据详情页。

### Props

| 属性 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `title` | `string` | -- | 标题 |
| `column` | `number` | `3` | 每行列数 |
| `border` | `boolean` | `true` | 是否显示边框 |

### 示例

```vue
<DetailDesc title="订单信息" :column="2">
    <DetailDescItem label="订单号">{{ order.order_no }}</DetailDescItem>
    <DetailDescItem label="创建时间">{{ order.created_at }}</DetailDescItem>
    <DetailDescItem label="状态"><StatusTag :status="order.status" /></DetailDescItem>
</DetailDesc>
```

## StatusTag -- 状态标签

基于 `el-tag` 封装的状态展示组件，内置默认状态映射，支持自定义。

### Props

| 属性 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `status` | `number \| string` | -- | 状态值 |
| `map` | `Record<string \| number, StatusConfig>` | 默认映射 | 自定义状态映射 |
| `effect` | `'dark' \| 'light' \| 'plain'` | `'light'` | 标签样式 |
| `size` | `string` | `'default'` | 标签尺寸 |

### 默认状态映射

| 值 | 标签文字 | 标签类型 |
|----|----------|----------|
| `0` | 禁用 | `danger` |
| `1` | 启用 | `success` |
| `2` | 待审核 | `warning` |
| `3` | 已驳回 | `info` |

### 示例

```vue
<!-- 使用默认映射 -->
<StatusTag :status="row.status" />

<!-- 自定义映射 -->
<StatusTag :status="row.pay_status" :map="{
    0: { label: '未支付', type: 'info' },
    1: { label: '已支付', type: 'success' },
    2: { label: '已退款', type: 'warning' }
}" />
```
