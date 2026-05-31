<template>
    <div class="dictionary-container">
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
                <div class="table-title">{{ $t('dictionary.title') }}</div>
                <div class="table-actions">
                    <el-button
                        v-has-perm="'system.dictionary.create'"
                        type="primary"
                        @click="handleAddDict"
                    >
                        <el-icon><Plus /></el-icon>
                        {{ $t('dictionary.addDict') }}
                    </el-button>
                </div>
            </div>

            <el-table v-loading="loading" :data="list">
                <el-table-column :label="$t('common.id')" prop="id" width="80" />
                <el-table-column :label="$t('dictionary.dictName')" prop="name" min-width="140" />
                <el-table-column :label="$t('dictionary.dictCode')" prop="code" min-width="160" />
                <el-table-column
                    :label="$t('dictionary.description')"
                    prop="description"
                    min-width="180"
                    show-overflow-tooltip
                />
                <el-table-column :label="$t('common.sort')" prop="sort" width="80" align="center" />
                <el-table-column :label="$t('common.status')" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">
                            {{ row.status === 1 ? $t('common.enable') : $t('common.disable') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('common.createdAt')" prop="created_at" width="180" />
                <el-table-column :label="$t('common.operation')" width="220" fixed="right">
                    <template #default="{ row }">
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
                    <el-icon><Plus /></el-icon>
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
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
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
