<template>
    <div class="article-container">
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('articleMgmt.articleTitle')">
                    <el-input
                        v-model="searchForm.keyword"
                        :placeholder="$t('articleMgmt.titlePlaceholder')"
                        clearable
                        style="width: 200px"
                    />
                </el-form-item>
                <el-form-item :label="$t('articleMgmt.category')">
                    <el-select
                        v-model="searchForm.category_id"
                        :placeholder="$t('articleMgmt.categoryPlaceholder')"
                        clearable
                        style="width: 160px"
                    >
                        <el-option
                            v-for="item in categoryOptions"
                            :key="item.id"
                            :label="item.name"
                            :value="item.id"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('common.status')">
                    <el-select
                        v-model="searchForm.status"
                        :placeholder="$t('common.selectPlaceholder')"
                        clearable
                        style="width: 120px"
                    >
                        <el-option :label="$t('articleMgmt.published')" :value="1" />
                        <el-option :label="$t('articleMgmt.draft')" :value="0" />
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
                <div class="table-title">{{ $t('articleMgmt.title') }}</div>
                <div class="table-actions">
                    <el-button v-has-perm="['article.create']" type="primary" @click="handleAdd">
                        <el-icon><Plus /></el-icon>
                        {{ $t('common.add') }}
                    </el-button>
                </div>
            </div>

            <el-table v-loading="loading" :data="list">
                <el-table-column
                    :label="$t('articleMgmt.articleTitle')"
                    prop="title"
                    min-width="250"
                    show-overflow-tooltip
                />

                <el-table-column :label="$t('articleMgmt.cover')" width="90">
                    <template #default="{ row }">
                        <el-image
                            v-if="row.cover"
                            :src="appStore.getImageUrl(row.cover)"
                            style="width: 60px; height: 60px"
                            fit="cover"
                            :preview-src-list="[appStore.getImageUrl(row.cover)]"
                            preview-teleported
                        />
                        <span v-else>-</span>
                    </template>
                </el-table-column>

                <el-table-column
                    :label="$t('articleMgmt.category')"
                    prop="category_name"
                    width="120"
                />

                <el-table-column :label="$t('articleMgmt.tags')" width="180">
                    <template #default="{ row }">
                        <template v-if="row.tags && row.tags.length">
                            <el-tag
                                v-for="tag in row.tags"
                                :key="tag"
                                size="small"
                                class="tag-item"
                            >
                                {{ tag }}
                            </el-tag>
                        </template>
                        <span v-else>-</span>
                    </template>
                </el-table-column>

                <el-table-column
                    :label="$t('articleMgmt.viewCount')"
                    prop="view_count"
                    width="90"
                />

                <el-table-column :label="$t('common.status')" prop="status" width="100">
                    <template #default="{ row }">
                        <el-switch
                            v-model="row.status"
                            :active-value="1"
                            :inactive-value="0"
                            :disabled="!userStore.hasPermission('article.status')"
                            @change="handleStatusChange(row)"
                        />
                    </template>
                </el-table-column>

                <el-table-column
                    :label="$t('articleMgmt.publishAt')"
                    prop="publish_at"
                    width="160"
                />

                <el-table-column :label="$t('common.operation')" width="150" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            v-has-perm="['article.update']"
                            type="primary"
                            size="small"
                            text
                            @click="handleEdit(row)"
                        >
                            {{ $t('common.edit') }}
                        </el-button>
                        <el-button
                            v-has-perm="['article.delete']"
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

            <!-- 分页 -->
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

        <!-- 表单抽屉 -->
        <ArticleForm
            v-model="formVisible"
            :form-data="formData"
            :category-options="categoryOptions"
            @success="getList"
        />
    </div>
</template>

<script setup lang="ts" name="ArticleList">
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { articleApi } from '@/api/article'
import { articleCategoryApi } from '@/api/article-category'
import { useListPage } from '@/hooks/useListPage'
import { useAppStore, useUserStore } from '@/store'

import ArticleForm from './components/ArticleForm.vue'

const { t } = useI18n()
const userStore = useUserStore()
const appStore = useAppStore()

// 使用统一的列表页 composable
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
    handleStatusChange
} = useListPage<any, { keyword: string; category_id?: number; status?: number }>({
    fetchFn: (params) => articleApi.getList(params),
    deleteFn: (id) => articleApi.delete(id),
    updateStatusFn: (id, status) => articleApi.updateStatus(id, status),
    defaultSearchForm: { keyword: '', category_id: undefined, status: undefined }
})

// 分类选项
const categoryOptions = ref<any[]>([])

// 弹窗相关
const formVisible = ref(false)
const formData = ref<Record<string, any>>({})

// 获取分类选项
const getCategoryOptions = async () => {
    try {
        const res = await articleCategoryApi.getOptions()
        categoryOptions.value = res.data
    } catch {
        // silent
    }
}

// 新增
const handleAdd = () => {
    const now = new Date()
    const pad = (n: number) => String(n).padStart(2, '0')
    const defaultTime = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`
    formData.value = { status: 0, tags: [], publish_at: defaultTime }
    formVisible.value = true
}

// 编辑
const handleEdit = async (row: any) => {
    try {
        const res = await articleApi.getDetail(row.id)
        formData.value = { ...res.data }
        formVisible.value = true
    } catch {
        ElMessage.error(t('message.fetchFailed'))
    }
}

onMounted(() => {
    getCategoryOptions()
})
</script>

<style lang="scss" scoped>
.article-container {
    .table-card {
        .tag-item {
            margin-right: 4px;
            margin-bottom: 2px;
        }
    }
}
</style>
