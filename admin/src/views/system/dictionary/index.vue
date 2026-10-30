<template>
    <div class="dictionary-container">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ $t('dictionary.title') }}</h1>
                <div class="page-desc">{{ $t('proPage.system_dictionary_index') }}</div>
            </div>
            <div class="page-actions">
                <el-button
                    v-has-perm="'system.dictionary.create'"
                    type="primary"
                    @click="handleAddDict"
                >
                    <i class="i-svg:plus" />
                    {{ $t('dictionary.addDict') }}
                </el-button>
            </div>
        </div>
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('dictionary.dictName')">
                    <el-input
                        v-model="searchForm.keyword"
                        :placeholder="$t('dictionary.searchPlaceholder')"
                        clearable
                        style="width: 200px"
                    />
                </el-form-item>
                <el-form-item :label="$t('common.status')">
                    <el-select
                        v-model="searchForm.status"
                        :placeholder="$t('dictionary.statusPlaceholder')"
                        clearable
                        style="width: 120px"
                    >
                        <el-option :label="$t('common.enable')" :value="1" />
                        <el-option :label="$t('common.disable')" :value="0" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="handleSearch">
                        <i class="i-svg:search" />
                        {{ $t('common.search') }}
                    </el-button>
                    <el-button @click="resetSearch">
                        <i class="i-svg:refresh-cw" />
                        {{ $t('common.reset') }}
                    </el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 表格区域 -->
        <ProTable
            :title="$t('dictionary.title')"
            storage-key="system-dictionary-list"
            :columns="[
                {
                    key: 'id',
                    label: $t('proPage.table_system_dictionary_index_vue_id'),
                    width: 80,
                    required: true
                },
                {
                    key: 'name',
                    label: $t('proPage.table_system_dictionary_index_vue_name'),
                    minWidth: 140
                },
                {
                    key: 'code',
                    label: $t('proPage.table_system_dictionary_index_vue_code'),
                    minWidth: 160
                },
                {
                    key: 'description',
                    label: $t('proPage.table_system_dictionary_index_vue_description'),
                    minWidth: 180,
                    showOverflowTooltip: true
                },
                {
                    key: 'sort',
                    label: $t('proPage.table_system_dictionary_index_vue_sort'),
                    width: 100,
                    align: 'center'
                },
                {
                    key: 'status',
                    label: $t('proPage.table_system_dictionary_index_vue_status'),
                    width: 100,
                    align: 'center'
                },
                {
                    key: 'created_at',
                    label: $t('proPage.table_system_dictionary_index_vue_created_at'),
                    width: 200
                },
                {
                    key: 'action',
                    label: $t('proPage.table_system_dictionary_index_vue_action'),
                    width: 220,
                    fixed: 'right',
                    required: true
                }
            ]"
            :data="list"
            :loading="loading"
            :pagination="pagination"
            ><template #status="{ row }">
                <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">
                    {{ row.status === 1 ? $t('common.enable') : $t('common.disable') }}
                </el-tag>
            </template>
            <template #action="{ row }">
                <el-button type="primary" size="small" text @click="handleOpenItems(row)">
                    {{ $t('dictionary.items') }}
                </el-button>
                <el-button
                    v-has-perm="'system.dictionary.update'"
                    type="primary"
                    size="small"
                    text
                    @click="handleEditDict(row)"
                >
                    {{ $t('common.edit') }}
                </el-button>
                <el-button
                    v-has-perm="'system.dictionary.delete'"
                    type="danger"
                    size="small"
                    text
                    @click="handleDelete(row.id, row.name)"
                >
                    {{ $t('common.delete') }}
                </el-button>
            </template>
            <template #pagination
                ><el-pagination
                    small
                    v-model:current-page="pagination.page"
                    v-model:page-size="pagination.limit"
                    :total="pagination.total"
                    :page-sizes="[10, 20, 50, 100]"
                    layout="prev, pager, next, sizes"
                    class="pro-pagination"
                    @size-change="handleSizeChange"
                    @current-change="handlePageChange" /></template
        ></ProTable>

        <!-- 字典项弹窗 -->
        <el-dialog
            v-model="itemDialogVisible"
            :title="t('dictionary.itemsTitle', { name: currentDict?.name || '' })"
            width="750px"
            destroy-on-close
        >
            <div class="flex justify-end mb-3">
                <el-button
                    v-has-perm="'system.dictionary.create'"
                    type="primary"
                    size="small"
                    @click="handleAddItem"
                >
                    <i class="i-svg:plus" />
                    {{ $t('dictionary.addItem') }}
                </el-button>
            </div>
            <el-table v-loading="itemLoading" :data="itemList" size="small">
                <el-table-column prop="label" :label="$t('dictionary.label')" min-width="100" />
                <el-table-column prop="value" :label="$t('dictionary.value')" min-width="80" />
                <el-table-column
                    prop="tag_type"
                    :label="$t('dictionary.tagType')"
                    width="100"
                    align="center"
                >
                    <template #default="{ row }">
                        <el-tag v-if="row.tag_type" :type="row.tag_type" size="small">{{
                            row.tag_type
                        }}</el-tag>
                        <span v-else class="text-gray-400">-</span>
                    </template>
                </el-table-column>
                <el-table-column prop="sort" :label="$t('common.sort')" width="60" align="center" />
                <el-table-column
                    prop="status"
                    :label="$t('common.status')"
                    width="80"
                    align="center"
                >
                    <template #default="{ row }">
                        <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">
                            {{ row.status === 1 ? $t('common.enable') : $t('common.disable') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column
                    prop="description"
                    :label="$t('dictionary.description')"
                    min-width="120"
                    show-overflow-tooltip
                />
                <el-table-column :label="$t('common.operation')" width="120" align="center">
                    <template #default="{ row }">
                        <el-button
                            v-has-perm="'system.dictionary.update'"
                            link
                            type="primary"
                            size="small"
                            @click="handleEditItem(row)"
                            >{{ $t('common.edit') }}</el-button
                        >
                        <el-button
                            v-has-perm="'system.dictionary.delete'"
                            link
                            type="danger"
                            size="small"
                            @click="handleDeleteItem(row)"
                            >{{ $t('common.delete') }}</el-button
                        >
                    </template>
                </el-table-column>
            </el-table>
        </el-dialog>

        <!-- 字典表单弹窗 -->
        <DictForm v-model="showDictForm" :form-data="dictFormData" @success="getList" />

        <!-- 字典项表单弹窗 -->
        <DictItemForm
            v-model="showItemForm"
            :form-data="itemFormData"
            :dictionary-id="currentDict?.id || 0"
            @success="fetchItemList"
        />
    </div>
</template>

<script setup lang="ts">

import { ElMessage, ElMessageBox } from 'element-plus'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { dictionaryApi } from '@/api/dictionary'
import { useListPage } from '@/hooks/useListPage'

import DictForm from './components/DictForm.vue'
import DictItemForm from './components/DictItemForm.vue'

const { t } = useI18n()

// ========== 字典列表 ==========
const {
    list,
    loading,
    pagination,
    searchForm,
    getList,
    handleSearch,
    resetSearch,
    handleSizeChange,
    handlePageChange,
    handleDelete
} = useListPage<any, { keyword: string; status?: number }>({
    fetchFn: (params) => dictionaryApi.getList(params),
    deleteFn: (id) => dictionaryApi.delete(id),
    defaultSearchForm: { keyword: '', status: undefined }
})

// 字典表单
const showDictForm = ref(false)
const dictFormData = ref<any>({})

const handleAddDict = () => {
    dictFormData.value = {}
    showDictForm.value = true
}

const handleEditDict = (row: any) => {
    dictFormData.value = { ...row }
    showDictForm.value = true
}

// ========== 字典项 ==========
const itemDialogVisible = ref(false)
const currentDict = ref<any>(null)
const itemLoading = ref(false)
const itemList = ref<any[]>([])

const handleOpenItems = (row: any) => {
    currentDict.value = row
    itemDialogVisible.value = true
    fetchItemList()
}

const fetchItemList = async () => {
    if (!currentDict.value) return
    itemLoading.value = true
    try {
        const res = await dictionaryApi.getItems(currentDict.value.id)
        const data = res.data
        itemList.value = Array.isArray(data) ? data : data?.list || []
    } catch (error) {
        console.error('获取字典项失败:', error)
    } finally {
        itemLoading.value = false
    }
}

// 字典项表单
const showItemForm = ref(false)
const itemFormData = ref<any>({})

const handleAddItem = () => {
    itemFormData.value = {}
    showItemForm.value = true
}

const handleEditItem = (row: any) => {
    itemFormData.value = { ...row }
    showItemForm.value = true
}

const handleDeleteItem = async (row: any) => {
    await ElMessageBox.confirm(
        t('message.deleteConfirmName', { name: row.label }),
        t('common.tip'),
        { type: 'warning' }
    )
    await dictionaryApi.deleteItem(row.id)
    ElMessage.success(t('message.deleteSuccess'))
    fetchItemList()
}
</script>
