<!-- 由代码生成器生成，可按需修改。 -->
<template>
    <div class="gen-article-container">
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item label="标题">
                    <el-input
                        v-model="searchForm.title"
                        placeholder="请输入标题"
                        clearable
                        style="width: 200px"
                    />
                </el-form-item>
                <el-form-item label="状态">
                    <el-input
                        v-model="searchForm.status"
                        placeholder="请输入状态"
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
                <div class="table-title">生成器夹具表</div>
                <div class="table-actions">
                    <el-button v-has-perm="['demo.gen_article.create']" type="primary" @click="handleAdd">
                        <el-icon><Plus /></el-icon>
                        {{ $t('common.add') }}
                    </el-button>
                    <el-button
                        v-has-perm="['demo.gen_article.delete']"
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
                <el-table-column label="标题" prop="title" show-overflow-tooltip />
                <el-table-column label="摘要" prop="summary" show-overflow-tooltip />
                <el-table-column label="封面图" width="90">
                    <template #default="{ row }">
                        <el-image
                            v-if="row.cover_image"
                            :src="appStore.getImageUrl(row.cover_image)"
                            style="width: 60px; height: 60px"
                            fit="cover"
                            :preview-src-list="[appStore.getImageUrl(row.cover_image)]"
                            preview-teleported
                        />
                        <span v-else>-</span>
                    </template>
                </el-table-column>
                <el-table-column label="分类" prop="category" />
                <el-table-column label="价格" prop="price" />
                <el-table-column label="浏览量" prop="view_count" />
                <el-table-column label="别名" prop="slug" show-overflow-tooltip />
                <el-table-column label="发布时间" prop="published_at" />
                <el-table-column label="状态" width="100" align="center">
                    <template #default="{ row }">
                        <el-switch
                            v-model="row.status"
                            :active-value="1"
                            :inactive-value="0"
                            :disabled="!userStore.hasPermission('demo.gen_article.status')"
                            @change="handleStatusChange(row)"
                        />
                    </template>
                </el-table-column>
                <el-table-column label="排序" prop="sort" />
                <el-table-column label="created_by" prop="created_by" />
                <el-table-column label="dept_id" prop="dept_id" />
                <el-table-column label="created_at" prop="created_at" />
                <el-table-column label="updated_at" prop="updated_at" />
                <el-table-column label="操作" width="150" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            v-has-perm="['demo.gen_article.update']"
                            type="primary"
                            size="small"
                            text
                            @click="handleEdit(row)"
                        >
                            {{ $t('common.edit') }}
                        </el-button>
                        <el-button
                            v-has-perm="['demo.gen_article.delete']"
                            type="danger"
                            size="small"
                            text
                            @click="handleDelete(row.id, row.title)"
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

        <GenArticleForm v-model="formVisible" :form-data="formData" @success="getList" />
    </div>
</template>

<script setup lang="ts" name="GenArticleList">
import { Delete, Plus, Refresh, Search } from '@element-plus/icons-vue'
import { ref } from 'vue'

import { genArticleApi } from '@/api/gen-article'
import type { GenArticleInfo, GenArticleQuery } from '@/api/gen-article'
import { useListPage } from '@/hooks/useListPage'
import { useAppStore, useUserStore } from '@/store'

import GenArticleForm from './components/GenArticleForm.vue'

const appStore = useAppStore()
const userStore = useUserStore()

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
    handleBatchDelete,
    handleStatusChange
} = useListPage<GenArticleInfo, GenArticleQuery>({
    fetchFn: (params) => genArticleApi.getList(params),
    deleteFn: (id) => genArticleApi.delete(id),
    batchDeleteFn: (ids) => genArticleApi.batchDelete(ids),
    updateStatusFn: (id, status) => genArticleApi.updateStatus(id, status),
    defaultSearchForm: {
        title: undefined,
        status: undefined
    }
})

const multipleSelection = ref<GenArticleInfo[]>([])
const formVisible = ref(false)
const formData = ref<Partial<GenArticleInfo>>({})

const handleSelectionChange = (selection: GenArticleInfo[]) => {
    multipleSelection.value = selection
}

const handleAdd = () => {
    formData.value = {}
    formVisible.value = true
}

const handleEdit = (row: GenArticleInfo) => {
    formData.value = { ...row }
    formVisible.value = true
}
</script>
