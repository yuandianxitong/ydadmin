<template>
    <div class="notification-container">
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('notificationMgmt.notificationTitle')">
                    <el-input
                        v-model="searchForm.keyword"
                        :placeholder="$t('notificationMgmt.titlePlaceholder')"
                        clearable
                        style="width: 200px"
                    />
                </el-form-item>
                <el-form-item :label="$t('notificationMgmt.notificationType')">
                    <el-select
                        v-model="searchForm.type"
                        :placeholder="$t('notificationMgmt.typePlaceholder')"
                        clearable
                        style="width: 120px"
                    >
                        <el-option :label="$t('notificationMgmt.typeOptions.system')" :value="1" />
                        <el-option :label="$t('notificationMgmt.typeOptions.todo')" :value="2" />
                        <el-option
                            :label="$t('notificationMgmt.typeOptions.business')"
                            :value="3"
                        />
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
                <div class="table-title">{{ $t('notificationMgmt.title') }}</div>
                <div class="table-actions">
                    <el-button
                        v-has-perm="['system.notification.create']"
                        type="primary"
                        @click="handleAdd"
                    >
                        <el-icon><Plus /></el-icon>
                        {{ $t('notificationMgmt.addNotification') }}
                    </el-button>
                </div>
            </div>

            <el-table v-loading="loading" :data="list">
                <el-table-column
                    :label="$t('notificationMgmt.notificationTitle')"
                    prop="title"
                    min-width="250"
                    show-overflow-tooltip
                />

                <el-table-column
                    :label="$t('notificationMgmt.notificationType')"
                    prop="type"
                    width="110"
                >
                    <template #default="{ row }">
                        <el-tag :type="typeTagMap[row.type] || 'info'" size="small">
                            {{ typeTextMap[row.type] || row.type }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column
                    :label="$t('notificationMgmt.targetScope')"
                    prop="target_type"
                    width="100"
                >
                    <template #default="{ row }">
                        <el-tag
                            :type="row.target_type === 1 ? 'primary' : 'warning'"
                            size="small"
                            effect="plain"
                        >
                            {{
                                row.target_type === 1
                                    ? $t('notificationMgmt.scopeOptions.all')
                                    : $t('notificationMgmt.scopeOptions.specified')
                            }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column
                    :label="$t('notificationMgmt.readCount')"
                    prop="reads_count"
                    width="100"
                />

                <el-table-column :label="$t('common.status')" prop="status" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">
                            {{
                                row.status === 1
                                    ? $t('notificationMgmt.published')
                                    : $t('notificationMgmt.draft')
                            }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('common.createdAt')" prop="created_at" width="160" />

                <el-table-column :label="$t('common.operation')" width="150" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            v-has-perm="['system.notification.update']"
                            type="primary"
                            size="small"
                            text
                            @click="handleEdit(row)"
                        >
                            {{ $t('common.edit') }}
                        </el-button>
                        <el-button
                            v-has-perm="['system.notification.delete']"
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

        <!-- 表单弹窗 -->
        <NotificationForm v-model="formVisible" :form-data="formData" @success="getList" />
    </div>
</template>

<script setup lang="ts" name="NotificationList">
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { notificationApi } from '@/api/notification'
import { useListPage } from '@/hooks/useListPage'

import NotificationForm from './components/NotificationForm.vue'

const { t } = useI18n()

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
    handleDelete
} = useListPage<any, { keyword: string; type?: number }>({
    fetchFn: (params) => {
        const query: Record<string, any> = {
            page: params.page,
            limit: params.limit
        }
        if (params.keyword?.trim()) query.keyword = params.keyword.trim()
        if (params.type !== undefined) query.type = params.type
        return notificationApi.getList(query)
    },
    deleteFn: (id) => notificationApi.delete(id),
    defaultSearchForm: { keyword: '', type: undefined }
})

const formVisible = ref(false)
const formData = ref<Record<string, any>>({})

const typeTextMap = computed(
    () =>
        ({
            1: t('notificationMgmt.typeOptions.system'),
            2: t('notificationMgmt.typeOptions.todo'),
            3: t('notificationMgmt.typeOptions.business')
        }) as Record<number, string>
)
const typeTagMap: Record<number, 'primary' | 'success' | 'warning' | 'info' | 'danger'> = {
    1: 'primary',
    2: 'warning',
    3: 'success'
}

const handleAdd = () => {
    formData.value = { type: 1, target_type: 1, status: 1 }
    formVisible.value = true
}

const handleEdit = (row: any) => {
    formData.value = { ...row }
    formVisible.value = true
}
</script>
