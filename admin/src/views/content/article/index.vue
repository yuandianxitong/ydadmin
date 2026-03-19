<template>
    <div class="article-container">
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item label="文章标题">
                    <el-input
                        v-model="searchForm.keyword"
                        placeholder="请输入文章标题"
                        clearable
                        style="width: 200px"
                    />
                </el-form-item>
                <el-form-item label="文章分类">
                    <el-select
                        v-model="searchForm.category_id"
                        placeholder="请选择分类"
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
                        <el-option label="已发布" :value="1" />
                        <el-option label="草稿" :value="0" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="getList">
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
                <div class="table-title">文章列表</div>
                <div class="table-actions">
                    <el-button
                        v-has-perm="['article.create']"
                        type="primary"
                        @click="handleAdd"
                    >
                        <el-icon><Plus /></el-icon>
                        {{ $t('common.add') }}
                    </el-button>
                </div>
            </div>

            <el-table v-loading="loading" :data="list">
                <el-table-column label="文章标题" prop="title" min-width="250" show-overflow-tooltip />

                <el-table-column label="封面" width="90">
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

                <el-table-column label="分类" prop="category_name" width="120" />

                <el-table-column label="标签" width="180">
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

                <el-table-column label="浏览量" prop="view_count" width="90" />

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

                <el-table-column label="发布时间" prop="publish_at" width="160" />

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
                            @click="handleDelete(row)"
                        >
                            {{ $t('common.delete') }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>

            <!-- 分页 -->
            <el-pagination
                v-model:current-page="pagination.current_page"
                v-model:page-size="pagination.per_page"
                :total="pagination.total"
                :page-sizes="[10, 20, 50, 100]"
                layout="total, sizes, prev, pager, next, jumper"
                class="pagination"
                @size-change="getList"
                @current-change="getList"
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
import { ElMessage, ElMessageBox } from 'element-plus'
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { articleApi } from '@/api/article'
import { articleCategoryApi } from '@/api/article-category'
import { useAppStore, useUserStore } from '@/store'

import ArticleForm from './components/ArticleForm.vue'

const { t } = useI18n()
const userStore = useUserStore()
const appStore = useAppStore()

// 搜索表单
const searchForm = reactive({
    keyword: '',
    category_id: undefined as number | undefined,
    status: undefined as number | undefined
})

// 列表数据
const list = ref<any[]>([])
const loading = ref(false)
const pagination = reactive({ current_page: 1, per_page: 20, total: 0, last_page: 1 })

// 分类选项
const categoryOptions = ref<any[]>([])

// 弹窗相关
const formVisible = ref(false)
const formData = ref<Record<string, any>>({})

// 获取列表
const getList = async () => {
    try {
        loading.value = true
        const params: Record<string, any> = {
            page: pagination.current_page,
            limit: pagination.per_page
        }
        if (searchForm.keyword?.trim()) params.keyword = searchForm.keyword.trim()
        if (searchForm.category_id !== undefined) params.category_id = searchForm.category_id
        if (searchForm.status !== undefined) params.status = searchForm.status

        const res = await articleApi.getList(params)
        list.value = res.data.list
        Object.assign(pagination, res.data.pagination)
    } catch {
        ElMessage.error(t('message.fetchFailed'))
    } finally {
        loading.value = false
    }
}

// 获取分类选项
const getCategoryOptions = async () => {
    try {
        const res = await articleCategoryApi.getOptions()
        categoryOptions.value = res.data
    } catch {
        // silent
    }
}

// 重置搜索
const resetSearch = () => {
    Object.assign(searchForm, { keyword: '', category_id: undefined, status: undefined })
    pagination.current_page = 1
    getList()
}

// 状态变更
const handleStatusChange = async (row: any) => {
    try {
        await articleApi.updateStatus(row.id, row.status)
        ElMessage.success(t('message.statusUpdateSuccess'))
    } catch {
        row.status = row.status === 1 ? 0 : 1
        ElMessage.error(t('common.error'))
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

// 删除
const handleDelete = async (row: any) => {
    try {
        await ElMessageBox.confirm(t('message.deleteConfirmName', { name: row.title }), t('message.confirmDelete'), {
            confirmButtonText: t('common.confirm'),
            cancelButtonText: t('common.cancel'),
            type: 'warning'
        })
        await articleApi.delete(row.id)
        ElMessage.success(t('message.deleteSuccess'))
        getList()
    } catch (error) {
        if (error !== 'cancel') ElMessage.error(t('common.error'))
    }
}

onMounted(() => {
    getList()
    getCategoryOptions()
})
</script>

<style lang="scss" scoped>
.article-container {
    .search-card {
        margin-bottom: 16px;

        .search-form {
            margin: 0;
        }
    }

    .table-card {
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;

            .table-title {
                font-size: 16px;
                font-weight: 600;
                color: var(--el-text-color-primary);
            }

            .table-actions {
                display: flex;
                gap: 8px;
            }
        }

        .tag-item {
            margin-right: 4px;
            margin-bottom: 2px;
        }

        .pagination {
            margin-top: 16px;
            display: flex;
            justify-content: flex-end;
        }
    }
}
</style>
