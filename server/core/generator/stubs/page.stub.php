<?php
/**
 * 列表页模板 → admin/src/views/{module}/{kebab}/index.vue，基于 useListPage()。
 *
 * @var string $module
 * @var string $model
 * @var string $modelSnake
 * @var string $modelKebab
 * @var string $tableCommentHtml 表说明，已按 HTML 文本节点的落点实体化（见 ModuleBlueprint::vars()）
 * @var list<\core\generator\ColumnDescriptor> $listColumns
 * @var list<\core\generator\ColumnDescriptor> $searchColumns
 * @var bool $hasStatus
 * @var string $primaryKey
 * @var \core\generator\TypeInference $inference
 */

$modelCamel = lcfirst($model);
$permBase = "{$module}.{$modelSnake}.";

$label = static function (\core\generator\ColumnDescriptor $column): string {
    return $column->comment !== '' ? $column->comment : $column->name;
};

$hasImageInList = false;
foreach ($listColumns as $c) {
    if ($c->formType === 'image') {
        $hasImageInList = true;
        break;
    }
}

// 删除确认弹窗里带上的「名字」：优先取可搜索的字符串列，否则退到列表里第一个字符串列，都没有就不带名字。
$nameColumn = null;
foreach ($searchColumns as $c) {
    if ($c->type === 'string') {
        $nameColumn = $c->name;
        break;
    }
}
if ($nameColumn === null) {
    foreach ($listColumns as $c) {
        if ($c->type === 'string') {
            $nameColumn = $c->name;
            break;
        }
    }
}

// ---- 搜索区：queryStrategy() 只分两支：range → 两个日期选择器；其余（like/equal）→ 输入框 ----
$searchItems = [];
foreach ($searchColumns as $column) {
    $colLabel = $label($column);
    if ($inference->queryStrategy($column) === 'range') {
        $searchItems[] = "                <el-form-item label=\"{$colLabel}\">\n"
            . "                    <el-date-picker\n"
            . "                        v-model=\"searchForm.{$column->name}_start\"\n"
            . "                        type=\"date\"\n"
            . "                        placeholder=\"{$colLabel}起始\"\n"
            . "                        value-format=\"YYYY-MM-DD\"\n"
            . "                        clearable\n"
            . "                        style=\"width: 160px\"\n"
            . "                    />\n"
            . "                    <span class=\"range-separator\">{{ " . '$t' . "('common.to') }}</span>\n"
            . "                    <el-date-picker\n"
            . "                        v-model=\"searchForm.{$column->name}_end\"\n"
            . "                        type=\"date\"\n"
            . "                        placeholder=\"{$colLabel}结束\"\n"
            . "                        value-format=\"YYYY-MM-DD\"\n"
            . "                        clearable\n"
            . "                        style=\"width: 160px\"\n"
            . "                    />\n"
            . "                </el-form-item>";
    } else {
        $searchItems[] = "                <el-form-item label=\"{$colLabel}\">\n"
            . "                    <el-input\n"
            . "                        v-model=\"searchForm.{$column->name}\"\n"
            . "                        placeholder=\"请输入{$colLabel}\"\n"
            . "                        clearable\n"
            . "                        style=\"width: 200px\"\n"
            . "                    />\n"
            . "                </el-form-item>";
    }
}
$searchBlock = implode("\n", $searchItems);

