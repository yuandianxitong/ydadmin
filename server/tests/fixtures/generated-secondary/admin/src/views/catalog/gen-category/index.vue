<!-- 由代码生成器生成，可按需修改。 -->
<template>
    <div class="gen-category-container">
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item label="名称">
                    <el-input
                        v-model="searchForm.name"
                        placeholder="请输入名称"
                        clearable
                        style="width: 200px"
                    />
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
                <div class="table-title">生成器夹具表二（无状态列/图片列/创建人列）</div>
                <div class="table-actions">
                    <el-button v-has-perm="['catalog.gen_category.create']" type="primary" @click="handleAdd">
                        <el-icon><Plus /></el-icon>
                        {{ $t('common.add') }}
                    </el-button>
                    <el-button
                        v-has-perm="['catalog.gen_category.delete']"
                        type="danger"
                        :disabled="!multipleSelection.length"
                        @click="handleBatchDelete(multipleSelection.map((item) => item.id))"
                    >
                        <el-icon><Delete /></el-icon>
                        {{ $t('common.batchDelete') }}
                    </el-button>
                </div>
            </div>

            <el-table v-loading="loading" :data="list" @selection-change="handleSelectionChange">
                <el-table-column type="selection" width="55" />
                <el-table-column label="id" prop="id" width="80" />
                <el-table-column label="名称" prop="name" show-overflow-tooltip />
                <el-table-column label="是否精选" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.is_featured ? 'success' : 'info'" size="small">
                            {{ $t(row.is_featured ? 'common.yes' : 'common.no') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="扩展配置" prop="settings" />
                <el-table-column label="联系邮箱" prop="contact_email" show-overflow-tooltip />
                <el-table-column label="排序" prop="sort" />
                <el-table-column label="created_at" prop="created_at" />
                <el-table-column label="updated_at" prop="updated_at" />
                <el-table-column label="操作" width="150" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            v-has-perm="['catalog.gen_category.update']"
                            type="primary"
                            size="small"
                            text
                            @click="handleEdit(row)"
                        >
                            {{ $t('common.edit') }}
                        </el-button>
                        <el-button
                            v-has-perm="['catalog.gen_category.delete']"
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

        <GenCategoryForm v-model="formVisible" :form-data="formData" @success="getList" />
    </div>
</template>

<script setup lang="ts" name="GenCategoryList">
import { Delete, Plus, Refresh, Search } from '@element-plus/icons-vue'
import { ref } from 'vue'

import { genCategoryApi } from '@/api/gen-category'
import type { GenCategoryInfo, GenCategoryQuery } from '@/api/gen-category'
import { useListPage } from '@/hooks/useListPage'

import GenCategoryForm from './components/GenCategoryForm.vue'

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
    handleDelete,
    handleBatchDelete
} = useListPage<GenCategoryInfo, GenCategoryQuery>({
    fetchFn: (params) => genCategoryApi.getList(params),
    deleteFn: (id) => genCategoryApi.delete(id),
    batchDeleteFn: (ids) => genCategoryApi.batchDelete(ids),
    defaultSearchForm: {
        name: undefined
    }
})

const multipleSelection = ref<GenCategoryInfo[]>([])
const formVisible = ref(false)
const formData = ref<Partial<GenCategoryInfo>>({})

const handleSelectionChange = (selection: GenCategoryInfo[]) => {
    multipleSelection.value = selection
}

const handleAdd = () => {
    formData.value = {}
    formVisible.value = true
}

const handleEdit = (row: GenCategoryInfo) => {
    formData.value = { ...row }
    formVisible.value = true
}
</script>
