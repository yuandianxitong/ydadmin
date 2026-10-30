<template>
    <div class="notification-container">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ $t('notificationMgmt.title') }}</h1>
                <div class="page-desc">{{ $t('proPage.system_notification_index') }}</div>
            </div>
            <div class="page-actions">
                <el-button
                    v-has-perm="['system.notification.create']"
                    type="primary"
                    @click="handleAdd"
                >
                    <i class="i-svg:plus" />
                    {{ $t('notificationMgmt.addNotification') }}
                </el-button>
            </div>
        </div>
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

        <!-- 操作区域 -->
        <ProTable
            :title="$t('notificationMgmt.title')"
            storage-key="system-notification-list"
            :columns="[
                {
                    key: 'title',
                    label: $t('proPage.table_system_notification_index_vue_title'),
                    minWidth: 250,
                    showOverflowTooltip: true,
                    required: true
                },
                {
                    key: 'type',
                    label: $t('proPage.table_system_notification_index_vue_type'),
                    width: 110
                },
                {
                    key: 'target_type',
                    label: $t('proPage.table_system_notification_index_vue_target_type'),
                    width: 120
                },
                {
                    key: 'reads_count',
                    label: $t('proPage.table_system_notification_index_vue_reads_count'),
                    width: 120
                },
                {
                    key: 'status',
                    label: $t('proPage.table_system_notification_index_vue_status'),
                    width: 100
                },
                {
                    key: 'created_at',
                    label: $t('proPage.table_system_notification_index_vue_created_at'),
                    width: 200
                },
                {
                    key: 'action',
                    label: $t('proPage.table_system_notification_index_vue_action'),
                    width: 150,
                    fixed: 'right',
                    required: true
                }
            ]"
            :data="list"
            :loading="loading"
            :pagination="pagination"
            ><template #type="{ row }">
                <el-tag :type="typeTagMap[row.type] || 'info'" size="small">
                    {{ typeTextMap[row.type] || row.type }}
                </el-tag>
            </template>
            <template #target_type="{ row }">
                <el-tag
                    :type="row.target_type === 1 ? 'primary' : 'warning'"
                    size="small"
                    effect="plain"
                >
                    {{
                        row.target_type === 1
                            ? $t('notificationMgmt.scopeOptions.all')
                            : $t('notificationMgmt.specifiedCount', {
                                  count: row.target_count ?? 0
                              })
                    }}
                </el-tag>
            </template>
            <template #status="{ row }">
                <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">
                    {{
                        row.status === 1
                            ? $t('notificationMgmt.published')
                            : $t('notificationMgmt.draft')
                    }}
                </el-tag>
            </template>
            <template #action="{ row }">
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

        <!-- 表单弹窗 -->
        <NotificationForm v-model="formVisible" :form-data="formData" @success="getList" />
    </div>
</template>

<script setup lang="ts" name="NotificationList">

import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { notificationApi } from '@/api/notification'
import { useListPage } from '@/hooks/useListPage'
import type { NotificationInfo } from '@/types/system'

import NotificationForm from './components/NotificationForm.vue'
import { TARGET_ADMINS } from './helpers'

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

const handleEdit = async (row: any) => {
    // 列表行类型来自 el-table 的通用行插槽（DefaultRow），这里按已知的通知行结构使用
    const info = row as NotificationInfo
    // 列表行不含收件人；指定管理员的通知先取详情拿 admin_ids 再打开表单
    let adminIds: number[] = []
    if (info.target_type === TARGET_ADMINS) {
        try {
            const res = await notificationApi.getDetail(info.id)
            adminIds = res.data?.admin_ids ?? []
        } catch {
            // request.ts 响应拦截器已提示错误；取不到收件人就不打开表单，避免提交时误清空名单
            return
        }
    }
    formData.value = { ...info, admin_ids: adminIds }
    formVisible.value = true
}
</script>