// ---- 表格列：switch/image/其余三支，status 列且 $hasStatus 时是实时开关 ----
$columnItems = [];
foreach ($listColumns as $column) {
    $colLabel = $label($column);
    if ($column->name === 'status' && $hasStatus) {
        $columnItems[] = "                <el-table-column label=\"{$colLabel}\" width=\"100\" align=\"center\">\n"
            . "                    <template #default=\"{ row }\">\n"
            . "                        <el-switch\n"
            . "                            v-model=\"row.status\"\n"
            . "                            :active-value=\"1\"\n"
            . "                            :inactive-value=\"0\"\n"
            . "                            :disabled=\"!userStore.hasPermission('{$permBase}status')\"\n"
            . "                            @change=\"handleStatusChange(row)\"\n"
            . "                        />\n"
            . "                    </template>\n"
            . "                </el-table-column>";
    } elseif ($column->formType === 'switch') {
        $columnItems[] = "                <el-table-column label=\"{$colLabel}\" width=\"100\" align=\"center\">\n"
            . "                    <template #default=\"{ row }\">\n"
            . "                        <el-tag :type=\"row.{$column->name} ? 'success' : 'info'\" size=\"small\">\n"
            . "                            {{ " . '$t' . "(row.{$column->name} ? 'common.yes' : 'common.no') }}\n"
            . "                        </el-tag>\n"
            . "                    </template>\n"
            . "                </el-table-column>";
    } elseif ($column->formType === 'image') {
        $columnItems[] = "                <el-table-column label=\"{$colLabel}\" width=\"90\">\n"
            . "                    <template #default=\"{ row }\">\n"
            . "                        <el-image\n"
            . "                            v-if=\"row.{$column->name}\"\n"
            . "                            :src=\"appStore.getImageUrl(row.{$column->name})\"\n"
            . "                            style=\"width: 60px; height: 60px\"\n"
            . "                            fit=\"cover\"\n"
            . "                            :preview-src-list=\"[appStore.getImageUrl(row.{$column->name})]\"\n"
            . "                            preview-teleported\n"
            . "                        />\n"
            . "                        <span v-else>-</span>\n"
            . "                    </template>\n"
            . "                </el-table-column>";
    } elseif ($column->type === 'json') {
        // json 列在行数据里是对象，裸 prop 会渲染成 [object Object]；序列化后显示，超长截断悬停看全文
        $columnItems[] = "                <el-table-column label=\"{$colLabel}\" show-overflow-tooltip>\n"
            . "                    <template #default=\"{ row }\">\n"
            . "                        {{ row.{$column->name} == null ? '' : JSON.stringify(row.{$column->name}) }}\n"
            . "                    </template>\n"
            . "                </el-table-column>";
    } else {
        $attrs = ["label=\"{$colLabel}\"", "prop=\"{$column->name}\""];
        if ($column->name === $primaryKey) {
            $attrs[] = 'width="80"';
        }
        if ($column->type === 'string' || $column->type === 'text') {
            $attrs[] = 'show-overflow-tooltip';
        }
        $columnItems[] = '                <el-table-column ' . implode(' ', $attrs) . ' />';
    }
}
$columnsBlock = implode("\n", $columnItems);

$deleteCall = $nameColumn !== null
    ? "handleDelete(row.{$primaryKey}, row.{$nameColumn})"
    : "handleDelete(row.{$primaryKey})";

// ---- defaultSearchForm ----
$defaultSearchFields = [];
foreach ($searchColumns as $column) {
    if ($inference->queryStrategy($column) === 'range') {
        $defaultSearchFields[] = "        {$column->name}_start: undefined";
        $defaultSearchFields[] = "        {$column->name}_end: undefined";
    } else {
        $defaultSearchFields[] = "        {$column->name}: undefined";
    }
}
$defaultSearchBlock = implode(",\n", $defaultSearchFields);

// ---- imports / composable 组装（按 hasImageInList / hasStatus 增减）----
$storeImports = [];
if ($hasImageInList) {
    $storeImports[] = 'useAppStore';
}
if ($hasStatus) {
    $storeImports[] = 'useUserStore';
}

$storeInit = [];
if ($hasImageInList) {
    $storeInit[] = 'const appStore = useAppStore()';
}
if ($hasStatus) {
    $storeInit[] = 'const userStore = useUserStore()';
}

$listPageKeys = [
    'list', 'loading', 'pagination', 'searchForm', 'getList', 'handleSearch', 'resetSearch',
    'handleSizeChange', 'handlePageChange', 'handleDelete', 'handleBatchDelete',
];
if ($hasStatus) {
    $listPageKeys[] = 'handleStatusChange';
}

$optionLines = [
    "    fetchFn: (params) => {$modelCamel}Api.getList(params)",
    "    deleteFn: (id) => {$modelCamel}Api.delete(id)",
    "    batchDeleteFn: (ids) => {$modelCamel}Api.batchDelete(ids)",
];
if ($hasStatus) {
    $optionLines[] = "    updateStatusFn: (id, status) => {$modelCamel}Api.updateStatus(id, status)";
}
$optionLines[] = "    defaultSearchForm: {\n{$defaultSearchBlock}\n    }";

$template = <<<'VUE'
<!-- 由代码生成器生成，可按需修改。 -->
<template>
    <div class="__MODEL_KEBAB__-container">
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
__SEARCH_BLOCK__
                <el-form-item>
                    <el-button type="primary" @click="handleSearch">
                        <el-icon><Search /></el-icon>
                        {{ $t('common.search') }}
                    </el-button>
                    <el-button @click="resetSearch">
                        <el-icon><Refresh /></el-icon>
                        {{ $t('common.reset') }}
                    </el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 表格区域 -->
        <el-card class="table-card" shadow="never">
            <div class="table-header">
                <div class="table-title">__TABLE_COMMENT__</div>
                <div class="table-actions">
                    <el-button v-has-perm="['__PERM_CREATE__']" type="primary" @click="handleAdd">
                        <el-icon><Plus /></el-icon>
                        {{ $t('common.add') }}
                    </el-button>
                    <el-button
                        v-has-perm="['__PERM_DELETE__']"
                        type="danger"
                        :disabled="!multipleSelection.length"
                        @click="handleBatchDelete(multipleSelection.map((item) => item.__PK__))"
                    >
                        <el-icon><Delete /></el-icon>
                        {{ $t('common.batchDelete') }}
                    </el-button>
                </div>
            </div>

            <el-table v-loading="loading" :data="list" @selection-change="handleSelectionChange">
                <el-table-column type="selection" width="55" />
