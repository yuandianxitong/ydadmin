<template>
    <div class="article-category-container">
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('articleCategoryMgmt.categoryName')">
                    <el-input
                        v-model="searchForm.keyword"
                        :placeholder="$t('articleCategoryMgmt.namePlaceholder')"
                        clearable
                        style="width: 200px"
                    />
                </el-form-item>
                <el-form-item :label="$t('common.status')">
                    <el-select
                        v-model="searchForm.status"
                        :placeholder="$t('common.selectPlaceholder')"
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

        <!-- 操作区域 -->
        <el-card class="table-card" shadow="never">
            <div class="table-header">
                <div class="table-title">{{ $t('articleCategoryMgmt.title') }}</div>
                <div class="table-actions">
                    <el-button
                        v-has-perm="['article_category.create']"
                        type="primary"
                        @click="handleAdd"
                    >
                        <el-icon><Plus /></el-icon>
                        {{ $t('common.add') }}
                    </el-button>
                </div>
            </div>

            <!-- 表格 -->
            <el-table
                v-loading="loading"
                :data="list"
                row-key="id"
                :tree-props="{ children: 'children', hasChildren: 'hasChildren' }"
                default-expand-all
            >
                <el-table-column
                    :label="$t('articleCategoryMgmt.categoryName')"
                    prop="name"
                    min-width="200"
                />

                <el-table-column :label="$t('articleCategoryMgmt.icon')" prop="icon" width="80">
                    <template #default="{ row }">
                        <el-icon v-if="row.icon">
                            <component :is="row.icon" />
                        </el-icon>
                        <span v-else>-</span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('common.sort')" prop="sort" width="80" />

                <el-table-column :label="$t('common.status')" prop="status" width="100">
                    <template #default="{ row }">
                        <el-switch
                            v-model="row.status"
                            :active-value="1"
                            :inactive-value="0"
                            :disabled="!userStore.hasPermission('article_category.update')"
                            @change="handleStatusChange(row)"
                        />
                    </template>
                </el-table-column>

                <el-table-column :label="$t('common.createdAt')" prop="created_at" width="160" />

                <el-table-column :label="$t('common.operation')" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            v-has-perm="['article_category.create']"
                            type="primary"
                            size="small"
                            text
                            @click="handleAddChild(row)"
                        >
                            {{ $t('common.add') }}
                        </el-button>
                        <el-button
                            v-has-perm="['article_category.update']"
                            type="primary"
                            size="small"
                            text
                            @click="handleEdit(row)"
                        >
                            {{ $t('common.edit') }}
                        </el-button>
                        <el-button
                            v-has-perm="['article_category.delete']"
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
        </el-card>

        <!-- 新增/编辑弹窗 -->
        <ArticleCategoryForm
            v-model="formVisible"
            :form-data="formData"
            :parent-options="parentOptions"
            @success="getList"
        />
    </div>
</template>

<script setup lang="ts" name="ArticleCategoryList">
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { articleCategoryApi } from '@/api/article-category'
import { useListPage } from '@/hooks/useListPage'
import { useUserStore } from '@/store'

import ArticleCategoryForm from './components/ArticleCategoryForm.vue'

const { t } = useI18n()
const userStore = useUserStore()

// 树形结构 API 不分页，返回结构为 { code, data: Tree[] }
// 这里通过包装 fetchFn，把树形数据塞进 useListPage 期望的 { list, pagination } 形态
const {
    list,
    loading,
    searchForm,
    getList,
    handleSearch,
    resetSearch,
    handleDelete,
    handleStatusChange
} = useListPage<any, { keyword: string; status?: number }>({
    fetchFn: async (params) => {
        const res = await articleCategoryApi.getList(params)
        return {
            ...res,
            data: {
                list: (res.data || []) as any[],
                pagination: { total: 0, page: 1, limit: 0, total_pages: 0 }
            }
        } as any
    },
    deleteFn: (id) => articleCategoryApi.delete(id),
    updateStatusFn: (id, status) => articleCategoryApi.updateStatus(id, status),
    defaultSearchForm: { keyword: '', status: undefined }
})

// 弹窗相关
const formVisible = ref(false)
const formData = ref<Record<string, any>>({})
const parentOptions = ref<any[]>([])

// 获取父级选项
const getParentOptions = async (excludeId?: number) => {
    try {
        const res = await articleCategoryApi.getOptions(excludeId)
        parentOptions.value = res.data
    } catch {
        ElMessage.error(t('message.fetchFailed'))
    }
}

// 新增
const handleAdd = () => {
    formData.value = { parent_id: 0, status: 1, sort: 0 }
    getParentOptions()
    formVisible.value = true
}

// 新增子级
const handleAddChild = (parent: any) => {
    formData.value = { parent_id: parent.id, status: 1, sort: 0 }
    getParentOptions()
    formVisible.value = true
}

// 编辑
const handleEdit = (row: any) => {
    formData.value = { ...row }
    getParentOptions(row.id)
    formVisible.value = true
}
</script>