__COLUMNS_BLOCK__
                <el-table-column label="操作" width="150" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            v-has-perm="['__PERM_UPDATE__']"
                            type="primary"
                            size="small"
                            text
                            @click="handleEdit(row)"
                        >
                            {{ $t('common.edit') }}
                        </el-button>
                        <el-button
                            v-has-perm="['__PERM_DELETE__']"
                            type="danger"
                            size="small"
                            text
                            @click="__DELETE_CALL__"
                        >
                            {{ $t('common.delete') }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>

            <el-pagination
                v-model:current-page="pagination.page"
                v-model:page-size="pagination.limit"
                :total="pagination.total"
                :page-sizes="[10, 20, 50, 100]"
                layout="total, sizes, prev, pager, next, jumper"
                class="pagination"
                @size-change="handleSizeChange"
                @current-change="handlePageChange"
            />
        </el-card>

        <__MODEL__Form v-model="formVisible" :form-data="formData" @success="getList" />
    </div>
</template>

<script setup lang="ts" name="__MODEL__List">
import { Delete, Plus, Refresh, Search } from '@element-plus/icons-vue'
import { ref } from 'vue'

import { __MODEL_CAMEL__Api } from '@/api/__MODEL_KEBAB__'
import type { __MODEL__Info, __MODEL__Query } from '@/api/__MODEL_KEBAB__'
import { useListPage } from '@/hooks/useListPage'
__STORE_IMPORT_LINE__

import __MODEL__Form from './components/__MODEL__Form.vue'

__STORE_INIT_BLOCK__
const {
__LISTPAGE_KEYS__
} = useListPage<__MODEL__Info, __MODEL__Query>({
__OPTION_LINES__
})

const multipleSelection = ref<__MODEL__Info[]>([])
const formVisible = ref(false)
const formData = ref<Partial<__MODEL__Info>>({})

const handleSelectionChange = (selection: __MODEL__Info[]) => {
    multipleSelection.value = selection
}

const handleAdd = () => {
    formData.value = {}
    formVisible.value = true
}

const handleEdit = (row: __MODEL__Info) => {
    formData.value = { ...row }
    formVisible.value = true
}
</script>
VUE;

$storeImportLine = $storeImports === [] ? '' : "import { " . implode(', ', $storeImports) . " } from '@/store'";
$storeInitBlock = $storeInit === [] ? '' : implode("\n", $storeInit) . "\n";
$listPageKeysBlock = implode(",\n", array_map(static fn (string $k): string => "    {$k}", $listPageKeys));
$optionLinesBlock = implode(",\n", $optionLines);

$replacements = [
    '__MODEL_KEBAB__' => $modelKebab,
    // 本模板里 __TABLE_COMMENT__ 只有一处落点：<div class="table-title">…</div>，即 HTML 文本节点。
    // 用 HTML 实体转义而不是 JS 转义——这里危险的是尖括号（能开出新标签），引号无害；
    // 反过来用 tableCommentJs 既挡不住 <img>，还会把撇号原样显示成 \'。
    '__TABLE_COMMENT__' => $tableCommentHtml,
    '__PERM_CREATE__' => $permBase . 'create',
    '__PERM_UPDATE__' => $permBase . 'update',
    '__PERM_DELETE__' => $permBase . 'delete',
    '__PK__' => $primaryKey,
    '__SEARCH_BLOCK__' => $searchBlock,
    '__COLUMNS_BLOCK__' => $columnsBlock,
    '__DELETE_CALL__' => $deleteCall,
    '__MODEL__' => $model,
    '__MODEL_CAMEL__' => $modelCamel,
    '__STORE_IMPORT_LINE__' => $storeImportLine,
    '__STORE_INIT_BLOCK__' => $storeInitBlock,
    '__LISTPAGE_KEYS__' => $listPageKeysBlock,
    '__OPTION_LINES__' => $optionLinesBlock,
];

$__out = strtr($template, $replacements);
// 没有图片列/状态列时对应的 import 行会被替换成空字符串，留下一行空行，一并清理掉。
$__out = preg_replace('/\n{3,}/', "\n\n", $__out);
?>
<?= $__out ?>

